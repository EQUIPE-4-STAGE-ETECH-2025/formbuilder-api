<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

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
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('token', $data['data']);
        $this->assertEquals($email, $data['data']['user']['email']);
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
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('email', $data['error']);
        $this->assertArrayHasKey('password', $data['error']);
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
        $token = $responseData['data']['token'];

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
        $token = $data['data']['token'];

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

    public function testRegisterSuccess(): void
    {
        $email = 'test_' . Uuid::v4() . '@example.com';

        $payload = [
            'firstName' => 'Alice',
            'lastName' => 'Wonder',
            'email' => $email,
            'password' => 'MotdepasseFort123!',
        ];

        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('user', $data);
        $this->assertEquals($email, $data['user']['email']);
        $this->assertFalse($data['user']['isEmailVerified']);
    }

    public function testRegisterValidationErrors(): void
    {
        $payload = [
            'firstName' => '',
            'lastName' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ];

        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $this->assertResponseStatusCodeSame(422);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('email', $data['errors']);
        $this->assertArrayHasKey('password', $data['errors']);
    }

    public function testVerifyEmailSuccess(): void
    {
        $email = 'test_' . Uuid::v4() . '@example.com';
        $password = 'MotdepasseFort123!';
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $jwtService = static::getContainer()->get('App\Service\JwtService');
        $userRepository = static::getContainer()->get('App\Repository\UserRepository');

        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('Verify');
        $user->setIsEmailVerified(false);
        $user->setRole('USER');
        $user->setPasswordHash($passwordHasher->hashPassword($user, $password));

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->flush();

        $verificationToken = $jwtService->generateToken([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'type' => 'email_verification',
        ]);

        $this->client->request('GET', '/api/auth/verify-email?token=' . $verificationToken);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Email vérifié avec succès', $data['message']);

        $em->clear();

        $verifiedUser = $userRepository->find($user->getId());
        $this->assertTrue($verifiedUser->isEmailVerified());
    }

    public function testVerifyEmailInvalidToken(): void
    {
        $this->client->request('GET', '/api/auth/verify-email?token=invalid-token');

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testForgotPasswordSuccess(): void
    {
        $email = 'test_' . Uuid::v4() . '@example.com';
        $password = 'MotdepasseFort123!';
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail($email);
        $user->setFirstName('Jean');
        $user->setLastName('Forgot');
        $user->setRole('USER');
        $user->setIsEmailVerified(true);
        $user->setPasswordHash($passwordHasher->hashPassword($user, $password));

        $this->removeUserIfExists($email);
        $this->em->persist($user);
        $this->em->flush();

        $this->client->request(
            'POST',
            '/api/auth/forgot-password',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email])
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Email de réinitialisation envoyé', $data['message']);
    }

    public function testForgotPasswordNotFound(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/forgot-password',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'nonexistent@example.com'])
        );

        $this->assertResponseStatusCodeSame(404);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testResetPasswordSuccess(): void
    {
        $email = 'test_' . Uuid::v4() . '@example.com';
        $password = 'MotdepasseFort123!';
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $jwtService = static::getContainer()->get('App\Service\JwtService');

        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail($email);
        $user->setFirstName('Jean');
        $user->setLastName('Reset');
        $user->setRole('USER');
        $user->setIsEmailVerified(true);
        $user->setPasswordHash($passwordHasher->hashPassword($user, $password));

        $this->removeUserIfExists($email);
        $this->em->persist($user);
        $this->em->flush();

        $resetToken = $jwtService->generateToken([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'type' => 'password_reset',
        ]);

        $newPassword = 'NewStrongPass456!';

        $oldPasswordHash = $user->getPasswordHash();

        $this->client->request(
            'POST',
            '/api/auth/reset-password',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'token' => $resetToken,
                'newPassword' => $newPassword,
            ])
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Mot de passe réinitialisé avec succès', $data['message']);

        $this->em->clear();
        $updatedUser = $this->em->getRepository(User::class)->find($user->getId());

        $this->assertNotEquals($oldPasswordHash, $updatedUser->getPasswordHash());
        $this->assertTrue($passwordHasher->isPasswordValid($updatedUser, $newPassword));
    }

    public function testResetPasswordValidationError(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/reset-password',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'token' => '',
                'newPassword' => 'short',
            ])
        );

        $this->assertResponseStatusCodeSame(422);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('token', $data['errors']);
        $this->assertArrayHasKey('newPassword', $data['errors']);
    }

    public function testResetPasswordInvalidToken(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/reset-password',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'token' => 'invalid-token',
                'newPassword' => 'NewStrongPass456!',
            ])
        );

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    // Fonction qui nettoie l'environnement après le test
    protected function tearDown(): void
    {
        $this->em?->close();
        $this->em = null;

        parent::tearDown();
    }
}
