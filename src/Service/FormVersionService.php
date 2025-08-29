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
        // Démarrer une transaction pour assurer la cohérence
        $this->entityManager->beginTransaction();

        try {
            $this->logger->info('Création d\'une nouvelle version de formulaire', [
                'form_id' => $form->getId(),
            ]);

            // Valider le schéma
            $this->schemaValidator->validateSchema($schema);

            // Obtenir le prochain numéro de version
            $nextVersionNumber = $this->getNextVersionNumber($form);

            $version = new FormVersion();
            $version->setId(Uuid::v4()->toRfc4122());
            $version->setForm($form);
            $version->setVersionNumber($nextVersionNumber);
            $version->setSchema($schema);

            $this->entityManager->persist($version);

            // Créer les champs de formulaire à partir du schéma
            $this->createFormFieldsFromSchema($version, $schema);

            $this->entityManager->flush();

            // Supprimer les anciennes versions si nécessaire APRÈS la création réussie
            $this->cleanupOldVersions($form);

            // Valider la transaction
            $this->entityManager->commit();

            $this->logger->info('Version de formulaire créée avec succès', [
                'form_id' => $form->getId(),
                'version_number' => $nextVersionNumber,
            ]);

            return $this->mapToDto($version);
        } catch (\Exception $e) {
            // Annuler la transaction en cas d'erreur
            $this->entityManager->rollback();

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

            // Supprimer la version restaurée pour éviter la duplication
            try {
                $this->logger->info('Suppression de la version restaurée pour éviter la duplication', [
                    'form_id' => $form->getId(),
                    'version_to_delete' => $versionNumber,
                ]);

                $this->entityManager->remove($versionToRestore);
                $this->entityManager->flush();

                $this->logger->info('Version restaurée avec succès et version originale supprimée', [
                    'form_id' => $form->getId(),
                    'restored_from_version' => $versionNumber,
                    'new_version_number' => $restoredVersion->versionNumber,
                ]);
            } catch (\Exception $deleteException) {
                // La nouvelle version a été créée avec succès, mais la suppression a échoué
                // On log l'erreur mais on ne fait pas échouer l'opération complète
                $this->logger->warning('Erreur lors de la suppression de la version restaurée, mais la nouvelle version a été créée', [
                    'error' => $deleteException->getMessage(),
                    'form_id' => $form->getId(),
                    'version_number' => $versionNumber,
                    'new_version_number' => $restoredVersion->versionNumber,
                ]);
            }

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

        $versionCount = count($versions);

        if ($versionCount > self::MAX_VERSIONS) {
            $versionsToDelete = array_slice($versions, self::MAX_VERSIONS);
            
            $this->logger->info('Nettoyage automatique des anciennes versions', [
                'form_id' => $form->getId(),
                'total_versions' => $versionCount,
                'versions_to_delete' => count($versionsToDelete),
                'max_versions_allowed' => self::MAX_VERSIONS,
            ]);

            foreach ($versionsToDelete as $version) {
                $this->logger->debug('Suppression automatique de la version', [
                    'form_id' => $form->getId(),
                    'version_number' => $version->getVersionNumber(),
                    'version_id' => $version->getId(),
                ]);

                $this->entityManager->remove($version);
            }

            // Note: Pas de flush ici car nous sommes dans une transaction
            // Le flush sera fait lors du commit de la transaction

            $this->logger->info('Nettoyage automatique terminé', [
                'form_id' => $form->getId(),
                'versions_deleted' => count($versionsToDelete),
                'remaining_versions' => $versionCount - count($versionsToDelete),
            ]);
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
