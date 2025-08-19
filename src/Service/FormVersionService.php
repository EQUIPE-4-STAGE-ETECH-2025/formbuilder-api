<?php

namespace App\Service;

use App\Dto\FormVersionDto;
use App\Entity\Form;
use App\Entity\FormField;
use App\Entity\FormVersion;
use App\Repository\FormVersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class FormVersionService
{
    private const MAX_VERSIONS = 10;

    public function __construct(
        private FormVersionRepository $formVersionRepository,
        private EntityManagerInterface $entityManager,
        private FormSchemaValidatorService $schemaValidator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $schema
     */
    public function createVersion(Form $form, array $schema): FormVersionDto
    {
        try {
            $this->logger->info('Création d\'une nouvelle version de formulaire', [
                'form_id' => $form->getId(),
            ]);

            // Valider le schéma
            $this->schemaValidator->validateSchema($schema);

            // Obtenir le prochain numéro de version
            $nextVersionNumber = $this->getNextVersionNumber($form);

            // Supprimer les anciennes versions si nécessaire
            $this->cleanupOldVersions($form);

            $version = new FormVersion();
            $version->setId(Uuid::v4()->toRfc4122());
            $version->setForm($form);
            $version->setVersionNumber($nextVersionNumber);
            $version->setSchema($schema);

            $this->entityManager->persist($version);

            // Créer les champs de formulaire à partir du schéma
            $this->createFormFieldsFromSchema($version, $schema);

            $this->entityManager->flush();

            $this->logger->info('Version de formulaire créée avec succès', [
                'form_id' => $form->getId(),
                'version_number' => $nextVersionNumber,
            ]);

            return $this->mapToDto($version);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la création de la version', [
                'error' => $e->getMessage(),
                'form_id' => $form->getId(),
            ]);

            throw $e;
        }
    }

    /**
     * @return FormVersionDto[]
     */
    public function getVersionsByForm(Form $form): array
    {
        $versions = $this->formVersionRepository->findBy(
            ['form' => $form],
            ['versionNumber' => 'DESC']
        );

        return array_map(fn (FormVersion $version) => $this->mapToDto($version), $versions);
    }

    public function restoreVersion(Form $form, int $versionNumber): FormVersionDto
    {
        $versionToRestore = $this->formVersionRepository->findOneBy([
            'form' => $form,
            'versionNumber' => $versionNumber,
        ]);

        if (! $versionToRestore) {
            throw new \InvalidArgumentException('Version non trouvée');
        }

        try {
            $this->logger->info('Restauration de la version', [
                'form_id' => $form->getId(),
                'version_number' => $versionNumber,
            ]);

            // Créer une nouvelle version basée sur l'ancienne
            $restoredVersion = $this->createVersion($form, $versionToRestore->getSchema());

            $this->logger->info('Version restaurée avec succès', [
                'form_id' => $form->getId(),
                'restored_from_version' => $versionNumber,
                'new_version_number' => $restoredVersion->versionNumber,
            ]);

            return $restoredVersion;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la restauration de la version', [
                'error' => $e->getMessage(),
                'form_id' => $form->getId(),
                'version_number' => $versionNumber,
            ]);

            throw $e;
        }
    }

    public function deleteVersion(Form $form, int $versionNumber): void
    {
        $version = $this->formVersionRepository->findOneBy([
            'form' => $form,
            'versionNumber' => $versionNumber,
        ]);

        if (! $version) {
            throw new \InvalidArgumentException('Version non trouvée');
        }

        // Empêcher la suppression de la dernière version
        $totalVersions = $this->formVersionRepository->count(['form' => $form]);
        if ($totalVersions <= 1) {
            throw new \InvalidArgumentException('Impossible de supprimer la dernière version du formulaire');
        }

        // Empêcher la suppression de la version la plus récente
        $latestVersion = $this->formVersionRepository->findOneBy(
            ['form' => $form],
            ['versionNumber' => 'DESC']
        );

        if ($latestVersion && $latestVersion->getVersionNumber() === $versionNumber) {
            throw new \InvalidArgumentException('Impossible de supprimer la version la plus récente');
        }

        try {
            $this->logger->info('Suppression de la version', [
                'form_id' => $form->getId(),
                'version_number' => $versionNumber,
            ]);

            $this->entityManager->remove($version);
            $this->entityManager->flush();

            $this->logger->info('Version supprimée avec succès', [
                'form_id' => $form->getId(),
                'version_number' => $versionNumber,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la suppression de la version', [
                'error' => $e->getMessage(),
                'form_id' => $form->getId(),
                'version_number' => $versionNumber,
            ]);

            throw $e;
        }
    }

    private function getNextVersionNumber(Form $form): int
    {
        $latestVersion = $this->formVersionRepository->findOneBy(
            ['form' => $form],
            ['versionNumber' => 'DESC']
        );

        return $latestVersion ? $latestVersion->getVersionNumber() + 1 : 1;
    }

    private function cleanupOldVersions(Form $form): void
    {
        $versions = $this->formVersionRepository->findBy(
            ['form' => $form],
            ['versionNumber' => 'DESC']
        );

        if (count($versions) >= self::MAX_VERSIONS) {
            $versionsToDelete = array_slice($versions, self::MAX_VERSIONS - 1);
            foreach ($versionsToDelete as $version) {
                $this->entityManager->remove($version);
            }
        }
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function createFormFieldsFromSchema(FormVersion $version, array $schema): void
    {
        if (! isset($schema['fields']) || ! is_array($schema['fields'])) {
            return;
        }

        $position = 1;
        foreach ($schema['fields'] as $fieldData) {
            if (! is_array($fieldData)) {
                continue;
            }

            $field = new FormField();
            $field->setId(Uuid::v4()->toRfc4122());
            $field->setFormVersion($version);
            $field->setLabel($fieldData['label'] ?? '');
            $field->setType($fieldData['type'] ?? 'text');
            $field->setIsRequired($fieldData['required'] ?? false);
            $field->setOptions($fieldData['options'] ?? []);
            $field->setPosition($position++);
            $field->setValidationRules($fieldData['validation'] ?? []);

            $this->entityManager->persist($field);
        }
    }

    private function mapToDto(FormVersion $version): FormVersionDto
    {
        // Validation des propriétés requises
        $versionId = $version->getId();
        $versionNumber = $version->getVersionNumber();
        $versionCreatedAt = $version->getCreatedAt();

        if ($versionId === null || $versionNumber === null || $versionCreatedAt === null) {
            throw new \InvalidArgumentException('La version a des propriétés manquantes');
        }

        return new FormVersionDto(
            id: $versionId,
            versionNumber: $versionNumber,
            schema: $version->getSchema(),
            createdAt: $versionCreatedAt,
            fields: []
        );
    }
}
