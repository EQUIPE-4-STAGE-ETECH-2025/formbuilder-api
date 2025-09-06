<?php

namespace App\Tests\Controller;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\JwtService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionControllerTest extends WebTestCase
{
    private $client;
    private $jwtService;
    private $userRepository;
    private $subscriptionRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = self::getContainer();

        $this->jwtService = $container->get(JwtService::class);
        $this->userRepository = $container->get(UserRepository::class);
        $this->subscriptionRepository = $container->get(SubscriptionRepository::class);
    }

    /**
     * Crée un header Authorization avec JWT pour un utilisateur donné.
     */
    private function createAuthHeader(string $email): array
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        // On crée un payload array avec les infos essentielles du user
        $payload = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
        ];

        $token = $this->jwtService->generateToken($payload);

        return ['HTTP_Authorization' => 'Bearer ' . $token];
    }

    public function testGetUserSubscriptions(): void
    {
        $user = $this->userRepository->findOneBy(['email' => 'anna@example.com']);
        $authHeader = $this->createAuthHeader($user->getEmail());

        $this->client->request(
            'GET',
            '/api/users/' . $user->getId() . '/subscriptions',
            [],
            [],
            $authHeader
        );

        $response = $this->client->getResponse();

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('planName', $data[0]);
        $this->assertArrayHasKey('status', $data[0]);
    }

    public function testCreateSubscription(): void
    {
        $user = $this->userRepository->findOneBy(['email' => 'anna@example.com']);
        $plan = self::getContainer()->get('App\Repository\PlanRepository')->findAll()[0];
        $authHeader = $this->createAuthHeader($user->getEmail());

        $payload = [
            'planId' => $plan->getId(),
            'userEmail' => $user->getEmail(),
        ];

        $this->client->request(
            'POST',
            '/api/subscriptions',
            [],
            [],
            $authHeader,
            json_encode($payload)
        );

        $response = $this->client->getResponse();

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('planName', $data);
        $this->assertEquals($plan->getName(), $data['planName']);
    }

    public function testUpdateSubscriptionStatus(): void
    {
        $subscription = $this->subscriptionRepository->findAll()[0];
        $user = $subscription->getUser();
        $authHeader = $this->createAuthHeader($user->getEmail());

        $payload = ['status' => Subscription::STATUS_SUSPENDED];

        $this->client->request(
            'PUT',
            '/api/subscriptions/' . $subscription->getId() . '/status',
            [],
            [],
            $authHeader,
            json_encode($payload)
        );

        $response = $this->client->getResponse();

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($subscription->getId(), $data['id']);
        $this->assertEquals(Subscription::STATUS_SUSPENDED, $data['status']);
    }

    public function testGetSubscriptionStatus(): void
    {
        $subscription = $this->subscriptionRepository->findAll()[0];
        $user = $subscription->getUser();
        $authHeader = $this->createAuthHeader($user->getEmail());

        $this->client->request(
            'GET',
            '/api/subscriptions/' . $subscription->getId() . '/status',
            [],
            [],
            $authHeader
        );

        $response = $this->client->getResponse();

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals($subscription->getId(), $data['id']);
    }
}
