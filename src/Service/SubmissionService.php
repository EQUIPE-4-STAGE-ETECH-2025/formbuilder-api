<?php

namespace App\Service;

use App\Entity\Form;
use App\Entity\Submission;
use App\Entity\User;
use App\Repository\SubmissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SubmissionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SubmissionRepository $submissionRepository,
        private readonly QuotaService $quotaService,
        private readonly FormSchemaValidatorService $formSchemaValidatorService,
        private readonly EmailService $emailService,
    ) {}

    public function getFormById(string $id): ?Form
    {
        return $this->entityManager->getRepository(Form::class)->find($id);
    }

    public function submitForm(Form $form, array $submissionData, ?User $user = null, ?string $ipAddress = null): Submission
    {
        // Vérification du quota
        if ($user) {
            $this->quotaService->enforceQuotaLimit($user, 'submit_form');
        }

        // Récupération de la dernière version
        $formVersions = $form->getFormVersions();
        if ($formVersions->isEmpty()) {
            throw new BadRequestHttpException('Le formulaire n’a pas de version valide.');
        }

        $latestVersion = $formVersions->last();
        $schema = $latestVersion->getSchema();

        // Mapping labels → IDs
        $fieldMap = [];
        if (isset($schema['fields']) && is_array($schema['fields'])) {
            foreach ($schema['fields'] as $field) {
                if (isset($field['id']) && isset($field['label'])) {
                    $fieldMap[$field['label']] = $field['id'];
                }
            }
        }

        $filteredData = [];
        foreach ($submissionData as $label => $value) {
            if (isset($fieldMap[$label])) {
                $filteredData[$fieldMap[$label]] = $value;
            }
        }

        // Validation dynamique
        try {
            $this->formSchemaValidatorService->validateSchema($schema, $filteredData);
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Validation échouée : ' . $e->getMessage());
        }

        // Création de la soumission
        $submission = new Submission();
        $submission->setForm($form)
                   ->setData($filteredData)
                   ->setSubmitter($user)
                   ->setSubmittedAt(new \DateTimeImmutable())
                   ->setIpAddress($ipAddress);

        $this->entityManager->persist($submission);
        $this->entityManager->flush();

        // Notification email au propriétaire du formulaire
        if ($form->getUser()?->getEmail()) {
            $subject = sprintf("Nouvelle soumission pour votre formulaire '%s'", $form->getTitle());
            $content = sprintf(
                "Bonjour %s, <br><br>Une nouvelle soumission a été effectuée sur votre formulaire <strong>%s</strong>.",
                $form->getUser()->getFirstName(),
                $form->getTitle()
            );

            try {
                $this->emailService->sendEmail($form->getUser()->getEmail(), $subject, $content);
            } catch (\Exception) {
                // Log uniquement, on ne bloque pas la soumission
            }
        }

        return $submission;
    }

    public function getFormSubmissions(Form $form): array
    {
        return $this->submissionRepository->findBy(['form' => $form], ['submittedAt' => 'DESC']);
    }

    public function exportSubmissionsToCsv(Form $form): string
    {
        $submissions = $this->getFormSubmissions($form);
        if (empty($submissions)) {
            return '';
        }

        $csv = fopen('php://temp', 'r+');

        // Entêtes CSV (IDs des champs)
        $first = $submissions[0]->getData();
        fputcsv($csv, array_keys($first));

        foreach ($submissions as $submission) {
            fputcsv($csv, array_values($submission->getData()));
        }

        rewind($csv);
        $output = stream_get_contents($csv);
        fclose($csv);

        return $output;
    }
}
