<?php

namespace App\Tests\Service;

use App\Entity\Form;
use App\Entity\Submission;
use App\Repository\FormRepository;
use App\Repository\SubmissionRepository;
use App\Service\SubmissionAnalyticsService;
use PHPUnit\Framework\TestCase;

class SubmissionAnalyticsServiceTest extends TestCase
{
    private $submissionRepository;
    private $formRepository;
    private $analyticsService;

    protected function setUp(): void
    {
        // Mock des repositories
        $this->submissionRepository = $this->createMock(SubmissionRepository::class);
        $this->formRepository = $this->createMock(FormRepository::class);

        // Instanciation du service avec les mocks
        $this->analyticsService = new SubmissionAnalyticsService(
            $this->submissionRepository,
            $this->formRepository
        );
    }

    public function testGetFormAnalytics(): void
    {
        // Mock du formulaire
        $form = $this->createMock(Form::class);
        $form->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-10 days')); // Ajout de getCreatedAt

        // Mock des soumissions
        $submission1 = $this->createMock(Submission::class);
        $submission1->method('getSubmittedAt')->willReturn(new \DateTimeImmutable('-1 day'));
        $submission1->method('getForm')->willReturn($form); // Ajout de getForm

        $submission2 = $this->createMock(Submission::class);
        $submission2->method('getSubmittedAt')->willReturn(new \DateTimeImmutable('-2 days'));
        $submission2->method('getForm')->willReturn($form); // Ajout de getForm

        $submissions = [$submission1, $submission2];

        // Configuration des mocks
        $this->formRepository->method('find')->with('form-id')->willReturn($form);
        $this->submissionRepository->method('findBy')->with(['form' => $form])->willReturn($submissions);

        // Appel de la méthode à tester
        $result = $this->analyticsService->getFormAnalytics('form-id');

        // Assertions
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(2, $result['total']);

        $this->assertArrayHasKey('daily', $result);
        $this->assertCount(7, $result['daily']);
        $this->assertEquals(1, $result['daily'][date('Y-m-d', strtotime('-1 day'))]);
        $this->assertEquals(1, $result['daily'][date('Y-m-d', strtotime('-2 days'))]);

        $this->assertArrayHasKey('conversion_rate', $result);
        $this->assertEquals(2.0, $result['conversion_rate']); // Basé sur le simulateur de visiteurs (100 visiteurs)

        $this->assertArrayHasKey('average_submission_time', $result);
        $this->assertNotNull($result['average_submission_time']);
    }

    public function testGetFormAnalyticsWithInvalidForm(): void
    {
        // Le formulaire n'existe pas
        $this->formRepository->method('find')->with('invalid-form-id')->willReturn(null);

        // Appel de la méthode et vérification de l'exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Formulaire introuvable.');

        $this->analyticsService->getFormAnalytics('invalid-form-id');
    }
}
