<?php

namespace App\Service;

use App\Entity\Form;
use App\Entity\Submission;
use App\Entity\Subscription;
use App\Entity\User;
use Exception;
use Psr\Log\LoggerInterface;
use Stripe\Invoice;
use Stripe\Subscription as StripeSubscription;
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
            $htmlContent = $this->twig->render('emails/verify_email.html.twig', [
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
            $htmlContent = $this->twig->render('emails/reset_password.html.twig', [
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

    /**
     * Envoie un email de confirmation d'abonnement
     */
    public function sendSubscriptionConfirmation(User $user, StripeSubscription $stripeSubscription): void
    {
        try {
            $subject = 'Bienvenue ! Votre abonnement FormBuilder est confirmé';
            $htmlContent = $this->twig->render('emails/subscription_confirmation.html.twig', [
                'user' => $user,
                'stripeSubscription' => $stripeSubscription,
                'frontend_url' => $_ENV['FRONTEND_URL'] ?? 'https://formbuilder.com',
            ]);

            $email = $user->getEmail();
            if ($email) {
                $this->sendEmail($email, $subject, $htmlContent);
            }
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email confirmation abonnement', [
                'user_id' => $user->getId(),
                'subscription_id' => $stripeSubscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie un email de notification d'échec de paiement
     */
    public function sendPaymentFailedNotification(User $user, Invoice $invoice, int $attemptNumber = 1): void
    {
        try {
            $subject = match ($attemptNumber) {
                1 => 'Problème avec votre paiement FormBuilder',
                2 => 'URGENT : Problème avec votre paiement FormBuilder',
                default => 'DERNIÈRE CHANCE : Problème avec votre paiement FormBuilder'
            };

            $htmlContent = $this->twig->render('emails/payment_failed.html.twig', [
                'user' => $user,
                'invoice' => $invoice,
                'attemptNumber' => $attemptNumber,
                'frontend_url' => $_ENV['FRONTEND_URL'] ?? 'https://formbuilder.com',
            ]);

            $email = $user->getEmail();
            if ($email) {
                $this->sendEmail($email, $subject, $htmlContent, $attemptNumber > 1);
            }
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email échec paiement', [
                'user_id' => $user->getId(),
                'invoice_id' => $invoice->id,
                'attempt_number' => $attemptNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie un email d'avertissement de suspension
     */
    public function sendSuspensionWarningNotification(User $user, Invoice $invoice): void
    {
        try {
            $subject = '⚠️ SUSPENSION IMMINENTE - Action requise';
            $htmlContent = $this->twig->render('emails/payment_failed.html.twig', [
                'user' => $user,
                'invoice' => $invoice,
                'attemptNumber' => 3, // Dernière chance
                'frontend_url' => $_ENV['FRONTEND_URL'] ?? 'https://formbuilder.com',
            ]);

            $email = $user->getEmail();
            if ($email) {
                $this->sendEmail($email, $subject, $htmlContent, true);
            }
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email avertissement suspension', [
                'user_id' => $user->getId(),
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie un email de notification de suspension
     */
    public function sendSubscriptionSuspendedNotification(User $user, Subscription $subscription): void
    {
        try {
            $subject = '🚫 Votre compte FormBuilder a été suspendu';
            $htmlContent = $this->twig->render('emails/subscription_suspended.html.twig', [
                'user' => $user,
                'subscription' => $subscription,
                'frontend_url' => $_ENV['FRONTEND_URL'] ?? 'https://formbuilder.com',
            ]);

            $email = $user->getEmail();
            if ($email) {
                $this->sendEmail($email, $subject, $htmlContent, true);
            }
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email suspension', [
                'user_id' => $user->getId(),
                'subscription_id' => $subscription->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie un email de notification de réactivation
     */
    public function sendSubscriptionReactivatedNotification(User $user): void
    {
        try {
            $subject = '✅ Votre compte FormBuilder a été réactivé';
            $htmlContent = $this->twig->render('emails/subscription_reactivated.html.twig', [
                'user' => $user,
                'frontend_url' => $_ENV['FRONTEND_URL'] ?? 'https://formbuilder.com',
            ]);

            $email = $user->getEmail();
            if ($email) {
                $this->sendEmail($email, $subject, $htmlContent);
            }
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email réactivation', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie un email de notification de quota
     *
     * @param array<string, mixed> $quotaData
     */
    public function sendQuotaAlert(User $user, int $percentage, array $quotaData): void
    {
        try {
            $subject = sprintf('Alerte quota - %d%% atteint - FormBuilder', $percentage);
            $htmlContent = $this->twig->render('emails/quota_alert.html.twig', [
                'user' => $user,
                'percentage' => $percentage,
                'quotaData' => $quotaData,
                'frontend_url' => $_ENV['FRONTEND_URL'] ?? 'https://formbuilder.com',
            ]);

            $email = $user->getEmail();
            if ($email) {
                $this->sendEmail($email, $subject, $htmlContent, $percentage >= 95);
            }
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email alerte quota', [
                'user_id' => $user->getId(),
                'percentage' => $percentage,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie un email de notification de nouvelle soumission
     */
    public function sendNewSubmissionNotification(User $user, Form $form, Submission $submission, int $totalSubmissions): void
    {
        try {
            $subject = sprintf("Nouvelle soumission pour votre formulaire '%s'", $form->getTitle());
            $htmlContent = $this->twig->render('emails/new_submission.html.twig', [
                'user' => $user,
                'form' => $form,
                'submission' => $submission,
                'totalSubmissions' => $totalSubmissions,
                'frontend_url' => $_ENV['FRONTEND_URL'] ?? 'https://formbuilder.com',
            ]);

            $email = $user->getEmail();
            if ($email) {
                $this->sendEmail($email, $subject, $htmlContent);
            }
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email nouvelle soumission', [
                'user_id' => $user->getId(),
                'form_id' => $form->getId(),
                'submission_id' => $submission->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie un email de notification d'annulation d'abonnement
     */
    public function sendSubscriptionCancellation(User $user): void
    {
        try {
            $subject = 'Confirmation d\'annulation de votre abonnement FormBuilder';
            $htmlContent = $this->twig->render('emails/subscription_cancellation.html.twig', [
                'user' => $user,
                'frontend_url' => $_ENV['FRONTEND_URL'] ?? 'https://formbuilder.com',
            ]);

            $email = $user->getEmail();
            if ($email) {
                $this->sendEmail($email, $subject, $htmlContent);
            }
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email annulation abonnement', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
