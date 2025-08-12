<?php

namespace App\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly Environment $twig
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendEmailVerification(string $to, string $username, string $verificationUrl): void
    {
        try {
            $htmlContent = $this->twig->render('verify_email.html.twig', [
                'username' => $username,
                'verificationUrl' => $verificationUrl,
            ]);

            $email = (new Email())
                ->from(new Address('no-reply@formbuilder.com', 'FormBuilder'))
                ->to($to)
                ->subject('Vérifiez votre adresse email')
                ->html($htmlContent);

            $this->mailer->send($email);
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email vérification : ' . $e->getMessage());
        }
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendPasswordResetEmail(string $to, string $username, string $resetUrl): void
    {
        try {
            $htmlContent = $this->twig->render('reset_password.html.twig', [
                'username' => $username,
                'resetUrl' => $resetUrl,
            ]);

            $email = (new Email())
                ->from(new Address('no-reply@formbuilder.com', 'FormBuilder'))
                ->to($to)
                ->subject('Réinitialisation de votre mot de passe')
                ->html($htmlContent);

            $this->mailer->send($email);
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email réinitialisation : ' . $e->getMessage());
        }
    }

    /**
     * Envoie un email générique
     *
     * @throws TransportExceptionInterface
     */
    public function sendEmail(string $to, string $subject, string $htmlContent, bool $highPriority = false): void
    {
        try {
            $email = (new Email())
                ->from(new Address('no-reply@formbuilder.com', 'FormBuilder'))
                ->to($to)
                ->subject($subject)
                ->html($htmlContent);

            if ($highPriority) {
                $email->priority(Email::PRIORITY_HIGH);
            }

            $this->mailer->send($email);

            $this->logger->info('Email envoyé avec succès', [
                'to' => $to,
                'subject' => $subject,
                'high_priority' => $highPriority,
            ]);

        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email générique : ' . $e->getMessage(), [
                'to' => $to,
                'subject' => $subject,
            ]);

            throw $e;
        }
    }
}