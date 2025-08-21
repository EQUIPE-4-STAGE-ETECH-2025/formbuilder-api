<?php

namespace App\Tests\Controller;

use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\User;
use App\Service\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class QuotaControllerTest extends WebTestCase
{
    private ?EntityManagerInterface $em = null;
    private $client;
    private ?JwtService $jwtService = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->jwtService = static::getContainer()->get(JwtService::class);
    }

    public function testGetQuotasSuccess(): void
    {
        $user = $this->createUserWithPlan();
        $token = $this->loginAndGetToken($user->getEmail(), 'password123');

        $this->client->request(
            'GET',
            '/api/users/' . $user->getId() . '/quotas',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent() ?: '', true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals($user->getId(), $data['data']['user_id']);
        $this->assertArrayHasKey('limits', $data['data']);
        $this->assertArrayHasKey('usage', $data['data']);
        $this->assertArrayHasKey('percentages', $data['data']);
        $this->assertArrayHasKey('is_over_limit', $data['data']);

        // Vérifier les limites du plan
        $this->assertEquals(10, $data['data']['limits']['max_forms']);
        $this->assertEquals(1000, $data['data']['limits']['max_submissions_per_month']);
        $this->assertEquals(100, $data['data']['limits']['max_storage_mb']);

        $this->cleanupUser($user);
    }

    public function testGetQuotasUnauthorizedWithoutToken(): void
    {
        $user = $this->createUserWithPlan();

        $this->client->request(
            'GET',
            '/api/users/' . $user->getId() . '/quotas',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );

        $response = $this->client->getResponse();
        $this->assertEquals(401, $response->getStatusCode());

        $this->cleanupUser($user);
    }

    public function testGetQuotasUserNotFound(): void
    {
        $user = $this->createUserWithPlan();
        $token = $this->loginAndGetToken($user->getEmail(), 'password123');
        $nonExistentUserId = Uuid::v4();

        $this->client->request(
            'GET',
            '/api/users/' . $nonExistentUserId . '/quotas',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(404, $response->getStatusCode());

        $data = json_decode($response->getContent() ?: '', true);
        $this->assertEquals('Utilisateur non trouvé', $data['error']);

        $this->cleanupUser($user);
    }

    public function testGetQuotasForbiddenForOtherUser(): void
    {
        $user1 = $this->createUserWithPlan('user1@example.com');
        $user2 = $this->createUserWithPlan('user2@example.com');

        $token = $this->loginAndGetToken($user1->getEmail(), 'password123');

        $this->client->request(
            'GET',
            '/api/users/' . $user2->getId() . '/quotas',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(403, $response->getStatusCode());

        $data = json_decode($response->getContent() ?: '', true);
        $this->assertEquals('Accès non autorisé', $data['error']);

        $this->cleanupUser($user1);
        $this->cleanupUser($user2);
    }

    public function testGetQuotasAllowedForAdmin(): void
    {
        $adminUser = $this->createUserWithPlan('admin@example.com', 'ADMIN');
        $regularUser = $this->createUserWithPlan('user@example.com', 'USER');

        $token = $this->loginAndGetToken($adminUser->getEmail(), 'password123');

        $this->client->request(
            'GET',
            '/api/users/' . $regularUser->getId() . '/quotas',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent() ?: '', true);
        $this->assertTrue($data['success']);
        $this->assertEquals($regularUser->getId(), $data['data']['user_id']);

        $this->cleanupUser($adminUser);
        $this->cleanupUser($regularUser);
    }

    public function testGetQuotasWithoutActivePlan(): void
    {
        $user = $this->createUserWithoutPlan();
        $token = $this->loginAndGetToken($user->getEmail(), 'password123');

        $this->client->request(
            'GET',
            '/api/users/' . $user->getId() . '/quotas',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(500, $response->getStatusCode());

        $data = json_decode($response->getContent() ?: '', true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Erreur lors de la récupération des quotas', $data['error']);
        $this->assertStringContainsString('Aucun plan actif trouvé', $data['details']);

        $this->cleanupUser($user);
    }

    private function createUserWithPlan(string $email = 'test@example.com', string $role = 'USER'): User
    {
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        // Créer un plan
        $plan = new Plan();
        $plan->setId(Uuid::v4());
        $plan->setName('Plan Test');
        $plan->setPriceCents(999);
        $plan->setStripeProductId('prod_test_' . uniqid());
        $plan->setMaxForms(10);
        $plan->setMaxSubmissionsPerMonth(1000);
        $plan->setMaxStorageMb(100);

        // Créer un utilisateur
        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setRole($role);
        $user->setIsEmailVerified(true);
        $user->setPasswordHash($passwordHasher->hashPassword($user, 'password123'));

        // Créer un abonnement actif
        $subscription = new Subscription();
        $subscription->setId(Uuid::v4());
        $subscription->setUser($user);
        $subscription->setPlan($plan);
        $subscription->setStripeSubscriptionId('sub_test_' . uniqid());
        $subscription->setStartDate(new \DateTime('2024-01-01'));
        $subscription->setEndDate(new \DateTime('2024-12-31'));
        $subscription->setIsActive(true);

        $this->removeUserIfExists($email);
        $this->em->persist($plan);
        $this->em->persist($user);
        $this->em->persist($subscription);
        $this->em->flush();

        return $user;
    }

    private function createUserWithoutPlan(string $email = 'test_no_plan@example.com'): User
    {
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setRole('USER');
        $user->setIsEmailVerified(true);
        $user->setPasswordHash($passwordHasher->hashPassword($user, 'password123'));

        $this->removeUserIfExists($email);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function removeUserIfExists(string $email): void
    {
        $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            // Supprimer les abonnements associés
            $subscriptions = $this->em->getRepository(Subscription::class)->findBy(['user' => $existingUser]);
            foreach ($subscriptions as $subscription) {
                $this->em->remove($subscription);
            }

            $this->em->remove($existingUser);
            $this->em->flush();
        }
    }

    private function loginAndGetToken(string $email, string $password): string
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent() ?: '', true);

        return $data['token'];
    }

    private function cleanupUser(User $user): void
    {
        // Récupérer l'utilisateur depuis la base pour éviter les problèmes "detached"
        $managedUser = $this->em->find(User::class, $user->getId());
        if (! $managedUser) {
            return;
        }

        // Supprimer les abonnements
        $subscriptions = $this->em->getRepository(Subscription::class)->findBy(['user' => $managedUser]);
        foreach ($subscriptions as $subscription) {
            $plan = $subscription->getPlan();
            $this->em->remove($subscription);
            if ($plan) {
                $this->em->remove($plan);
            }
        }

        // Supprimer l'utilisateur
        $this->em->remove($managedUser);
        $this->em->flush();
    }
}
