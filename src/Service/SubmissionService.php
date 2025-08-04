<?php

namespace App\Service;

use App\Dto\SubmitFormDto;
use App\Entity\Form;
use App\Entity\Submission;
use App\Repository\FormRepository;
use App\Repository\FormVersionRepository;
use App\Repository\QuotaStatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SubmissionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private FormRepository $formRepository,
        private FormVersionRepository $formVersionRepository,
        private QuotaStatusRepository $quotaStatusRepository,
        private MailerInterface $mailer,
        private ParameterBagInterface $params,
    ) {}

    public function submit(string $formId, SubmitFormDto $dto, string $ipAddress): Submission
    {
        $form = $this->formRepository->find($formId);
        if (!$form) {
            throw new \InvalidArgumentException("Formulaire introuvable.");
        }

        $formVersion = $this->formVersionRepository->findOneBy(
            ['form' => $form],
            ['versionNumber' => 'DESC']
        );
        if (!$formVersion) {
            throw new \RuntimeException("Aucune version de formulaire trouvée.");
        }

        $schema = $formVersion->getSchema();
        $data = $dto->getData();

        $this->validateDynamic($schema, $data, $formVersion);
        $this->checkQuota($form->getUser());

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setData($data);
        $submission->setIpAddress($ipAddress);

        $this->em->persist($submission);
        $this->incrementSubmissionQuota($form->getUser()->getId());
        $this->em->flush();

        // Envoi d’un email de notification
        $frontendUrl = $this->params->get('env(FRONTEND_URL)');
        $submissionUrl = $frontendUrl . 'forms/' . $form->getId() . '/submissions';

        $email = (new Email())
            ->from('no-reply@formbuilder.com')
            ->to($form->getUser()->getEmail())
            ->subject('Nouvelle soumission reçue')
            ->html(sprintf(
                '<p>Vous avez reçu une nouvelle soumission pour le formulaire <strong>%s</strong>.</p>
                <p><a href="%s" target="_blank">Voir les réponses</a></p>',
                htmlspecialchars($form->getTitle(), ENT_QUOTES),
                $submissionUrl
            ));

        $this->mailer->send($email);

        return $submission;
    }

    private function validateDynamic(array $schema, array $data, \App\Entity\FormVersion $formVersion): void
    {
        // Indexation des FormFields pour accès rapide
        $fields = [];
        foreach ($formVersion->getFormFields() as $field) {
            $fields[$field->getId()] = $field;
        }

        foreach ($schema['fields'] ?? [] as $fieldSchema) {
            $name = $fieldSchema['name'] ?? null;
            $type = $fieldSchema['type'] ?? null;
            $required = $fieldSchema['required'] ?? false;

            $fieldEntity = $fields[$name] ?? null;
            $validationRules = $fieldEntity?->getValidationRules() ?? [];
            $label = $fieldEntity?->getLabel() ?? $name;

            $value = $data[$name] ?? null;

            // Champs requis
            if ($required && ($value === null || $value === '')) {
                throw new \InvalidArgumentException("Le champ requis '$label' est manquant.");
            }

            if ($value !== null) {
                // Validation des types de base
                if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException("Le champ '$label' doit être une adresse email valide.");
                }

                if ($type === 'number' && !is_numeric($value)) {
                    throw new \InvalidArgumentException("Le champ '$label' doit être un nombre.");
                }

                // Règles personnalisées (validationRules)
                if (isset($validationRules['regex']) && !preg_match('/' . $validationRules['regex'] . '/', $value)) {
                    $message = $validationRules['regexMessage'] ?? "Le champ '$label' n'est pas valide.";
                    throw new \InvalidArgumentException($message);
                }

                if (isset($validationRules['minLength']) && strlen($value) < $validationRules['minLength']) {
                    throw new \InvalidArgumentException("Le champ '$label' doit contenir au moins {$validationRules['minLength']} caractères.");
                }

                if (isset($validationRules['maxLength']) && strlen($value) > $validationRules['maxLength']) {
                    throw new \InvalidArgumentException("Le champ '$label' ne peut pas dépasser {$validationRules['maxLength']} caractères.");
                }

                if ($type === 'number') {
                    if (isset($validationRules['min']) && $value < $validationRules['min']) {
                        throw new \InvalidArgumentException("Le champ '$label' doit être supérieur ou égal à {$validationRules['min']}.");
                    }
                    if (isset($validationRules['max']) && $value > $validationRules['max']) {
                        throw new \InvalidArgumentException("Le champ '$label' doit être inférieur ou égal à {$validationRules['max']}.");
                    }
                }
            }
        }
    }

    private function checkQuota($user): void
    {
        $now = new \DateTimeImmutable('first day of this month');
        $quota = $this->quotaStatusRepository->findOneBy(['user' => $user, 'month' => $now]);

        if ($quota && $quota->getSubmissionCount() >= 1000) {
            throw new \RuntimeException('Quota de soumissions atteint pour ce mois.');
        }
    }

    private function incrementSubmissionQuota(string $userId): void
    {
        $now = new \DateTimeImmutable('first day of this month');
        $quota = $this->quotaStatusRepository->findOneBy(['user' => $userId, 'month' => $now]);

        if ($quota) {
            $quota->setSubmissionCount($quota->getSubmissionCount() + 1);
            $this->em->persist($quota);
        }
    }
}
