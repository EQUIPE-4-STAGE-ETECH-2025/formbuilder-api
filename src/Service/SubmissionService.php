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

class SubmissionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private FormRepository $formRepository,
        private FormVersionRepository $formVersionRepository,
        private QuotaStatusRepository $quotaStatusRepository,
        private MailerInterface $mailer,
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

        $this->validateDynamic($schema, $data);
        $this->checkQuota($form->getUser());

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setData($data);
        $submission->setIpAddress($ipAddress);

        $this->em->persist($submission);
        $this->incrementSubmissionQuota($form->getUser()->getId());
        $this->em->flush();

        // Envoi d’un email de notification au propriétaire du formulaire
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

    private function validateDynamic(array $schema, array $data): void
    {
        foreach ($schema['fields'] ?? [] as $field) {
            $name = $field['name'] ?? null;
            $type = $field['type'] ?? null;
            $required = $field['required'] ?? false;

            if ($required && (!isset($data[$name]) || $data[$name] === '')) {
                throw new \InvalidArgumentException("Le champ requis '$name' est manquant.");
            }

            if (isset($data[$name])) {
                if ($type === 'email' && !filter_var($data[$name], FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException("Le champ '$name' doit être une adresse email valide.");
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
