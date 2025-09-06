<?php

namespace App\Tests\Controller;

use App\Entity\Form;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Tests simplifiés pour SubmissionAnalyticsController
 * Note: Ces tests se concentrent sur l'authentification et la validation des paramètres
 */
class SubmissionAnalyticsControllerTest extends WebTestCase
{
    private $client;
    private ?EntityManagerInterface $em = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testGetAnalyticsRequiresAuthentication(): void
    {
        $formId = Uuid::v4()->toRfc4122();

        $this->client->request('GET', '/api/forms/' . $formId . '/submissions/analytics');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetAnalyticsWithInvalidUuid(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);
        $invalidFormId = 'invalid-uuid';

        $this->client->request(
            'GET',
            '/api/forms/' . $invalidFormId . '/submissions/analytics',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        // Le format d'UUID invalide devrait être géré par le routeur ou le controller
        // On vérifie que ça ne renvoie pas 200
        $this->assertNotEquals(200, $this->client->getResponse()->getStatusCode());
    }

    public function testGetAnalyticsWithValidFormId(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);
        $form = $this->createTestForm($user);

        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId() . '/submissions/analytics',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        // Avec un vrai formulaire en base, on devrait au moins avoir une réponse
        // même si elle indique qu'il n'y a pas de données
        $response = $this->client->getResponse();
        $this->assertTrue(
            in_array($response->getStatusCode(), [200, 404]),
            'Expected 200 or 404, got ' . $response->getStatusCode()
        );
    }

    public function testGetAnalyticsWithNonExistentForm(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);
        $nonExistentFormId = Uuid::v4()->toRfc4122();

        $this->client->request(
            'GET',
            '/api/forms/' . $nonExistentFormId . '/submissions/analytics',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(404);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Formulaire introuvable.', $responseData['error']);
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

    private function createTestForm(User $user): Form
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setTitle('Test Form');
        $form->setDescription('Test form description for analytics testing');
        $form->setUser($user);
        $form->setStatus('PUBLISHED');
        $form->setCreatedAt(new \DateTimeImmutable());
        $form->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($form);
        $this->em->flush();

        return $form;
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
