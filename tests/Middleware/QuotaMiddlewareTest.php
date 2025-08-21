<?php

namespace App\Tests\Middleware;

use App\Entity\User;
use App\Middleware\QuotaMiddleware;
use App\Repository\UserRepository;
use App\Service\QuotaService;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

class QuotaMiddlewareTest extends TestCase
{
    private QuotaMiddleware $quotaMiddleware;
    private $quotaService;
    private $userRepository;
    private $tokenStorage;
    private $logger;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->quotaService = $this->createMock(QuotaService::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->quotaMiddleware = new QuotaMiddleware(
            $this->quotaService,
            $this->userRepository,
            $this->tokenStorage,
            $this->logger
        );
    }

    public function testOnKernelRequestIgnoresNonMainRequest(): void
    {
        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->quotaService
            ->expects($this->never())
            ->method('enforceQuotaLimit');

        $this->quotaMiddleware->onKernelRequest($event);
    }

    public function testOnKernelRequestIgnoresNonProtectedRoute(): void
    {
        $request = Request::create('/api/other/endpoint', 'GET');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->quotaService
            ->expects($this->never())
            ->method('enforceQuotaLimit');

        $this->quotaMiddleware->onKernelRequest($event);
    }

    public function testOnKernelRequestIgnoresWhenNoUser(): void
    {
        $request = Request::create('/api/forms', 'POST');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->willReturn(null);

        $this->quotaService
            ->expects($this->never())
            ->method('enforceQuotaLimit');

        $this->quotaMiddleware->onKernelRequest($event);
    }

    public function testOnKernelRequestAllowsActionWhenQuotaNotExceeded(): void
    {
        $user = $this->createUser();
        $request = Request::create('/api/forms', 'POST');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->setupAuthenticatedUser($user);

        $this->quotaService
            ->expects($this->once())
            ->method('enforceQuotaLimit')
            ->with($user, 'create_form', 1);

        $this->quotaMiddleware->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testOnKernelRequestBlocksActionWhenQuotaExceeded(): void
    {
        $user = $this->createUser();
        $request = Request::create('/api/forms', 'POST');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->setupAuthenticatedUser($user);

        $this->quotaService
            ->expects($this->once())
            ->method('enforceQuotaLimit')
            ->with($user, 'create_form', 1)
            ->willThrowException(new RuntimeException('Limite de formulaires atteinte'));

        $this->quotaMiddleware->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertFalse($content['success']);
        $this->assertEquals('Quota dépassé', $content['error']);
        $this->assertEquals('Limite de formulaires atteinte', $content['details']);
    }

    public function testOnKernelRequestHandlesSubmitFormRoute(): void
    {
        $user = $this->createUser();
        $request = Request::create('/api/forms/123/submit', 'POST');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->setupAuthenticatedUser($user);

        $this->quotaService
            ->expects($this->once())
            ->method('enforceQuotaLimit')
            ->with($user, 'submit_form', 1);

        $this->quotaMiddleware->onKernelRequest($event);
    }

    public function testOnKernelRequestHandlesFileUploadRoute(): void
    {
        $user = $this->createUser();
        $request = Request::create('/api/forms/123/files', 'POST');

        // Simuler un fichier uploadé
        $uploadedFile = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);
        $uploadedFile->method('isValid')->willReturn(true);
        $uploadedFile->method('getSize')->willReturn(2048000); // 2MB
        $request->files->set('file', $uploadedFile);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->setupAuthenticatedUser($user);

        $this->quotaService
            ->expects($this->once())
            ->method('enforceQuotaLimit')
            ->with($user, 'upload_file', 2); // 2MB converti

        $this->quotaMiddleware->onKernelRequest($event);
    }

    public function testOnKernelRequestLogsErrors(): void
    {
        $user = $this->createUser();
        $request = Request::create('/api/forms', 'POST');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->setupAuthenticatedUser($user);

        $this->quotaService
            ->expects($this->once())
            ->method('enforceQuotaLimit')
            ->willThrowException(new \Exception('Unexpected error'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Erreur lors de la vérification des quotas', $this->arrayHasKey('error'));

        $this->quotaMiddleware->onKernelRequest($event);

        // L'erreur ne doit pas bloquer la requête
        $this->assertNull($event->getResponse());
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail('test@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setRole('USER');

        return $user;
    }

    private function setupAuthenticatedUser(User $user): void
    {
        $symfonyUser = $this->createMock(UserInterface::class);
        $symfonyUser->method('getUserIdentifier')->willReturn($user->getEmail());

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($symfonyUser);

        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $this->userRepository
            ->method('findOneBy')
            ->with(['email' => $user->getEmail()])
            ->willReturn($user);
    }
}
