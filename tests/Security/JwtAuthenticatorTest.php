<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\JwtAuthenticator;
use App\Service\JwtService;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JwtAuthenticatorTest extends TestCase
{
    private JwtService&MockObject $jwtService;
    private UserRepository&MockObject $userRepository;
    private JwtAuthenticator $authenticator;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->jwtService = $this->createMock(JwtService::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->authenticator = new JwtAuthenticator($this->jwtService, $this->userRepository);
    }

    public function testSupportsWithBearerToken(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer sometoken');

        $this->assertTrue($this->authenticator->supports($request));
    }

    public function testSupportsWithoutAuthorizationHeader(): void
    {
        $request = new Request();

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function testAuthenticateSuccess(): void
    {
        $token = 'validtoken';
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $payload = (object) ['id' => 'user-id-123'];

        $this->jwtService->expects($this->once())
            ->method('validateToken')
            ->with($token)
            ->willReturn($payload);

        $user = new User();
        $this->userRepository->expects($this->once())
            ->method('find')
            ->with($payload->id)
            ->willReturn($user);

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);

        // On ne teste pas la propriété interne userBadge (pas accessible)
        // Mais on peut tester que le Passport a un UserBadge en essayant d'appeler getUser()
        $userFromPassport = $passport->getUser();
        $this->assertSame($user, $userFromPassport);
    }

    public function testAuthenticateThrowsExceptionWhenNoToken(): void
    {
        $this->expectException(AuthenticationException::class);

        $request = new Request();
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsExceptionWhenUserNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $token = 'validtoken';
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $payload = (object) ['id' => 'user-id-123'];

        $this->jwtService->method('validateToken')->willReturn($payload);
        $this->userRepository->method('find')->willReturn(null);

        $passport = $this->authenticator->authenticate($request);

        $passport->getUser();
    }

    /**
     * @throws Exception
     */
    public function testOnAuthenticationSuccessReturnsNull(): void
    {
        $request = new Request();

        $response = $this->authenticator->onAuthenticationSuccess($request, $this->createMock(TokenInterface::class), 'main');

        $this->assertNull($response);
    }

    public function testOnAuthenticationFailureReturnsJsonResponse(): void
    {
        $request = new Request();
        $exception = new AuthenticationException('Failed!');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Authentification échouée', $data['error']);
        $this->assertEquals('Failed!', $data['details']);

        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
    }
}
