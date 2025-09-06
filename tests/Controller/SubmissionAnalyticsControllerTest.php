<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Service\SubmissionAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class SubmissionAnalyticsControllerTest extends WebTestCase
{
    private $client;
    private ?EntityManagerInterface $em = null;
    private MockObject $analyticsService;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->analyticsService = $this->createMock(SubmissionAnalyticsService::class);
        static::getContainer()->set(SubmissionAnalyticsService::class, $this->analyticsService);
    }

    public function testGetAnalyticsRequiresAuthentication(): void
    {
        $formId = Uuid::v4()->toRfc4122();

        $this->client->request('GET', '/api/forms/' . $formId . '/submissions/analytics');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetAnalyticsSuccess(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);
        $formId = Uuid::v4()->toRfc4122();

        $mockAnalytics = [
            'form_id' => $formId,
            'total_submissions' => 25,
            'submissions_per_day' => [
                '2025-01-10' => 5,
                '2025-01-11' => 8,
                '2025-01-12' => 12,
            ],
            'submissions_per_month' => [
                '2025-01' => 25,
            ],
            'response_distribution' => [
                'field1' => [
                    'option1' => 10,
                    'option2' => 15,
                ],
            ],
            'completion_rate' => 85.5,
            'average_completion_time' => 120, // secondes
            'bounce_rate' => 14.5,
            'most_popular_fields' => [
                'email' => 25,
                'name' => 24,
                'message' => 20,
            ],
            'conversion_funnel' => [
                'started' => 30,
                'completed' => 25,
                'abandoned' => 5,
            ],
        ];

        $this->analyticsService
            ->expects($this->once())
            ->method('getFormAnalytics')
            ->with($formId)
            ->willReturn($mockAnalytics);

        $this->client->request(
            'GET',
            '/api/forms/' . $formId . '/submissions/analytics',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals($formId, $responseData['form_id']);
        $this->assertEquals(25, $responseData['total_submissions']);
        $this->assertEquals(85.5, $responseData['completion_rate']);
        $this->assertArrayHasKey('submissions_per_day', $responseData);
        $this->assertArrayHasKey('submissions_per_month', $responseData);
        $this->assertArrayHasKey('response_distribution', $responseData);
        $this->assertArrayHasKey('conversion_funnel', $responseData);
    }

    public function testGetAnalyticsFormNotFound(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);
        $formId = Uuid::v4()->toRfc4122();

        $this->analyticsService
            ->expects($this->once())
            ->method('getFormAnalytics')
            ->with($formId)
            ->willThrowException(new \InvalidArgumentException('Formulaire introuvable.'));

        $this->client->request(
            'GET',
            '/api/forms/' . $formId . '/submissions/analytics',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(404);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Formulaire introuvable.', $responseData['error']);
    }

    public function testGetAnalyticsInternalError(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);
        $formId = Uuid::v4()->toRfc4122();

        $this->analyticsService
            ->expects($this->once())
            ->method('getFormAnalytics')
            ->with($formId)
            ->willThrowException(new \Exception('Erreur inattendue'));

        $this->client->request(
            'GET',
            '/api/forms/' . $formId . '/submissions/analytics',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(500);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('An unexpected error occurred.', $responseData['error']);
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
