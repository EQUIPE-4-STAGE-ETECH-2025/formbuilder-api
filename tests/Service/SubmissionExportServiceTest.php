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
        $user = $this->createConfiguredMock(User::class, [
            'getId' => 'user-1'
        ]);

        $form = $this->createConfiguredMock(Form::class, [
            'getUser' => $user,
            'getId' => 'form-1'
        ]);

        $submission1 = new Submission();
        $submission1->setForm($form)
            ->setData(['email' => 'test1@example.com'])
            ->setIpAddress('127.0.0.1');

        $submission2 = new Submission();
        $submission2->setForm($form)
            ->setData(['email' => 'test2@example.com'])
            ->setIpAddress('127.0.0.2');

        $this->submissionRepository
            ->method('findBy')
            ->with(['form' => $form], ['submittedAt' => 'DESC'], null, null)
            ->willReturn([$submission1, $submission2]);

        $csv = $this->service->exportFormSubmissionsToCsv($form, $user);

        $this->assertStringContainsString('ID;Form ID;Submitted At;IP Address;email', $csv);
        $this->assertStringContainsString('test1@example.com', $csv);
        $this->assertStringContainsString('test2@example.com', $csv);
    }

    public function testExportReturnsEmptyWhenNoSubmissions(): void
    {
        $user = $this->createConfiguredMock(User::class, ['getId' => 'user-1']);
        $form = $this->createConfiguredMock(Form::class, ['getUser' => $user]);

        $this->submissionRepository
            ->method('findBy')
            ->willReturn([]);

        $csv = $this->service->exportFormSubmissionsToCsv($form, $user);

        $this->assertSame('', $csv);
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
}
