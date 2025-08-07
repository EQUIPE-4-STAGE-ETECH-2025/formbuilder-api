<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use Symfony\Component\Uid\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthControllerTest extends WebTestCase
{
    private ?EntityManagerInterface $em = null;

    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    public function testLoginSuccess(): void
    {
        $email = 'test_' . Uuid::v4() . '@example.com';
        $password = 'password123';

        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail($email);
        $user->setFirstName('Jean');
        $user->setLastName('Test');
        $user->setRole('USER');
        $user->setIsEmailVerified(true);
        $user->setPasswordHash($passwordHasher->hashPassword($user, $password));

        $this->removeUserIfExists($email);
        $this->em->persist($user);
        $this->em->flush();

        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => $password,
            ])
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertEquals($email, $data['user']['email']);
    }

    public function testLoginValidationError(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => '',
                'password' => '',
            ])
        );

        $this->assertResponseStatusCodeSame(422);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('email', $data['errors']);
        $this->assertArrayHasKey('password', $data['errors']);
    }

    public function testMeEndpointReturnsUser(): void
    {
        $email = 'test_' . Uuid::v4() . '@example.com';
        $password = 'password123';
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail($email);
        $user->setFirstName('Jean');
        $user->setLastName('Test');
        $user->setRole('USER');
        $user->setIsEmailVerified(true);
        $user->setPasswordHash($passwordHasher->hashPassword($user, $password));

        $this->removeUserIfExists($email);
        $this->em->persist($user);
        $this->em->flush();

        // Login pour obtenir un token
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $token = $responseData['token'];

        // Test de l'endpoint /me
        $this->client->request(
            'GET',
            '/api/auth/me',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($email, $data['email']);
    }

    public function testLogoutSuccessfully(): void
    {
        $email = 'test_' . Uuid::v4() . '@example.com';
        $password = 'password123';

        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail($email);
        $user->setFirstName('Jean');
        $user->setLastName('Logout');
        $user->setRole('USER');
        $user->setIsEmailVerified(true);
        $user->setPasswordHash($passwordHasher->hashPassword($user, $password));

        $this->removeUserIfExists($email);
        $this->em->persist($user);
        $this->em->flush();

        // Login pour obtenir un token
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $token = $data['token'];

        // Test de logout
        $this->client->request(
            'POST',
            '/api/auth/logout',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Déconnexion réussie', $response['message']);
    }

    private function removeUserIfExists(string $email): void
    {
        $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $this->em->remove($existingUser);
            $this->em->flush();
        }
    }

    //Fonction qui nettoie l'environnement après le test
    protected function tearDown(): void
    {
        $this->em?->close();
        $this->em = null;

        parent::tearDown();
    }
}
