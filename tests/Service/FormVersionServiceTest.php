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

        // Créer 11 versions existantes (pour que avec la nouvelle ça fasse 12 > 10)
        $oldVersions = [];
        for ($i = 1; $i <= 11; $i++) {
            $version = new FormVersion();
            $version->setId(Uuid::v4());
            $version->setVersionNumber($i);
            $version->setForm($form);
            $oldVersions[] = $version;
        }

        // Inverser pour simuler l'ordre DESC
        $oldVersionsDesc = array_reverse($oldVersions);

        $latestVersion = $oldVersions[10]; // Version 11

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findOneBy')
            ->with(['form' => $form], ['versionNumber' => 'DESC'])
            ->willReturn($latestVersion);

        // Simuler que après création, nous avons 12 versions (11 + 1 nouvelle)
        $allVersionsAfterCreation = $oldVersionsDesc;
        // Ajouter la nouvelle version au début (ordre DESC)
        $newVersion = new FormVersion();
        $newVersion->setVersionNumber(12);
        array_unshift($allVersionsAfterCreation, $newVersion);

        $formVersionRepository->method('findBy')
            ->with(['form' => $form], ['versionNumber' => 'DESC'])
            ->willReturn($allVersionsAfterCreation);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('beginTransaction');
        $entityManager->expects($this->once())->method('commit');
        $entityManager->expects($this->exactly(2))->method('remove'); // Supprimer 2 anciennes versions (12 - 10 = 2)
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

        $result = $formVersionService->createVersion($form, ['fields' => []]);

        $this->assertEquals(12, $result->versionNumber);
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
        $entityManager->expects($this->once())->method('beginTransaction');
        $entityManager->expects($this->atLeastOnce())->method('persist');
        $entityManager->expects($this->once())->method('remove')->with($versionToRestore);
        $entityManager->expects($this->exactly(2))->method('flush'); // Une fois dans createVersion, une fois dans restoreVersion
        $entityManager->expects($this->once())->method('commit');

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

    /**
     * @throws Exception
     */
    public function testCleanupOldVersionsWhenMaxReached(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());

        // Créer 11 versions existantes (dépasse la limite de 10)
        $versions = [];
        for ($i = 1; $i <= 11; $i++) {
            $version = new FormVersion();
            $version->setId(Uuid::v4());
            $version->setVersionNumber($i);
            $version->setForm($form);
            $versions[] = $version;
        }

        // Inverser l'ordre pour simuler le tri DESC
        $versionsDesc = array_reverse($versions);

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findOneBy')
            ->willReturnMap([
                [['form' => $form], ['versionNumber' => 'DESC'], $versions[10]], // Version 11
            ]);
        $formVersionRepository->method('findBy')
            ->with(['form' => $form], ['versionNumber' => 'DESC'])
            ->willReturn($versionsDesc);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        // Vérifier la gestion de transaction
        $entityManager->expects($this->once())->method('beginTransaction');
        $entityManager->expects($this->once())->method('commit');

        // Vérifier que la version la plus ancienne (version 1) est supprimée
        $entityManager->expects($this->once())
            ->method('remove')
            ->with($versions[0]); // Version 1

        $entityManager->expects($this->once())
            ->method('flush'); // Une seule fois dans la transaction

        $schemaValidator = $this->createMock(FormSchemaValidatorService::class);
        $schemaValidator->expects($this->once())->method('validateSchema');

        $logger = $this->createMock(LoggerInterface::class);

        // Vérifier que les logs de nettoyage sont appelés
        $logger->expects($this->atLeastOnce())
            ->method('info');

        $formVersionService = new FormVersionService(
            $formVersionRepository,
            $entityManager,
            $schemaValidator,
            $logger
        );

        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => 'Test Field',
                    'required' => true,
                ],
            ],
        ];

        $result = $formVersionService->createVersion($form, $schema);

        $this->assertEquals(12, $result->versionNumber); // Nouvelle version créée
        $this->assertEquals($schema, $result->schema);
    }

    /**
     * @throws Exception
     */
    public function testNoCleanupWhenUnderMaxVersions(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());

        // Créer seulement 5 versions existantes (sous la limite de 10)
        $versions = [];
        for ($i = 1; $i <= 5; $i++) {
            $version = new FormVersion();
            $version->setId(Uuid::v4());
            $version->setVersionNumber($i);
            $version->setForm($form);
            $versions[] = $version;
        }

        $versionsDesc = array_reverse($versions);

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findOneBy')
            ->willReturnMap([
                [['form' => $form], ['versionNumber' => 'DESC'], $versions[4]], // Version 5
            ]);
        $formVersionRepository->method('findBy')
            ->with(['form' => $form], ['versionNumber' => 'DESC'])
            ->willReturn($versionsDesc);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        // Vérifier la gestion de transaction
        $entityManager->expects($this->once())->method('beginTransaction');
        $entityManager->expects($this->once())->method('commit');

        // Vérifier qu'aucune version n'est supprimée lors du cleanup
        $entityManager->expects($this->never())->method('remove');

        $entityManager->expects($this->once())->method('flush'); // Une fois dans la transaction

        $schemaValidator = $this->createMock(FormSchemaValidatorService::class);
        $schemaValidator->expects($this->once())->method('validateSchema');

        $logger = $this->createMock(LoggerInterface::class);

        // Vérifier que les logs sont appelés
        $logger->expects($this->atLeastOnce())
            ->method('info');

        $formVersionService = new FormVersionService(
            $formVersionRepository,
            $entityManager,
            $schemaValidator,
            $logger
        );

        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => 'Test Field',
                    'required' => true,
                ],
            ],
        ];

        $result = $formVersionService->createVersion($form, $schema);

        $this->assertEquals(6, $result->versionNumber); // Nouvelle version créée
        $this->assertEquals($schema, $result->schema);
    }
}
