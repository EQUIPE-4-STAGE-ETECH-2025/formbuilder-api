<?php

namespace App\Service;

use App\Dto\CreateFormDto;
use App\Dto\FormFieldDto;
use App\Dto\FormResponseDto;
use App\Dto\FormVersionDto;
use App\Dto\UpdateFormDto;
use App\Entity\Form;
use App\Entity\FormField;
use App\Entity\FormVersion;
use App\Entity\User;
use App\Repository\FormRepository;
use App\Repository\FormVersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

class FormService
{
    public function __construct(
        private FormRepository $formRepository,
        private FormVersionRepository $formVersionRepository,
        private EntityManagerInterface $entityManager,
        private FormVersionService $formVersionService,
        private AuthorizationService $authorizationService,
        private readonly QuotaService $quotaService,
        private LoggerInterface $logger
    ) {
    }

    public function createForm(CreateFormDto $dto, User $user): FormResponseDto
    {
        try {
            $this->quotaService->enforceQuotaLimit($user, 'create_form');

            $this->logger->info('Création d\'un nouveau formulaire', [
                'user_id' => $user->getId(),
                'title' => $dto->title,
            ]);

            $form = new Form();
            $form->setId(Uuid::v4()->toRfc4122());
            $form->setTitle($dto->title);
            $form->setDescription($dto->description);
            $form->setStatus($dto->status);
            $form->setUser($user);

            if ($dto->status === 'PUBLISHED') {
                $form->setPublishedAt(new \DateTimeImmutable());
            }

            $this->entityManager->persist($form);
            $this->entityManager->flush();

            // Créer la première version si un schéma est fourni
            if (! empty($dto->schema)) {
                $this->formVersionService->createVersion($form, $dto->schema);
            }

            $this->logger->info('Formulaire créé avec succès', [
                'form_id' => $form->getId(),
                'user_id' => $user->getId(),
            ]);

            $this->quotaService->calculateCurrentQuotas($user);

            return $this->mapToDto($form);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la création du formulaire', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return FormResponseDto[]
     */
    public function getAllForms(User $user, array $filters = [], int $page = 1, int $limit = 20): array
    {
        // Utiliser une requête SQL optimisée avec comptage direct des submissions
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('f', 'COUNT(s.id) as submissionsCount')
           ->from(Form::class, 'f')
           ->leftJoin('f.submissions', 's')
           ->where('f.user = :user')
           ->setParameter('user', $user)
           ->groupBy('f.id')
           ->orderBy('f.updatedAt', 'DESC')
           ->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        // Appliquer les filtres
        if (isset($filters['status'])) {
            $qb->andWhere('f.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $qb->andWhere('f.title LIKE :search OR f.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        $results = $qb->getQuery()->getResult();

        $forms = [];
        foreach ($results as $result) {
            $form = $result[0]; // L'entité Form
            $submissionsCount = (int) $result['submissionsCount'];
            // Créer le DTO avec le comptage déjà récupéré
            $forms[] = $this->mapToDtoWithCount($form, $submissionsCount);
        }

        return $forms;
    }

    /**
     * Récupère les formulaires avec le total en une seule opération
     * @param array<string, mixed> $filters
     * @return array{forms: FormResponseDto[], total: int}
     */
    public function getAllFormsWithTotal(User $user, array $filters = [], int $page = 1, int $limit = 10): array
    {
        // Construire la requête de base
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('f', 'COUNT(s.id) as submissionsCount')
           ->from(Form::class, 'f')
           ->leftJoin('f.submissions', 's')
           ->where('f.user = :user')
           ->setParameter('user', $user)
           ->groupBy('f.id');

        // Appliquer les filtres
        if (isset($filters['status'])) {
            $qb->andWhere('f.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $qb->andWhere('f.title LIKE :search OR f.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Requête pour le total (sans pagination)
        $totalQb = clone $qb;
        $total = count($totalQb->select('f.id')->getQuery()->getResult());

        // Appliquer la pagination et le tri
        $qb->orderBy('f.updatedAt', 'DESC')
           ->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $results = $qb->getQuery()->getResult();

        $forms = [];
        foreach ($results as $result) {
            $form = $result[0]; // L'entité Form
            $submissionsCount = (int) $result['submissionsCount'];
            $forms[] = $this->mapToDtoWithCount($form, $submissionsCount);
        }

        return [
            'forms' => $forms,
            'total' => $total,
        ];
    }

    public function getFormById(string $id, User $user): FormResponseDto
    {
        $form = $this->formRepository->find($id);

        if (! $form) {
            throw new \InvalidArgumentException('Formulaire non trouvé');
        }

        if (! $this->authorizationService->canAccessForm($user, $form)) {
            throw new AccessDeniedException('Accès refusé à ce formulaire');
        }

        return $this->mapToDto($form, true);
    }

    public function updateForm(string $id, UpdateFormDto $dto, User $user): FormResponseDto
    {
        $form = $this->formRepository->find($id);

        if (! $form) {
            throw new \InvalidArgumentException('Formulaire non trouvé');
        }

        if (! $this->authorizationService->canModifyForm($user, $form)) {
            throw new AccessDeniedException('Vous n\'avez pas les permissions pour modifier ce formulaire');
        }

        try {
            $this->logger->info('Mise à jour du formulaire', [
                'form_id' => $id,
                'user_id' => $user->getId(),
            ]);

            if ($dto->title !== null) {
                $form->setTitle($dto->title);
            }

            if ($dto->description !== null) {
                $form->setDescription($dto->description);
            }

            if ($dto->status !== null) {
                $form->setStatus($dto->status);
                if ($dto->status === 'PUBLISHED' && ! $form->getPublishedAt()) {
                    $form->setPublishedAt(new \DateTimeImmutable());
                }
            }

            $form->setUpdatedAt(new \DateTimeImmutable());

            // Créer une nouvelle version si le schéma a été modifié
            if ($dto->schema !== null) {
                $this->formVersionService->createVersion($form, $dto->schema);
            }

            $this->entityManager->flush();

            $this->logger->info('Formulaire mis à jour avec succès', [
                'form_id' => $id,
                'user_id' => $user->getId(),
            ]);

            return $this->mapToDto($form);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour du formulaire', [
                'error' => $e->getMessage(),
                'form_id' => $id,
                'user_id' => $user->getId(),
            ]);

            throw $e;
        }
    }

    public function deleteForm(string $id, User $user): void
    {
        $form = $this->formRepository->find($id);

        if (! $form) {
            throw new \InvalidArgumentException('Formulaire non trouvé');
        }

        if (! $this->authorizationService->canModifyForm($user, $form)) {
            throw new AccessDeniedException('Vous n\'avez pas les permissions pour supprimer ce formulaire');
        }

        try {
            $this->logger->info('Suppression du formulaire', [
                'form_id' => $id,
                'user_id' => $user->getId(),
            ]);

            $this->formRepository->remove($form, true);

            $this->logger->info('Formulaire supprimé avec succès', [
                'form_id' => $id,
                'user_id' => $user->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la suppression du formulaire', [
                'error' => $e->getMessage(),
                'form_id' => $id,
                'user_id' => $user->getId(),
            ]);

            throw $e;
        }
    }

    public function publishForm(string $id, User $user): FormResponseDto
    {
        $form = $this->formRepository->find($id);

        if (! $form) {
            throw new \InvalidArgumentException('Formulaire non trouvé');
        }

        if (! $this->authorizationService->canModifyForm($user, $form)) {
            throw new AccessDeniedException('Vous n\'avez pas les permissions pour publier ce formulaire');
        }

        try {
            $this->logger->info('Publication du formulaire', [
                'form_id' => $id,
                'user_id' => $user->getId(),
            ]);

            $form->setStatus('PUBLISHED');
            $form->setPublishedAt(new \DateTimeImmutable());
            $form->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            $this->logger->info('Formulaire publié avec succès', [
                'form_id' => $id,
                'user_id' => $user->getId(),
            ]);

            return $this->mapToDto($form);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la publication du formulaire', [
                'error' => $e->getMessage(),
                'form_id' => $id,
                'user_id' => $user->getId(),
            ]);

            throw $e;
        }
    }

    /**
     * Formater la réponse d'un formulaire pour l'accès public
     * @return array<string, mixed>
     */
    public function formatFormResponse(Form $form): array
    {
        $dto = $this->mapToDto($form, true);

        // Convertir le DTO en tableau associatif
        return [
            'id' => $dto->id,
            'title' => $dto->title,
            'description' => $dto->description,
            'status' => $dto->status,
            'publishedAt' => $dto->publishedAt?->format('c'),
            'createdAt' => $dto->createdAt->format('c'),
            'updatedAt' => $dto->updatedAt->format('c'),
            'schema' => $dto->schema,
            'submissionsCount' => $dto->submissionsCount,
            'currentVersion' => $dto->currentVersion ? [
                'id' => $dto->currentVersion->id,
                'versionNumber' => $dto->currentVersion->versionNumber,
                'schema' => $dto->currentVersion->schema,
                'createdAt' => $dto->currentVersion->createdAt->format('c'),
                'fields' => $dto->currentVersion->fields,
            ] : null,
            'versions' => array_map(fn ($version) => [
                'id' => $version->id,
                'versionNumber' => $version->versionNumber,
                'schema' => $version->schema,
                'createdAt' => $version->createdAt->format('c'),
                'fields' => $version->fields,
            ], $dto->versions),
        ];
    }

    private function mapToDto(Form $form, bool $includeVersions = false): FormResponseDto
    {
        // Utiliser une requête directe au lieu de lazy loading
        $submissionsCount = $this->entityManager->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM submission WHERE form_id = ?', [$form->getId()]);

        $versions = [];
        $currentVersion = null;

        if ($includeVersions) {
            // Utiliser une requête avec JOIN pour éviter les requêtes multiples
            $formVersions = $this->formVersionRepository
                ->createQueryBuilder('fv')
                ->leftJoin('fv.formFields', 'ff')
                ->addSelect('ff')
                ->where('fv.form = :form')
                ->setParameter('form', $form)
                ->orderBy('fv.versionNumber', 'DESC')
                ->getQuery()
                ->getResult();

            $versions = array_map(fn (FormVersion $version) => $this->mapVersionToDto($version), $formVersions ?? []);
            $currentVersion = ! empty($versions) ? $versions[0] : null;
        }

        // Validation des propriétés requises
        $formId = $form->getId();
        $formTitle = $form->getTitle();
        $formDescription = $form->getDescription();
        $formStatus = $form->getStatus();
        $formCreatedAt = $form->getCreatedAt();
        $formUpdatedAt = $form->getUpdatedAt();

        if ($formId === null || $formTitle === null || $formDescription === null ||
            $formStatus === null || $formCreatedAt === null || $formUpdatedAt === null) {
            throw new \InvalidArgumentException('Le formulaire a des propriétés manquantes');
        }

        return new FormResponseDto(
            id: $formId,
            title: $formTitle,
            description: $formDescription,
            status: $formStatus,
            publishedAt: $form->getPublishedAt(),
            createdAt: $formCreatedAt,
            updatedAt: $formUpdatedAt,
            schema: $currentVersion?->schema ?? [],
            submissionsCount: $submissionsCount,
            currentVersion: $currentVersion,
            versions: $versions
        );
    }

    private function mapVersionToDto(FormVersion $version): FormVersionDto
    {
        $fields = array_map(
            function (FormField $field): FormFieldDto {
                $fieldId = $field->getId();
                $fieldLabel = $field->getLabel();
                $fieldType = $field->getType();
                $fieldIsRequired = $field->isRequired();
                $fieldPosition = $field->getPosition();

                if ($fieldId === null || $fieldLabel === null || $fieldType === null ||
                    $fieldIsRequired === null || $fieldPosition === null) {
                    throw new \InvalidArgumentException('Le champ a des propriétés manquantes');
                }

                return new FormFieldDto(
                    id: $fieldId,
                    label: $fieldLabel,
                    type: $fieldType,
                    isRequired: $fieldIsRequired,
                    options: $field->getOptions(),
                    position: $fieldPosition,
                    validationRules: $field->getValidationRules()
                );
            },
            $version->getFormFields()->toArray()
        );

        // Validation des propriétés de version requises
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
            fields: $fields
        );
    }

    /**
     * Méthode optimisée qui utilise le comptage pré-calculé
     */
    private function mapToDtoWithCount(Form $form, int $submissionsCount, bool $includeVersions = false): FormResponseDto
    {
        $versions = [];
        $currentVersion = null;

        if ($includeVersions) {
            // Utiliser une requête avec JOIN pour éviter les requêtes multiples
            $formVersions = $this->formVersionRepository
                ->createQueryBuilder('fv')
                ->leftJoin('fv.formFields', 'ff')
                ->addSelect('ff')
                ->where('fv.form = :form')
                ->setParameter('form', $form)
                ->orderBy('fv.versionNumber', 'DESC')
                ->getQuery()
                ->getResult();

            $versions = array_map(fn (FormVersion $version) => $this->mapVersionToDto($version), $formVersions ?? []);
            $currentVersion = ! empty($versions) ? $versions[0] : null;
        }

        // Validation des propriétés requises
        $formId = $form->getId();
        $formTitle = $form->getTitle();
        $formDescription = $form->getDescription();
        $formStatus = $form->getStatus();
        $formCreatedAt = $form->getCreatedAt();
        $formUpdatedAt = $form->getUpdatedAt();

        if ($formId === null || $formTitle === null || $formDescription === null ||
            $formStatus === null || $formCreatedAt === null || $formUpdatedAt === null) {
            throw new \InvalidArgumentException('Le formulaire a des propriétés manquantes');
        }

        return new FormResponseDto(
            id: $formId,
            title: $formTitle,
            description: $formDescription,
            status: $formStatus,
            publishedAt: $form->getPublishedAt(),
            createdAt: $formCreatedAt,
            updatedAt: $formUpdatedAt,
            schema: $currentVersion?->schema ?? [],
            submissionsCount: $submissionsCount, // Utiliser le comptage pré-calculé
            currentVersion: $currentVersion,
            versions: $versions
        );
    }
}
