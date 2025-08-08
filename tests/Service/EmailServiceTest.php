<?php

namespace App\Tests\Service;

use App\Service\EmailService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailServiceTest extends TestCase
{
    private $mailer;
    private $logger;
    private $twig;
    private EmailService $emailService;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->emailService = new EmailService($this->mailer, $this->logger, $this->twig);
    }

    public function testSendEmailVerificationSuccess(): void
    {
        $to = 'user@example.com';
        $username = 'Jean';
        $verificationUrl = 'http://test/verify';

        $renderedHtml = '<html>Verification Email</html>';

        // Twig doit rendre le template et retourner la chaîne HTML
        $this->twig->expects($this->once())
            ->method('render')
            ->with('verify_email.html.twig', ['username' => $username, 'verificationUrl' => $verificationUrl])
            ->willReturn($renderedHtml);

        // On attend que le mailer envoie un Email dont le contenu html est $renderedHtml
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use ($to, $renderedHtml) {
                return
                    $email->getTo()[0]->getAddress() === $to &&
                    $email->getSubject() === 'Vérifiez votre adresse email' &&
                    (string)$email->getHtmlBody() === $renderedHtml;
            }));

        // Logger ne doit rien recevoir ici
        $this->logger->expects($this->never())
            ->method('error');

        $this->emailService->sendEmailVerification($to, $username, $verificationUrl);
    }

    public function testSendEmailVerificationLogsErrorOnException(): void
    {
        $to = 'user@example.com';
        $username = 'Jean';
        $verificationUrl = 'http://test/verify';

        $this->twig->method('render')->willThrowException(new \Exception('Twig error'));

        $this->mailer->expects($this->never())->method('send');

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Erreur envoi email vérification'));

        $this->emailService->sendEmailVerification($to, $username, $verificationUrl);
    }

    public function testSendPasswordResetEmailSuccess(): void
    {
        $to = 'user@example.com';
        $username = 'Jean';
        $resetUrl = 'http://test/reset';

        $renderedHtml = '<html>Reset Password Email</html>';

        $this->twig->expects($this->once())
            ->method('render')
            ->with('reset_password.html.twig', ['username' => $username, 'resetUrl' => $resetUrl])
            ->willReturn($renderedHtml);

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use ($to, $renderedHtml) {
                return
                    $email->getTo()[0]->getAddress() === $to &&
                    $email->getSubject() === 'Réinitialisation de votre mot de passe' &&
                    (string)$email->getHtmlBody() === $renderedHtml;
            }));

        $this->logger->expects($this->never())
            ->method('error');

        $this->emailService->sendPasswordResetEmail($to, $username, $resetUrl);
    }

    public function testSendPasswordResetEmailLogsErrorOnException(): void
    {
        $to = 'user@example.com';
        $username = 'Jean';
        $resetUrl = 'http://test/reset';

        $this->twig->method('render')->willThrowException(new \Exception('Twig error'));

        $this->mailer->expects($this->never())->method('send');

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Erreur envoi email réinitialisation'));

        $this->emailService->sendPasswordResetEmail($to, $username, $resetUrl);
    }
}
