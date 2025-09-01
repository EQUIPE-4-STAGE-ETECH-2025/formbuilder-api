<?php

namespace App\Tests\Service;

use App\Entity\Form;
use App\Entity\FormVersion;
use App\Entity\Submission;
use App\Entity\User;
use App\Service\EmailService;
use App\Service\FormSchemaValidatorService;
use App\Service\QuotaService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use App\Repository\SubmissionRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SubmissionServiceTest extends TestCase
{
    private $entityManager;
    private $submissionRepository;
    private $quotaService;
    private $validatorService;
    private $emailService;
    private $submissionService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->submissionRepository = $this->createMock(SubmissionRepository::class);
        $this->quotaService = $this->createMock(QuotaService::class);
        $this->validatorService = $this->createMock(FormSchemaValidatorService::class);
        $this->emailService = $this->createMock(EmailService::class);

        $this->submissionService = new SubmissionService(
            $this->entityManager,
            $this->submissionRepository,
            $this->quotaService,
            $this->validatorService,
            $this->emailService
        );
    }

    public function testSubmitFormSuccessfully(): void
    {
        $user = new User();
        $user->setEmail('owner@example.com');

        $formVersion = new FormVersion();
        // On utilise 'id' et 'label' comme dans SubmissionService
        $formVersion->setSchema([
            'fields' => [
                ['id' => 'field1', 'label' => 'Nom complet', 'type' => 'text'],
                ['id' => 'field2', 'label' => 'Email', 'type' => 'email'],
                ['id' => 'field3', 'label' => 'Message', 'type' => 'textarea'],
            ]
        ]);

        $form = new Form();
        $form->setUser($user)->setTitle('Test Form')->addFormVersion($formVersion);

        $submissionData = [
            'Nom complet' => 'Alice Dupont',
            'Email' => 'alice@example.com',
            'Message' => 'Bonjour'
        ];

        // Transformation labels → ids pour matcher SubmissionService
        $expectedData = [
            'field1' => 'Alice Dupont',
            'field2' => 'alice@example.com',
            'field3' => 'Bonjour'
        ];

        $this->validatorService->expects($this->once())
            ->method('validateSchema')
            ->with($formVersion->getSchema(), $expectedData);

        $this->quotaService->expects($this->once())
            ->method('enforceQuotaLimit')
            ->with($user, 'submit_form');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $this->emailService->expects($this->once())
            ->method('sendEmail')
            ->with(
                'owner@example.com',
                $this->stringContains('Nouvelle soumission'),
                $this->stringContains('Test Form')
            );

        $submission = $this->submissionService->submitForm($form, $submissionData, $user, '127.0.0.1');

        $this->assertInstanceOf(Submission::class, $submission);
        $this->assertEquals($expectedData, $submission->getData());
        $this->assertSame($user, $submission->getSubmitter());
        $this->assertEquals('127.0.0.1', $submission->getIpAddress());
        $this->assertInstanceOf(\DateTimeImmutable::class, $submission->getSubmittedAt());
    }

    public function testSubmitFormValidationFails(): void
    {
        $formVersion = new FormVersion();
        $formVersion->setSchema(['fields' => [['id' => 'field1', 'label' => 'Email', 'type' => 'email']]]);

        $form = new Form();
        $form->setTitle('Test Form')->addFormVersion($formVersion);

        $submissionData = ['Email' => 'invalid-email'];
        $expectedData = ['field1' => 'invalid-email'];

        $this->validatorService->expects($this->once())
            ->method('validateSchema')
            ->with($formVersion->getSchema(), $expectedData)
            ->willThrowException(new \Exception('Email invalide'));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Validation échouée : Email invalide');

        $this->submissionService->submitForm($form, $submissionData);
    }

    public function testSubmitFormQuotaExceeded(): void
    {
        $user = new User();

        $formVersion = new FormVersion();
        $formVersion->setSchema(['fields' => [['id' => 'field1', 'label' => 'Email', 'type' => 'email']]]);

        $form = new Form();
        $form->setUser($user)->setTitle('Test Form')->addFormVersion($formVersion);

        $submissionData = ['Email' => 'test@example.com'];
        $expectedData = ['field1' => 'test@example.com'];

        $this->quotaService->expects($this->once())
            ->method('enforceQuotaLimit')
            ->willThrowException(new \RuntimeException('Limite de soumissions atteinte'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Limite de soumissions atteinte');

        $this->submissionService->submitForm($form, $submissionData, $user);
    }

    public function testExportSubmissionsToCsv(): void
    {
        $form = new Form();
        $form->setTitle('CSV Form');

        $submission1 = new Submission();
        $submission1->setData(['field1' => 'a@example.com']);

        $submission2 = new Submission();
        $submission2->setData(['field1' => 'b@example.com']);

        $this->submissionRepository->expects($this->any())
            ->method('findBy')
            ->willReturn([$submission1, $submission2]);

        $csv = $this->submissionService->exportSubmissionsToCsv($form);

        $this->assertStringContainsString('field1', $csv);
        $this->assertStringContainsString('a@example.com', $csv);
        $this->assertStringContainsString('b@example.com', $csv);
    }
}