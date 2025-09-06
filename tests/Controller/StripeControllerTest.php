<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Service\StripeCheckoutService;
use App\Service\StripeCustomerPortalService;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Stripe\Checkout\Session;
use Stripe\Product;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class StripeControllerTest extends WebTestCase
{
    private $client;
    private ?EntityManagerInterface $em = null;
    private MockObject $stripeService;
    private MockObject $checkoutService;
    private MockObject $portalService;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->client = static::createClient([
            'environment' => 'test',
        ]);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Créer les mocks AVANT que le container ne soit compilé
        $this->stripeService = $this->createMock(StripeService::class);
        $this->checkoutService = $this->createMock(StripeCheckoutService::class);
        $this->portalService = $this->createMock(StripeCustomerPortalService::class);

        // Configurer les mocks par défaut pour éviter les appels API
        $this->setupDefaultMocks();

        // Remplacer les services dans le container
        static::getContainer()->set(StripeService::class, $this->stripeService);
        static::getContainer()->set(StripeCheckoutService::class, $this->checkoutService);
        static::getContainer()->set(StripeCustomerPortalService::class, $this->portalService);
    }

    private function setupDefaultMocks(): void
    {
        // Configuration par défaut pour éviter les appels API réels
        $this->stripeService
            ->method('getActiveProducts')
            ->willReturn([]);

        $this->checkoutService
            ->method('createCheckoutSession')
            ->willReturn($this->createMock(Session::class));

        $this->checkoutService
            ->method('getCheckoutSession')
            ->willReturn($this->createMock(Session::class));

        $this->portalService
            ->method('createDefaultPortalSession')
            ->willReturn($this->createMock(\Stripe\BillingPortal\Session::class));
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

    public function testCreateCheckoutSessionSuccess(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);

        $mockSession = $this->createMock(Session::class);
        $mockSession->id = 'cs_test_123';
        $mockSession->url = 'https://checkout.stripe.com/test';

        $this->checkoutService
            ->expects($this->once())
            ->method('createCheckoutSession')
            ->willReturn($mockSession);

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
                'price_id' => 'price_test123',
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('cs_test_123', $responseData['session_id']);
        $this->assertEquals('https://checkout.stripe.com/test', $responseData['url']);
        $this->assertEquals('success', $responseData['status']);
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
                // Données manquantes
                'success_url' => '',
                'cancel_url' => '',
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Données invalides', $responseData['error']);
    }

    public function testGetCheckoutSessionSuccess(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);

        $mockSession = $this->createMock(Session::class);
        $mockSession->id = 'cs_test_123';
        $mockSession->payment_status = 'paid';
        $mockSession->status = 'complete';
        $mockSession->customer = 'cus_test123';
        $mockSession->subscription = 'sub_test123';
        $mockSession->metadata = new \Stripe\StripeObject();
        $mockSession->metadata['user_id'] = $user->getId();

        $this->checkoutService
            ->expects($this->once())
            ->method('getCheckoutSession')
            ->with('cs_test_123')
            ->willReturn($mockSession);

        $this->client->request(
            'GET',
            '/api/stripe/checkout-session/cs_test_123',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('cs_test_123', $responseData['session']['id']);
        $this->assertEquals('paid', $responseData['session']['payment_status']);
        $this->assertEquals('complete', $responseData['session']['status']);
    }

    public function testCreateCustomerPortalSuccess(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);

        $mockSession = $this->createMock(\Stripe\BillingPortal\Session::class);
        $mockSession->id = 'bps_test_123';
        $mockSession->url = 'https://billing.stripe.com/test';

        $this->portalService
            ->expects($this->once())
            ->method('createDefaultPortalSession')
            ->willReturn($mockSession);

        $this->client->request(
            'POST',
            '/api/stripe/customer-portal',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
            json_encode([
                'return_url' => 'https://example.com/account',
            ])
        );

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('bps_test_123', $responseData['session_id']);
        $this->assertEquals('https://billing.stripe.com/test', $responseData['url']);
        $this->assertEquals('success', $responseData['status']);
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

    public function testGetProductsSuccess(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);

        $mockProduct = $this->createMock(Product::class);
        $mockProduct->id = 'prod_test123';
        $mockProduct->name = 'Test Product';
        $mockProduct->description = 'Test Description';
        $mockProduct->default_price = null;
        $mockProduct->metadata = new \Stripe\StripeObject();

        $this->stripeService
            ->expects($this->once())
            ->method('getActiveProducts')
            ->willReturn([$mockProduct]);

        $this->client->request(
            'GET',
            '/api/stripe/products',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('success', $responseData['status']);
        $this->assertCount(1, $responseData['products']);
        $this->assertEquals('prod_test123', $responseData['products'][0]['id']);
        $this->assertEquals('Test Product', $responseData['products'][0]['name']);
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
