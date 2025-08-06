<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class UserControllerTest extends WebTestCase
{
    private $client;
    private $userService;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Mock de UserService
        $this->userService = $this->createMock(UserService::class);

        // Remplace le service dans le container (possible depuis Symfony 5.3+)
        self::getContainer()->set(UserService::class, $this->userService);
    }

    public function testGetRoleSuccess(): void
    {
        $userId = Uuid::v4()->toRfc4122();
        $this->userService->expects($this->once())
            ->method('getUserRole')
            ->with($userId)
            ->willReturn('ADMIN');

        $this->client->request('GET', "/api/users/$userId/roles");

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('ADMIN', $data['role']);
    }

    public function testUpdateRoleInvalidPayload(): void
    {
        $userId = Uuid::v4()->toRfc4122();

        $this->client->request(
            'PUT',
            "/api/users/$userId/roles",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([]) // Pas de 'role'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Rôle invalide', $data['error']);
    }

    public function testUpdateRoleSuccess(): void
    {
        $userId = Uuid::v4()->toRfc4122();
        $newRole = 'ADMIN';

        $user = new User();
        $user->setId($userId);
        $user->setEmail('test@example.com');
        $user->setRole($newRole);

        $this->userService->expects($this->once())
            ->method('updateUserRole')
            ->with($userId, $newRole)
            ->willReturn($user);

        $this->client->request(
            'PUT',
            "/api/users/$userId/roles",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['role' => $newRole])
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Rôle mis à jour avec succès', $data['message']);
        $this->assertEquals($userId, $data['user']['id']);
        $this->assertEquals($newRole, $data['user']['role']);
    }
}
