<?php

namespace App\Service;

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

    /**
     * Envoie un email de confirmation d'abonnement
     */
    public function sendSubscriptionConfirmation(User $user, StripeSubscription $stripeSubscription): void
    {
        try {
            $subject = 'Bienvenue ! Votre abonnement FormBuilder est confirmé';
            $htmlContent = sprintf(
                '
                <h1>Bienvenue %s !</h1>
                <p>Votre abonnement FormBuilder a été confirmé avec succès.</p>
                <p><strong>Détails de votre abonnement :</strong></p>
                <ul>
                    <li>ID d\'abonnement : %s</li>
                    <li>Statut : %s</li>
                    <li>Période de facturation : du %s au %s</li>
                </ul>
                <p>Vous pouvez maintenant profiter pleinement de toutes les fonctionnalités de votre plan.</p>
                <p>Merci de votre confiance !</p>
                <p>L\'équipe FormBuilder</p>
            ',
                $user->getFirstName(),
                $stripeSubscription->id,
                ucfirst($stripeSubscription->status),
                date('d/m/Y', $stripeSubscription->current_period_start ?? time()),
                date('d/m/Y', $stripeSubscription->current_period_end ?? time())
            );

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

            $urgencyMessage = match ($attemptNumber) {
                1 => 'Nous avons rencontré un problème lors du traitement de votre paiement.',
                2 => 'Votre paiement a échoué pour la deuxième fois.',
                default => 'C\'est votre dernière chance avant la suspension de votre compte.'
            };

            $htmlContent = sprintf(
                '
                <h1>Problème avec votre paiement</h1>
                <p>Bonjour %s,</p>
                <p>%s</p>
                <p><strong>Détails de la facture :</strong></p>
                <ul>
                    <li>Montant : %.2f %s</li>
                    <li>Facture : %s</li>
                    <li>Tentative : %d</li>
                </ul>
                <p>Veuillez mettre à jour votre méthode de paiement pour éviter toute interruption de service.</p>
                <p><a href="%s" style="background-color: #ff4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Mettre à jour le paiement</a></p>
                <p>L\'équipe FormBuilder</p>
            ',
                $user->getFirstName(),
                $urgencyMessage,
                $invoice->amount_due / 100,
                strtoupper($invoice->currency),
                $invoice->id,
                $attemptNumber,
                $invoice->hosted_invoice_url ?? '#'
            );

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
            $htmlContent = sprintf(
                '
                <h1 style="color: #ff4444;">⚠️ Suspension imminente de votre compte</h1>
                <p>Bonjour %s,</p>
                <p><strong>Votre compte FormBuilder sera suspendu sous peu à défaut de paiement.</strong></p>
                <p>Malgré nos tentatives répétées, nous n\'avons pas pu traiter votre paiement.</p>
                <p><strong>Détails de la facture impayée :</strong></p>
                <ul>
                    <li>Montant : %.2f %s</li>
                    <li>Facture : %s</li>
                </ul>
                <p><strong>Action requise :</strong> Mettez à jour votre méthode de paiement immédiatement pour éviter la suspension.</p>
                <p><a href="%s" style="background-color: #ff4444; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">PAYER MAINTENANT</a></p>
                <p>En cas de suspension, vos formulaires deviendront inaccessibles et vos données seront conservées pendant 7 jours seulement.</p>
                <p>L\'équipe FormBuilder</p>
            ',
                $user->getFirstName(),
                $invoice->amount_due / 100,
                strtoupper($invoice->currency),
                $invoice->id,
                $invoice->hosted_invoice_url ?? '#'
            );

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
            $htmlContent = sprintf(
                '
                <h1 style="color: #ff4444;">🚫 Compte suspendu</h1>
                <p>Bonjour %s,</p>
                <p>Votre compte FormBuilder a été suspendu en raison de paiements en souffrance.</p>
                <p><strong>Que signifie cette suspension ?</strong></p>
                <ul>
                    <li>Vos formulaires ne sont plus accessibles au public</li>
                    <li>Vous ne pouvez plus créer de nouveaux formulaires</li>
                    <li>Les soumissions existantes sont préservées</li>
                </ul>
                <p><strong>Comment réactiver votre compte ?</strong></p>
                <p>Réglez simplement vos factures en souffrance pour réactiver immédiatement votre compte.</p>
                <p><strong>Période de grâce :</strong> Vous avez 7 jours pour régler vos factures avant que votre compte ne soit rétrogradé vers le plan gratuit.</p>
                <p><a href="#" style="background-color: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">RÉACTIVER MON COMPTE</a></p>
                <p>L\'équipe FormBuilder</p>
            ',
                $user->getFirstName()
            );

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
            $htmlContent = sprintf(
                '
                <h1 style="color: #28a745;">✅ Compte réactivé !</h1>
                <p>Bonjour %s,</p>
                <p>Excellente nouvelle ! Votre compte FormBuilder a été réactivé avec succès.</p>
                <p><strong>Vous pouvez maintenant :</strong></p>
                <ul>
                    <li>Accéder à tous vos formulaires</li>
                    <li>Créer de nouveaux formulaires</li>
                    <li>Recevoir de nouvelles soumissions</li>
                    <li>Profiter de toutes les fonctionnalités de votre plan</li>
                </ul>
                <p>Merci d\'avoir régularisé votre situation !</p>
                <p>L\'équipe FormBuilder</p>
            ',
                $user->getFirstName()
            );

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
     * Envoie un email de notification d'annulation d'abonnement
     */
    public function sendSubscriptionCancellation(User $user): void
    {
        try {
            $subject = 'Confirmation d\'annulation de votre abonnement FormBuilder';
            $htmlContent = sprintf(
                '
                <h1>Abonnement annulé</h1>
                <p>Bonjour %s,</p>
                <p>Nous confirmons que votre abonnement FormBuilder a été annulé.</p>
                <p><strong>Que se passe-t-il maintenant ?</strong></p>
                <ul>
                    <li>Votre abonnement reste actif jusqu\'à la fin de votre période de facturation</li>
                    <li>Aucun nouveau paiement ne sera prélevé</li>
                    <li>Vous pouvez continuer à utiliser toutes les fonctionnalités jusqu\'à l\'expiration</li>
                    <li>Après expiration, votre compte passera automatiquement au plan gratuit</li>
                </ul>
                <p>Nous sommes désolés de vous voir partir. Si vous changez d\'avis, vous pouvez réactiver votre abonnement à tout moment.</p>
                <p>L\'équipe FormBuilder</p>
            ',
                $user->getFirstName()
            );

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

    /**
     * Envoie un email de notification de fin d'essai
     */
    public function sendTrialEndingNotification(User $user, StripeSubscription $stripeSubscription): void
    {
        try {
            $trialEndDate = date('d/m/Y', $stripeSubscription->trial_end);
            $subject = 'Votre essai gratuit FormBuilder se termine bientôt';
            $htmlContent = sprintf(
                '
                <h1>Votre essai gratuit se termine bientôt</h1>
                <p>Bonjour %s,</p>
                <p>Votre essai gratuit FormBuilder se termine le <strong>%s</strong>.</p>
                <p>Pour continuer à profiter de toutes les fonctionnalités, votre abonnement payant démarrera automatiquement.</p>
                <p><strong>Vous pouvez :</strong></p>
                <ul>
                    <li>Continuer avec votre abonnement actuel</li>
                    <li>Changer de plan si nécessaire</li>
                    <li>Annuler avant la fin de l\'essai si vous ne souhaitez pas continuer</li>
                </ul>
                <p><a href="#" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Gérer mon abonnement</a></p>
                <p>L\'équipe FormBuilder</p>
            ',
                $user->getFirstName(),
                $trialEndDate
            );

            $email = $user->getEmail();
            if ($email) {
                $this->sendEmail($email, $subject, $htmlContent);
            }
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email fin d\'essai', [
                'user_id' => $user->getId(),
                'subscription_id' => $stripeSubscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
