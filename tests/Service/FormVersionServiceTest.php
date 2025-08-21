<?php

namespace App\Tests\Service;

use App\Entity\Form;
use App\Entity\FormVersion;
use App\Repository\FormVersionRepository;
use App\Service\FormSchemaValidatorService;
use App\Service\FormVersionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class FormVersionServiceTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testCreateVersionSuccess(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setTitle('Test Form');

        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                    'position' => 1,
                ],
            ],
        ];

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findOneBy')
            ->willReturn(null); // Pas de version existante
        $formVersionRepository->method('count')->willReturn(0);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->atLeastOnce())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $schemaValidator = $this->createMock(FormSchemaValidatorService::class);
        $schemaValidator->expects($this->once())->method('validateSchema')->with($schema);

        $logger = $this->createMock(LoggerInterface::class);

        $formVersionService = new FormVersionService(
            $formVersionRepository,
            $entityManager,
            $schemaValidator,
            $logger
        );

        $result = $formVersionService->createVersion($form, $schema);

        $this->assertEquals(1, $result->versionNumber);
        $this->assertEquals($schema, $result->schema);
    }

    /**
     * @throws Exception
     */
    public function testCreateVersionWithExistingVersions(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());

        $existingVersion = new FormVersion();
        $existingVersion->setVersionNumber(2);

        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'email',
                    'label' => 'Email',
                    'required' => true,
                    'position' => 1,
                ],
            ],
        ];

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findOneBy')
            ->with(['form' => $form], ['versionNumber' => 'DESC'])
            ->willReturn($existingVersion);
        $formVersionRepository->method('findBy')
            ->willReturn([$existingVersion]);
        $formVersionRepository->method('count')->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->atLeastOnce())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $schemaValidator = $this->createMock(FormSchemaValidatorService::class);
        $schemaValidator->expects($this->once())->method('validateSchema');

        $logger = $this->createMock(LoggerInterface::class);

        $formVersionService = new FormVersionService(
            $formVersionRepository,
            $entityManager,
            $schemaValidator,
            $logger
        );

        $result = $formVersionService->createVersion($form, $schema);

        $this->assertEquals(3, $result->versionNumber); // 2 + 1
        $this->assertEquals($schema, $result->schema);
    }

    /**
     * @throws Exception
     */
    public function testCreateVersionCleansUpOldVersions(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());

        $oldVersions = [];
        for ($i = 1; $i <= 12; $i++) {
            $version = new FormVersion();
            $version->setVersionNumber($i);
            $oldVersions[] = $version;
        }

        $latestVersion = $oldVersions[11]; // Version 12

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findOneBy')
            ->with(['form' => $form], ['versionNumber' => 'DESC'])
            ->willReturn($latestVersion);
        $formVersionRepository->method('findBy')
            ->with(['form' => $form], ['versionNumber' => 'DESC'])
            ->willReturn($oldVersions);
        $formVersionRepository->method('count')->willReturn(12);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->atLeast(3))->method('remove'); // Supprimer au moins 3 anciennes versions
        $entityManager->expects($this->atLeastOnce())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $schemaValidator = $this->createMock(FormSchemaValidatorService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formVersionService = new FormVersionService(
            $formVersionRepository,
            $entityManager,
            $schemaValidator,
            $logger
        );

        $result = $formVersionService->createVersion($form, ['fields' => []]);

        $this->assertEquals(13, $result->versionNumber);
    }

    /**
     * @throws Exception
     */
    public function testCreateVersionWithInvalidSchema(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());

        $invalidSchema = [
            'fields' => [
                [
                    'type' => 'invalid_type', // Type invalide
                    'label' => 'Test',
                ],
            ],
        ];

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $schemaValidator = $this->createMock(FormSchemaValidatorService::class);
        $schemaValidator->method('validateSchema')
            ->willThrowException(new \InvalidArgumentException('Schéma invalide'));

        $logger = $this->createMock(LoggerInterface::class);

        $formVersionService = new FormVersionService(
            $formVersionRepository,
            $entityManager,
            $schemaValidator,
            $logger
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Schéma invalide');

        $formVersionService->createVersion($form, $invalidSchema);
    }

    /**
     * @throws Exception
     */
    public function testGetVersionsByForm(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());

        $version1 = new FormVersion();
        $version1->setId(Uuid::v4());
        $version1->setVersionNumber(1);
        $version1->setSchema(['fields' => []]);

        $version2 = new FormVersion();
        $version2->setId(Uuid::v4());
        $version2->setVersionNumber(2);
        $version2->setSchema(['fields' => []]);

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findBy')
            ->with(['form' => $form], ['versionNumber' => 'DESC'])
            ->willReturn([$version2, $version1]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $schemaValidator = $this->createMock(FormSchemaValidatorService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formVersionService = new FormVersionService(
            $formVersionRepository,
            $entityManager,
            $schemaValidator,
            $logger
        );

        $result = $formVersionService->getVersionsByForm($form);

        $this->assertCount(2, $result);
        $this->assertEquals(2, $result[0]->versionNumber); // Ordre décroissant
        $this->assertEquals(1, $result[1]->versionNumber);
    }

    /**
     * @throws Exception
     */
    public function testRestoreVersionSuccess(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());

        $versionToRestore = new FormVersion();
        $versionToRestore->setId(Uuid::v4());
        $versionToRestore->setVersionNumber(1);
        $versionToRestore->setSchema([
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => 'Old Field',
                    'required' => false,
                ],
            ],
        ]);

        $latestVersion = new FormVersion();
        $latestVersion->setVersionNumber(3);

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findOneBy')
            ->willReturnMap([
                [['form' => $form, 'versionNumber' => 1], null, $versionToRestore],
                [['form' => $form], ['versionNumber' => 'DESC'], $latestVersion],
            ]);
        $formVersionRepository->method('findBy')
            ->willReturn([$latestVersion]);
        $formVersionRepository->method('count')->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->atLeastOnce())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $schemaValidator = $this->createMock(FormSchemaValidatorService::class);
        $schemaValidator->expects($this->once())->method('validateSchema');

        $logger = $this->createMock(LoggerInterface::class);

        $formVersionService = new FormVersionService(
            $formVersionRepository,
            $entityManager,
            $schemaValidator,
            $logger
        );

        $result = $formVersionService->restoreVersion($form, 1);

        $this->assertEquals(4, $result->versionNumber); // Nouvelle version créée
        $this->assertEquals($versionToRestore->getSchema(), $result->schema);
    }

    /**
     * @throws Exception
     */
    public function testRestoreVersionNotFound(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findOneBy')
            ->with(['form' => $form, 'versionNumber' => 99])
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $schemaValidator = $this->createMock(FormSchemaValidatorService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formVersionService = new FormVersionService(
            $formVersionRepository,
            $entityManager,
            $schemaValidator,
            $logger
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Version non trouvée');

        $formVersionService->restoreVersion($form, 99);
    }

    /**
     * @throws Exception
     */
    public function testDeleteVersionSuccess(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());

        $versionToDelete = new FormVersion();
        $versionToDelete->setVersionNumber(1);

        $latestVersion = new FormVersion();
        $latestVersion->setVersionNumber(2);

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findOneBy')
            ->willReturnMap([
                [['form' => $form, 'versionNumber' => 1], null, $versionToDelete],
                [['form' => $form], ['versionNumber' => 'DESC'], $latestVersion],
            ]);
        $formVersionRepository->method('count')
            ->with(['form' => $form])
            ->willReturn(2); // Plus d'une version

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($versionToDelete);
        $entityManager->expects($this->once())->method('flush');

        $schemaValidator = $this->createMock(FormSchemaValidatorService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formVersionService = new FormVersionService(
            $formVersionRepository,
            $entityManager,
            $schemaValidator,
            $logger
        );

        $formVersionService->deleteVersion($form, 1);
    }

    /**
     * @throws Exception
     */
    public function testDeleteLastVersionFails(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());

        $lastVersion = new FormVersion();
        $lastVersion->setVersionNumber(1);

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findOneBy')
            ->with(['form' => $form, 'versionNumber' => 1])
            ->willReturn($lastVersion);
        $formVersionRepository->method('count')
            ->with(['form' => $form])
            ->willReturn(1); // Une seule version

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $schemaValidator = $this->createMock(FormSchemaValidatorService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formVersionService = new FormVersionService(
            $formVersionRepository,
            $entityManager,
            $schemaValidator,
            $logger
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Impossible de supprimer la dernière version du formulaire');

        $formVersionService->deleteVersion($form, 1);
    }

    /**
     * @throws Exception
     */
    public function testDeleteMostRecentVersionFails(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());

        $versionToDelete = new FormVersion();
        $versionToDelete->setVersionNumber(2);

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findOneBy')
            ->willReturnMap([
                [['form' => $form, 'versionNumber' => 2], null, $versionToDelete],
                [['form' => $form], ['versionNumber' => 'DESC'], $versionToDelete], // C'est la plus récente
            ]);
        $formVersionRepository->method('count')
            ->with(['form' => $form])
            ->willReturn(2);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $schemaValidator = $this->createMock(FormSchemaValidatorService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formVersionService = new FormVersionService(
            $formVersionRepository,
            $entityManager,
            $schemaValidator,
            $logger
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Impossible de supprimer la version la plus récente');

        $formVersionService->deleteVersion($form, 2);
    }
}
