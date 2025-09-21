<?php

namespace App\Tests\Service;

use App\Entity\Form;
use App\Entity\FormVersion;
use App\Entity\Submission;
use App\Entity\User;
use App\Repository\FormVersionRepository;
use App\Repository\SubmissionRepository;
use App\Service\SubmissionExportService;
use PHPUnit\Framework\TestCase;

class SubmissionExportServiceTest extends TestCase
{
    private SubmissionExportService $service;
    private $submissionRepository;
    private $formVersionRepository;

    protected function setUp(): void
    {
        $this->submissionRepository = $this->createMock(SubmissionRepository::class);
        $this->formVersionRepository = $this->createMock(FormVersionRepository::class);
        $this->service = new SubmissionExportService(
            $this->submissionRepository,
            $this->formVersionRepository
        );
    }

    public function testExportSuccessWithSubmissions(): void
    {
        $user = $this->createConfiguredMock(User::class, ['getId' => 'user-1']);
        $form = $this->createConfiguredMock(Form::class, ['getUser' => $user, 'getId' => 'form-1']);

        // Créer le schéma JSON avec les champs et leurs labels
        $schema = [
            'fields' => [
                [
                    'id' => 'name',
                    'label' => 'Nom complet',
                    'type' => 'text',
                    'required' => true,
                ],
                [
                    'id' => 'email',
                    'label' => 'Adresse email',
                    'type' => 'email',
                    'required' => true,
                ],
                [
                    'id' => 'message',
                    'label' => 'Message',
                    'type' => 'textarea',
                    'required' => false,
                ],
            ],
        ];

        $formVersion = $this->createConfiguredMock(FormVersion::class, [
            'getSchema' => $schema,
        ]);

        $this->formVersionRepository
            ->method('findLatestVersionWithFields')
            ->with($form)
            ->willReturn($formVersion);

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

        // Vérifier que les labels sont utilisés dans les en-têtes au lieu des IDs
        $this->assertStringContainsString('ID;Form ID;Submitted At;IP Address;Nom complet;Adresse email;Message', $csv);
        $this->assertStringContainsString('test1@example.com', $csv);
        $this->assertStringContainsString('test2@example.com', $csv);
    }

    public function testExportReturnsEmptyWhenNoSubmissions(): void
    {
        $user = $this->createConfiguredMock(User::class, ['getId' => 'user-1']);
        $form = $this->getFormWithoutSubmissions();
        $form->setUser($user);

        $this->formVersionRepository
            ->method('findLatestVersionWithFields')
            ->with($form)
            ->willReturn(null);

        $this->submissionRepository
            ->method('findBy')
            ->willReturn([]);

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

    public function testExportWithMissingFieldLabels(): void
    {
        $user = $this->createConfiguredMock(User::class, ['getId' => 'user-1']);
        $form = $this->createConfiguredMock(Form::class, ['getUser' => $user, 'getId' => 'form-1']);

        // Créer le schéma JSON avec un champ qui a un label et un autre sans label
        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'label' => 'Nom',
                    'type' => 'text',
                    'required' => true,
                ],
                // Note: 'other_field' n'est pas dans le schéma, donc il utilisera son ID comme fallback
            ],
        ];

        $formVersion = $this->createConfiguredMock(FormVersion::class, [
            'getSchema' => $schema,
        ]);

        $this->formVersionRepository
            ->method('findLatestVersionWithFields')
            ->with($form)
            ->willReturn($formVersion);

        $submission = new Submission();
        $submission->setForm($form)
            ->setData(['field1' => 'Alice', 'other_field' => 'test'])
            ->setIpAddress('127.0.0.1');

        $this->submissionRepository
            ->method('findBy')
            ->willReturn([$submission]);

        $csv = $this->service->exportFormSubmissionsToCsv($form, $user);

        // Vérifier que le label est utilisé pour field1 et l'ID pour other_field (pas de mapping)
        $this->assertStringContainsString('ID;Form ID;Submitted At;IP Address;Nom;other_field', $csv);
    }

    // ---- Ajout de la méthode manquante ----
    private function getFormWithoutSubmissions(): Form
    {
        $form = new Form();
        $form->setTitle('Formulaire vide');

        return $form;
    }
}
