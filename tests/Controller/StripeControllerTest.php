<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Tests simplifiés pour StripeController
 * Note: Ces tests se concentrent sur la logique métier sans appels API Stripe réels
 */
class StripeControllerTest extends WebTestCase
{
    private $client;
    private ?EntityManagerInterface $em = null;

    protected function setUp(): void
    {
        $this->client = static::createClient([
            'environment' => 'test',
        ]);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCreateCheckoutSessionRequiresAuthentication(): void
    {
        $this->client->request(
            'POST',
            '/api/stripe/checkout-session',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'price_id' => 'price_test123',
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateCheckoutSessionValidationError(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);

        $this->client->request(
            'POST',
            '/api/stripe/checkout-session',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
            json_encode([
                // Données manquantes/invalides
                'price_id' => '',
                'success_url' => '',
                'cancel_url' => '',
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Données invalides', $responseData['error']);
        $this->assertArrayHasKey('details', $responseData);
    }

    public function testCreateCustomerPortalRequiresAuthentication(): void
    {
        $this->client->request(
            'POST',
            '/api/stripe/customer-portal',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'return_url' => 'https://example.com/account',
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateCustomerPortalMissingReturnUrl(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);

        $this->client->request(
            'POST',
            '/api/stripe/customer-portal',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(400);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('return_url est obligatoire', $responseData['error']);
    }

    public function testGetProductsRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/stripe/products');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetCheckoutSessionRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/stripe/checkout-session/cs_test_123');

        $this->assertResponseStatusCodeSame(401);
    }

    private function createTestUser(string $email = 'test@example.com'): User
    {
        $this->removeUserIfExists($email);

        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setRole('USER');
        $user->setIsEmailVerified(true);
        $user->setPasswordHash($passwordHasher->hashPassword($user, 'password123'));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function loginUser(User $user): string
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $user->getEmail(),
                'password' => 'password123',
            ])
        );

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        if (! isset($responseData['success']) || ! $responseData['success'] || ! isset($responseData['data']['token'])) {
            throw new \RuntimeException('Échec de la connexion: ' . json_encode($responseData));
        }

        return $responseData['data']['token'];
    }

    private function removeUserIfExists(string $email): void
    {
        $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $this->em->remove($existingUser);
            $this->em->flush();
        }
    }

    protected function tearDown(): void
    {
        if ($this->em) {
            $this->em->close();
            $this->em = null;
        }
        parent::tearDown();
    }
}
