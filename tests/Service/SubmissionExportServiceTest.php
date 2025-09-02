<?php

namespace App\Tests\Service;

use App\Entity\Form;
use App\Entity\Submission;
use App\Entity\User;
use App\Repository\SubmissionRepository;
use App\Service\SubmissionExportService;
use PHPUnit\Framework\TestCase;

class SubmissionExportServiceTest extends TestCase
{
    private SubmissionExportService $service;
    private $submissionRepository;

    protected function setUp(): void
    {
        $this->submissionRepository = $this->createMock(SubmissionRepository::class);
        $this->service = new SubmissionExportService($this->submissionRepository);
    }

    public function testExportSuccessWithSubmissions(): void
    {
        $user = $this->createConfiguredMock(User::class, ['getId' => 'user-1']);
        $form = $this->createConfiguredMock(Form::class, ['getUser' => $user, 'getId' => 'form-1']);

        $submission1 = new Submission();
        $submission1->setForm($form)
            ->setData(['name' => 'Alice', 'email' => 'test1@example.com', 'message' => 'Hello'])
            ->setIpAddress('127.0.0.1');

        $submission2 = new Submission();
        $submission2->setForm($form)
            ->setData(['name' => 'Bob', 'email' => 'test2@example.com', 'message' => 'Hi'])
            ->setIpAddress('127.0.0.2');

        $this->submissionRepository
            ->method('findBy')
            ->with(['form' => $form], ['submittedAt' => 'DESC'], null, null)
            ->willReturn([$submission1, $submission2]);

        $csv = $this->service->exportFormSubmissionsToCsv($form, $user);

        $this->assertStringContainsString('ID;Form ID;Submitted At;IP Address;name;email;message', $csv);
        $this->assertStringContainsString('test1@example.com', $csv);
        $this->assertStringContainsString('test2@example.com', $csv);
    }

    public function testExportReturnsEmptyWhenNoSubmissions(): void
    {
        $user = $this->createConfiguredMock(User::class, ['getId' => 'user-1']);
        $form = $this->getFormWithoutSubmissions();
        $form->setUser($user);

        $csv = $this->service->exportFormSubmissionsToCsv($form, $user);
        $csv = rtrim($csv, "\n\r");

        $this->assertSame('ID;Form ID;Submitted At;IP Address', $csv);
    }

    public function testExportThrowsExceptionWhenUnauthorized(): void
    {
        $owner = $this->createConfiguredMock(User::class, ['getId' => 'user-owner']);
        $attacker = $this->createConfiguredMock(User::class, ['getId' => 'user-attacker']);
        $form = $this->createConfiguredMock(Form::class, ['getUser' => $owner]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Accès interdit : ce formulaire ne vous appartient pas.");

        $this->service->exportFormSubmissionsToCsv($form, $attacker);
    }

    // ---- Ajout de la méthode manquante ----
    private function getFormWithoutSubmissions(): Form
    {
        $form = new Form();
        $form->setTitle('Formulaire vide');

        return $form;
    }
}
