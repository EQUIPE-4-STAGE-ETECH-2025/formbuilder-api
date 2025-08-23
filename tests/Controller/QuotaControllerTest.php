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
        $this->assertEquals($user->getId(), $data['data']['user_id']);

        // Vérifier les limites du plan Free
        $this->assertEquals(3, $data['data']['limits']['max_forms']);
        $this->assertEquals(500, $data['data']['limits']['max_submissions_per_month']);
        $this->assertEquals(10, $data['data']['limits']['max_storage_mb']);

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

    private function createUserWithPlan(string $email = 'test@example.com', string $role = 'USER'): User
    {
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        // On prend le plan "Free" déjà présent en fixtures
        $plan = $this->em->getRepository(Plan::class)->findOneBy(['name' => 'Free']);
        if (!$plan) {
            throw new \RuntimeException("Le plan Free n’existe pas en base.");
        }

        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setRole($role);
        $user->setIsEmailVerified(true);
        $user->setPasswordHash($passwordHasher->hashPassword($user, 'password123'));

        $subscription = new Subscription();
        $subscription->setId(Uuid::v4());
        $subscription->setUser($user);
        $subscription->setPlan($plan);
        $subscription->setStripeSubscriptionId('sub_test_' . uniqid());
        $subscription->setStartDate(new \DateTime('2024-01-01'));
        $subscription->setEndDate(new \DateTime('2024-12-31'));
        $subscription->setStatus(Subscription::STATUS_ACTIVE);

        $this->removeUserIfExists($email);
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

        if (!isset($data['success']) || !$data['success'] || !isset($data['data']['token'])) {
            throw new \RuntimeException('Échec de la connexion: ' . json_encode($data));
        }

        return $data['data']['token'];
    }

    private function cleanupUser(User $user): void
    {
        $managedUser = $this->em->find(User::class, $user->getId());
        if (!$managedUser) {
            return;
        }

        $subscriptions = $this->em->getRepository(Subscription::class)->findBy(['user' => $managedUser]);
        foreach ($subscriptions as $subscription) {
            $this->em->remove($subscription);
        }

        $this->em->remove($managedUser);
        $this->em->flush();
    }
}
