<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\AuthorizationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AuthorizationServiceTest extends TestCase
{
    private $authorizationChecker;
    private $authorizationService;

    protected function setUp(): void
    {
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->authorizationService = new AuthorizationService($this->authorizationChecker);
    }

    public function testRequirePermissionGranted(): void
    {
        $user = new User();

        $this->authorizationChecker->method('isGranted')->willReturn(true);

        $this->authorizationService->requirePermissionOnObject('ANY_PERMISSION', $user);

        $this->assertTrue(true); // Just to mark the test as passed if no exception is thrown
    }

    public function testRequirePermissionDenied(): void
    {
        $user = new User();

        $this->authorizationChecker->method('isGranted')->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Accès refusé : permission ANY_PERMISSION requise.');

        $this->authorizationService->requirePermissionOnObject('ANY_PERMISSION', $user);
    }
}
