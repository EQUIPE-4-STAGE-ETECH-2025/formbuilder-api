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

        $this->userService = $this->createMock(UserService::class);

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
            json_encode([])
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

    public function testGetProfileSuccess(): void
    {
        $userId = Uuid::v4()->toRfc4122();

        $user = new User();
        $user->setId($userId);
        $user->setEmail('john@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setRole('USER');
        $user->setIsEmailVerified(true);

        $this->userService->expects($this->once())
            ->method('getUserProfile')
            ->with($userId)
            ->willReturn($user);

        $this->client->request('GET', "/api/users/$userId/profile");

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('john@example.com', $data['email']);
        $this->assertEquals('John', $data['firstName']);
        $this->assertEquals('Doe', $data['lastName']);
        $this->assertTrue($data['isEmailVerified']);
    }

    public function testUpdateProfileInvalidData(): void
    {
        $userId = Uuid::v4()->toRfc4122();

        $this->userService->expects($this->once())
            ->method('updateUserProfile')
            ->willThrowException(new \InvalidArgumentException(json_encode(['firstName' => 'Ce champ est requis'])));

        $this->client->request(
            'PUT',
            "/api/users/$userId/profile",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
    }

    public function testUpdateProfileSuccess(): void
    {
        $userId = Uuid::v4()->toRfc4122();

        $user = new User();
        $user->setId($userId);
        $user->setEmail('john@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setRole('USER');
        $user->setIsEmailVerified(true);

        $this->userService->expects($this->once())
            ->method('updateUserProfile')
            ->with($userId, ['firstName' => 'John'])
            ->willReturn($user);

        $this->client->request(
            'PUT',
            "/api/users/$userId/profile",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['firstName' => 'John'])
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Profil mis à jour avec succès', $data['message']);
        $this->assertEquals('John', $data['user']['firstName']);
    }

    public function testListUsers(): void
    {
        $user1 = new User();
        $user1->setId(Uuid::v4()->toRfc4122());
        $user1->setEmail('user1@example.com');
        $user1->setFirstName('User');
        $user1->setLastName('One');
        $user1->setRole('USER');
        $user1->setIsEmailVerified(true);

        $user2 = new User();
        $user2->setId(Uuid::v4()->toRfc4122());
        $user2->setEmail('user2@example.com');
        $user2->setFirstName('User');
        $user2->setLastName('Two');
        $user2->setRole('ADMIN');
        $user2->setIsEmailVerified(false);

        $this->userService->expects($this->once())
            ->method('listUsers')
            ->willReturn([$user1, $user2]);

        $this->client->request('GET', '/api/users');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertEquals('user1@example.com', $data[0]['email']);
    }

    public function testDeleteUserSuccess(): void
    {
        $userId = Uuid::v4()->toRfc4122();

        $user = new User();
        $user->setId($userId);
        $user->setEmail('delete@example.com');
        $user->setFirstName('Del');
        $user->setLastName('Ete');

        $this->userService->expects($this->once())
            ->method('getUserProfile')
            ->with($userId)
            ->willReturn($user);

        $this->userService->expects($this->once())
            ->method('deleteUser')
            ->with($userId);

        $this->client->request('DELETE', "/api/users/$userId");

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Utilisateur supprimé avec succès', $data['message']);
        $this->assertEquals('delete@example.com', $data['user']['email']);
    }

    public function testDeleteUserNotFound(): void
    {
        $userId = 'non-existent-id';

        $this->userService->expects($this->once())
            ->method('getUserProfile')
            ->with($userId)
            ->willThrowException(new \RuntimeException('Utilisateur non trouvé'));

        $this->client->request('DELETE', '/api/users/' . $userId);

        $this->assertResponseStatusCodeSame(404);
        $this->assertJsonStringEqualsJsonString(
            json_encode(['error' => 'Utilisateur non trouvé']),
            $this->client->getResponse()->getContent()
        );
    }
}
