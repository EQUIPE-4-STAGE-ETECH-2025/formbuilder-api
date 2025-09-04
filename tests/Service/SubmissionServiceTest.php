<?php

namespace App\Tests\Service;

use App\Entity\Form;
use App\Entity\FormVersion;
use App\Entity\Submission;
use App\Entity\User;
use App\Repository\SubmissionRepository;
use App\Service\EmailService;
use App\Service\FormSchemaValidatorService;
use App\Service\QuotaService;
use App\Service\SubmissionService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SubmissionServiceTest extends TestCase
{
    private $entityManager;
    private $submissionRepository;
    private $quotaService;
    private $validatorService;
    private $emailService;
    private $submissionService;

    /**
     * @throws Exception
     */
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
        $user->setEmail('owner@example.com')->setFirstName('Jean');

        $formVersion = new FormVersion();
        $formVersion->setSchema([
            'fields' => [
                ['id' => 'field1', 'label' => 'Nom complet'],
                ['id' => 'field2', 'label' => 'Email'],
                ['id' => 'field3', 'label' => 'Message'],
            ],
        ]);

        $form = new Form();
        $form->setUser($user)->setTitle('Test Form')->addFormVersion($formVersion);

        $submissionData = [
            'Nom complet' => 'Alice Dupont',
            'Email' => 'alice@example.com',
            'Message' => 'Bonjour',
        ];

        $expectedData = [
            'field1' => 'Alice Dupont',
            'field2' => 'alice@example.com',
            'field3' => 'Bonjour',
        ];

        // Expectations
        $this->quotaService->expects($this->once())->method('enforceQuotaLimit')->with($user, 'submit_form');
        $this->validatorService->expects($this->once())->method('validateSchema')->with($formVersion->getSchema(), $expectedData);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        $this->quotaService->expects($this->once())->method('calculateCurrentQuotas')->with($user);
        $this->emailService->expects($this->once())->method('sendEmail')->with(
            'owner@example.com',
            $this->stringContains('Nouvelle soumission'),
            $this->stringContains('Test Form')
        );

        $submission = $this->submissionService->submitForm($form, $submissionData, '127.0.0.1');

        $this->assertEquals($expectedData, $submission->getData());
        $this->assertEquals('127.0.0.1', $submission->getIpAddress());
        $this->assertInstanceOf(DateTimeImmutable::class, $submission->getSubmittedAt());
    }

    public function testSubmitFormValidationFails(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $formVersion = new FormVersion();
        $formVersion->setSchema(['fields' => [['id' => 'field1', 'label' => 'Email']]]);

        $form = new Form();
        $form->setUser($user) // ✅ obligatoire
        ->setTitle('Test Form')
            ->addFormVersion($formVersion);

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
        $formVersion->setSchema(['fields' => [['id' => 'field1', 'label' => 'Email']]]);
        $form = new Form();
        $form->setUser($user)->setTitle('Test Form')->addFormVersion($formVersion);

        $submissionData = ['Email' => 'test@example.com'];

        $this->quotaService->expects($this->once())
            ->method('enforceQuotaLimit')
            ->willThrowException(new RuntimeException('Limite de soumissions atteinte'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Limite de soumissions atteinte');

        $this->submissionService->submitForm($form, $submissionData);
    }

    public function testExportSubmissionsToCsv(): void
    {
        $form = new Form();
        $form->setTitle('Contact Form');

        $submission1 = (new Submission())->setData(['field1' => 'Alice', 'field2' => 'alice@example.com']);
        $submission2 = (new Submission())->setData(['field1' => 'Bob', 'field2' => 'bob@example.com']);

        $this->submissionRepository->expects($this->once())
            ->method('findBy')
            ->with(['form' => $form])
            ->willReturn([$submission1, $submission2]);

        $csv = $this->submissionService->exportSubmissionsToCsv($form);

        $this->assertStringContainsString('field1,field2', $csv);
        $this->assertStringContainsString('Alice,alice@example.com', $csv);
        $this->assertStringContainsString('Bob,bob@example.com', $csv);
    }

    public function testGetSubmissionsPaginated(): void
    {
        $form = new Form();
        $submissions = [new Submission(), new Submission()];

        $this->submissionRepository->expects($this->once())
            ->method('findBy')
            ->with(['form' => $form], ['submittedAt' => 'DESC'], 10, 10) // offset doit être 10
            ->willReturn($submissions);

        $result = $this->submissionService->getFormSubmissions($form, 2, 10);

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(Submission::class, $result);
    }

    public function testCountSubmissionsByForm(): void
    {
        $form = new Form();

        $this->submissionRepository->expects($this->once())
            ->method('count')
            ->with(['form' => $form])
            ->willReturn(42);

        $count = $this->submissionService->countFormSubmissions($form);

        $this->assertEquals(42, $count);
    }
}
