<?php

namespace App\Tests\Service;

use App\Dto\CreateFormDto;
use App\Dto\UpdateFormDto;
use App\Entity\Form;
use App\Entity\User;
use App\Repository\FormRepository;
use App\Repository\FormVersionRepository;
use App\Service\AuthorizationService;
use App\Service\FormService;
use App\Service\FormVersionService;
use App\Service\QuotaService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

class FormServiceTest extends TestCase
{
    /**
     * @throws Exception
     * @throws \Exception
     */
    public function testCreateFormSuccess(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail('test@example.com');

        $dto = new CreateFormDto(
            title: 'Test Form',
            description: 'Test Description for validation',
            status: 'DRAFT',
            schema: []
        );

        $formRepository = $this->createMock(FormRepository::class);
        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $formVersionService = $this->createMock(FormVersionService::class);
        $authorizationService = $this->createMock(AuthorizationService::class);
        $quotaService = $this->createMock(QuotaService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $quotaService->expects($this->once())->method('enforceQuotaLimit')->with($user, 'create_form');
        $quotaService->expects($this->once())->method('calculateCurrentQuotas')->with($user);

        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $formService = new FormService(
            $formRepository,
            $formVersionRepository,
            $entityManager,
            $formVersionService,
            $authorizationService,
            $quotaService,
            $logger
        );

        $result = $formService->createForm($dto, $user);

        $this->assertEquals('Test Form', $result->title);
        $this->assertEquals('Test Description for validation', $result->description);
        $this->assertEquals('DRAFT', $result->status);
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function testCreateFormWithSchema(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail('test@example.com');

        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                ],
            ],
        ];

        $dto = new CreateFormDto(
            title: 'Test Form',
            description: 'Test Description for validation',
            status: 'DRAFT',
            schema: $schema
        );

        $formRepository = $this->createMock(FormRepository::class);
        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $formVersionService = $this->createMock(FormVersionService::class);
        $authorizationService = $this->createMock(AuthorizationService::class);
        $quotaService = $this->createMock(QuotaService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $quotaService->expects($this->once())->method('enforceQuotaLimit')->with($user, 'create_form');
        $quotaService->expects($this->once())->method('calculateCurrentQuotas')->with($user);

        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $formVersionService->expects($this->once())
            ->method('createVersion')
            ->with($this->isInstanceOf(Form::class), $schema);

        $formService = new FormService(
            $formRepository,
            $formVersionRepository,
            $entityManager,
            $formVersionService,
            $authorizationService,
            $quotaService,
            $logger
        );

        $result = $formService->createForm($dto, $user);

        $this->assertEquals('Test Form', $result->title);
    }

    /**
     * @throws Exception
     */
    public function testGetFormByIdSuccess(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail('test@example.com');

        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setTitle('Test Form');
        $form->setDescription('Test Description');
        $form->setStatus('DRAFT');
        $form->setUser($user);

        $formRepository = $this->createMock(FormRepository::class);
        $formRepository->method('find')->willReturn($form);

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findBy')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $formVersionService = $this->createMock(FormVersionService::class);

        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService->method('canAccessForm')->willReturn(true);

        $quotaService = $this->createMock(QuotaService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formService = new FormService(
            $formRepository,
            $formVersionRepository,
            $entityManager,
            $formVersionService,
            $authorizationService,
            $quotaService,
            $logger
        );

        $result = $formService->getFormById($form->getId(), $user);

        $this->assertEquals($form->getId(), $result->id);
        $this->assertEquals('Test Form', $result->title);
    }

    /**
     * @throws Exception
     */
    public function testGetFormByIdNotFound(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());

        $formRepository = $this->createMock(FormRepository::class);
        $formRepository->method('find')->willReturn(null);

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $formVersionService = $this->createMock(FormVersionService::class);
        $authorizationService = $this->createMock(AuthorizationService::class);
        $quotaService = $this->createMock(QuotaService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formService = new FormService(
            $formRepository,
            $formVersionRepository,
            $entityManager,
            $formVersionService,
            $authorizationService,
            $quotaService,
            $logger
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Formulaire non trouvé');

        $formService->getFormById(Uuid::v4(), $user);
    }

    /**
     * @throws Exception
     */
    public function testGetFormByIdAccessDenied(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());

        $form = new Form();
        $form->setId(Uuid::v4());

        $formRepository = $this->createMock(FormRepository::class);
        $formRepository->method('find')->willReturn($form);

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $formVersionService = $this->createMock(FormVersionService::class);

        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService->method('canAccessForm')->willReturn(false);

        $quotaService = $this->createMock(QuotaService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formService = new FormService(
            $formRepository,
            $formVersionRepository,
            $entityManager,
            $formVersionService,
            $authorizationService,
            $quotaService,
            $logger
        );

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Accès refusé à ce formulaire');

        $formService->getFormById($form->getId(), $user);
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function testUpdateFormSuccess(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());

        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setTitle('Original Title');
        $form->setDescription('Original Description');
        $form->setStatus('DRAFT');

        $dto = new UpdateFormDto(
            title: 'Updated Title',
            description: 'Updated Description for validation',
            status: 'PUBLISHED'
        );

        $formRepository = $this->createMock(FormRepository::class);
        $formRepository->method('find')->willReturn($form);

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findBy')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $formVersionService = $this->createMock(FormVersionService::class);

        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService->method('canModifyForm')->willReturn(true);

        $quotaService = $this->createMock(QuotaService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formService = new FormService(
            $formRepository,
            $formVersionRepository,
            $entityManager,
            $formVersionService,
            $authorizationService,
            $quotaService,
            $logger
        );

        $result = $formService->updateForm($form->getId(), $dto, $user);

        $this->assertEquals('Updated Title', $result->title);
        $this->assertEquals('Updated Description for validation', $result->description);
        $this->assertEquals('PUBLISHED', $result->status);
        $this->assertNotNull($result->publishedAt);
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function testUpdateFormWithSchema(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());

        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setTitle('Original Title');
        $form->setDescription('Original Description');
        $form->setStatus('DRAFT');

        $newSchema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'email',
                    'label' => 'Email',
                    'required' => true,
                ],
            ],
        ];

        $dto = new UpdateFormDto(
            title: 'Updated Title',
            schema: $newSchema
        );

        $formRepository = $this->createMock(FormRepository::class);
        $formRepository->method('find')->willReturn($form);

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findBy')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $formVersionService = $this->createMock(FormVersionService::class);
        $formVersionService->expects($this->once())
            ->method('createVersion')
            ->with($form, $newSchema);

        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService->method('canModifyForm')->willReturn(true);

        $quotaService = $this->createMock(QuotaService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formService = new FormService(
            $formRepository,
            $formVersionRepository,
            $entityManager,
            $formVersionService,
            $authorizationService,
            $quotaService,
            $logger
        );

        $result = $formService->updateForm($form->getId(), $dto, $user);

        $this->assertEquals('Updated Title', $result->title);
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function testDeleteFormSuccess(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());

        $form = new Form();
        $form->setId(Uuid::v4());

        $formRepository = $this->createMock(FormRepository::class);
        $formRepository->method('find')->willReturn($form);
        $formRepository->expects($this->once())->method('remove')->with($form, true);

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $formVersionService = $this->createMock(FormVersionService::class);

        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService->method('canModifyForm')->willReturn(true);

        $quotaService = $this->createMock(QuotaService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formService = new FormService(
            $formRepository,
            $formVersionRepository,
            $entityManager,
            $formVersionService,
            $authorizationService,
            $quotaService,
            $logger
        );

        $formService->deleteForm($form->getId(), $user);
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function testPublishFormSuccess(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());

        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setTitle('Test Form');
        $form->setDescription('Test Description');
        $form->setStatus('DRAFT');

        $formRepository = $this->createMock(FormRepository::class);
        $formRepository->method('find')->willReturn($form);

        $formVersionRepository = $this->createMock(FormVersionRepository::class);
        $formVersionRepository->method('findBy')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $formVersionService = $this->createMock(FormVersionService::class);

        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService->method('canModifyForm')->willReturn(true);

        $quotaService = $this->createMock(QuotaService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formService = new FormService(
            $formRepository,
            $formVersionRepository,
            $entityManager,
            $formVersionService,
            $authorizationService,
            $quotaService,
            $logger
        );

        $result = $formService->publishForm($form->getId(), $user);

        $this->assertEquals('PUBLISHED', $result->status);
        $this->assertNotNull($result->publishedAt);
    }
}
