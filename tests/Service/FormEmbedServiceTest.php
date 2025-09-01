<?php

namespace App\Tests\Service;

use App\Entity\Form;
use App\Entity\FormToken;
use App\Repository\FormTokenRepository;
use App\Service\FormEmbedService;
use App\Service\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Uid\Uuid;

class FormEmbedServiceTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testGenerateEmbedCodeSuccess(): void
    {

        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setTitle('Test Form');
        $form->setStatus('PUBLISHED');

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('generateToken')->willReturn('test-jwt-token');

        $formTokenRepository = $this->createMock(FormTokenRepository::class);
        $formTokenRepository->method('findOneBy')->willReturn(null); // Pas de token existant

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('frontend_url')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $jwtService,
            $formTokenRepository,
            $entityManager,
            $parameterBag,
            $logger
        );

        $result = $formEmbedService->generateEmbedCode($form);

        $this->assertEquals($form->getId(), $result->formId);
        $this->assertEquals('test-jwt-token', $result->token);
        $this->assertStringContainsString('<iframe', $result->embedCode);
        $this->assertStringContainsString('http://localhost:3000/embed/', $result->embedUrl);
        $this->assertStringContainsString($form->getId(), $result->embedUrl);
        $this->assertStringContainsString('token=test-jwt-token', $result->embedUrl);
    }

    /**
     * @throws Exception
     */
    public function testGenerateEmbedCodeWithCustomization(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setStatus('PUBLISHED');

        $customization = [
            'width' => '800px',
            'height' => '400px',
            'border' => '1px solid #ccc',
            'borderRadius' => '12px',
        ];

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('generateToken')->willReturn('test-jwt-token');

        $formTokenRepository = $this->createMock(FormTokenRepository::class);
        $formTokenRepository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('frontend_url')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $jwtService,
            $formTokenRepository,
            $entityManager,
            $parameterBag,
            $logger
        );

        $result = $formEmbedService->generateEmbedCode($form, $customization);

        $this->assertEquals($customization, $result->customization);
        $this->assertStringContainsString('width: 800px', $result->embedCode);
        $this->assertStringContainsString('height: 400px', $result->embedCode);
        $this->assertStringContainsString('border: 1px solid #ccc', $result->embedCode);
        $this->assertStringContainsString('border-radius: 12px', $result->embedCode);
    }

    /**
     * @throws Exception
     */
    public function testGenerateEmbedCodeWithExistingToken(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setStatus('PUBLISHED');

        $existingToken = new FormToken();
        $existingToken->setToken('existing-jwt-token');
        $existingToken->setExpiresAt(new \DateTimeImmutable('+6 months')); // Token valide

        $jwtService = $this->createMock(JwtService::class);

        $formTokenRepository = $this->createMock(FormTokenRepository::class);
        $formTokenRepository->method('findOneBy')->willReturn($existingToken);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist'); // Pas de nouveau token créé

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('frontend_url')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $jwtService,
            $formTokenRepository,
            $entityManager,
            $parameterBag,
            $logger
        );

        $result = $formEmbedService->generateEmbedCode($form);

        $this->assertEquals('existing-jwt-token', $result->token);
    }

    /**
     * @throws Exception
     */
    public function testGenerateEmbedCodeFormNotPublished(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setStatus('DRAFT'); // Pas publié

        $jwtService = $this->createMock(JwtService::class);
        $formTokenRepository = $this->createMock(FormTokenRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $jwtService,
            $formTokenRepository,
            $entityManager,
            $parameterBag,
            $logger
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le formulaire doit être publié pour générer un code d\'intégration');

        $formEmbedService->generateEmbedCode($form);
    }

    /**
     * @throws Exception
     */
    public function testValidateEmbedTokenSuccess(): void
    {
        $formId = Uuid::v4();
        $token = 'valid-jwt-token';

        $payload = (object) [
            'form_id' => $formId,
            'type' => 'embed',
            'exp' => (new \DateTimeImmutable('+1 hour'))->getTimestamp(),
        ];

        $formToken = new FormToken();
        $formToken->setToken($token);
        $formToken->setIsActive(true);
        $formToken->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->willReturn($payload);

        $formTokenRepository = $this->createMock(FormTokenRepository::class);
        $formTokenRepository->method('findOneBy')->willReturn($formToken);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $jwtService,
            $formTokenRepository,
            $entityManager,
            $parameterBag,
            $logger
        );

        $result = $formEmbedService->validateEmbedToken($token);

        $this->assertEquals($formId, $result);
    }

    /**
     * @throws Exception
     */
    public function testValidateEmbedTokenInvalid(): void
    {
        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')
            ->willThrowException(new \Exception('Token invalide'));

        $formTokenRepository = $this->createMock(FormTokenRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $jwtService,
            $formTokenRepository,
            $entityManager,
            $parameterBag,
            $logger
        );

        $result = $formEmbedService->validateEmbedToken('invalid-token');

        $this->assertNull($result);
    }

    /**
     * @throws Exception
     */
    public function testRevokeFormTokensSuccess(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());

        $token1 = new FormToken();
        $token1->setIsActive(true);
        $token1->setType('embed');

        $token2 = new FormToken();
        $token2->setIsActive(true);
        $token2->setType('embed');

        $jwtService = $this->createMock(JwtService::class);

        $formTokenRepository = $this->createMock(FormTokenRepository::class);
        $formTokenRepository->method('findBy')->willReturn([$token1, $token2]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $jwtService,
            $formTokenRepository,
            $entityManager,
            $parameterBag,
            $logger
        );

        $formEmbedService->revokeFormTokens($form);

        $this->assertFalse($token1->isActive());
        $this->assertFalse($token2->isActive());
    }

    /**
     * @throws Exception
     */
    public function testValidateEmbedTokenExpired(): void
    {
        $formId = Uuid::v4();
        $token = 'expired-token';

        $payload = (object) [
            'form_id' => $formId,
            'type' => 'embed',
        ];

        $expiredFormToken = new FormToken();
        $expiredFormToken->setToken($token);
        $expiredFormToken->setIsActive(true);
        $expiredFormToken->setExpiresAt(new \DateTimeImmutable('-1 hour')); // Expiré

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->willReturn($payload);

        $formTokenRepository = $this->createMock(FormTokenRepository::class);
        $formTokenRepository->method('findOneBy')->willReturn($expiredFormToken);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $jwtService,
            $formTokenRepository,
            $entityManager,
            $parameterBag,
            $logger
        );

        $result = $formEmbedService->validateEmbedToken($token);

        $this->assertNull($result);
    }
}
