<?php

namespace App\Tests\Controller;

use App\Entity\Form;
use App\Entity\FormVersion;
use App\Entity\Submission;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class SubmissionControllerTest extends WebTestCase
{
    private ?EntityManagerInterface $em = null;
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    public function testSubmitFormSuccessfully(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user, 'PUBLISHED');

        $formData = [
            'email' => 'test@example.com',
            'message' => 'Bonjour, ceci est un test',
        ];

        $this->client->request(
            'POST',
            '/api/forms/' . $form->getId() . '/submit',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($formData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('formId', $responseData);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('submittedAt', $responseData);
        $this->assertArrayHasKey('ipAddress', $responseData);
        $this->assertEquals($form->getId(), $responseData['formId']);
    }

    public function testSubmitFormWithInvalidData(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user, 'PUBLISHED');

        $this->client->request(
            'POST',
            '/api/forms/' . $form->getId() . '/submit',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode('invalid-json-data')
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Données invalides', $responseData['error']);
    }

    public function testSubmitFormNotFound(): void
    {
        $nonExistentFormId = Uuid::v4();

        $formData = [
            'email' => 'test@example.com',
        ];

        $this->client->request(
            'POST',
            '/api/forms/' . $nonExistentFormId . '/submit',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($formData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testListSubmissionsRequiresAuthentication(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);

        $this->client->request('GET', '/api/forms/' . $form->getId() . '/submissions');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testListSubmissionsWithAuthentication(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $this->createTestSubmission($form);
        $token = $this->loginUser($user);

        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId() . '/submissions',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertNotEmpty($responseData);

        // Vérifier la structure des données de soumission
        $submission = $responseData[0];
        $this->assertArrayHasKey('id', $submission);
        $this->assertArrayHasKey('formId', $submission);
        $this->assertArrayHasKey('data', $submission);
        $this->assertArrayHasKey('submittedAt', $submission);
        $this->assertArrayHasKey('ipAddress', $submission);
    }

    public function testListSubmissionsForbiddenForOtherUser(): void
    {
        $user1 = $this->createTestUser('user1@example.com');
        $user2 = $this->createTestUser('user2@example.com');
        $form = $this->createTestForm($user1);
        $token = $this->loginUser($user2);

        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId() . '/submissions',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Accès refusé', $responseData['error']);
    }

    public function testListSubmissionsFormNotFound(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);
        $nonExistentFormId = Uuid::v4();

        $this->client->request(
            'GET',
            '/api/forms/' . $nonExistentFormId . '/submissions',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testExportSubmissionsCsv(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $this->createTestSubmission($form);
        $token = $this->loginUser($user);

        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId() . '/submissions/export',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'text/csv');

        $response = $this->client->getResponse();
        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment; filename=', $contentDisposition);
        $this->assertStringContainsString('submissions_', $contentDisposition);
        $this->assertStringContainsString('.csv', $contentDisposition);

        // Vérifier que le contenu CSV n'est pas vide
        $csvContent = $response->getContent();
        $this->assertNotEmpty($csvContent);
    }

    public function testExportSubmissionsWithPagination(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $this->createTestSubmission($form);
        $token = $this->loginUser($user);

        // Test avec limit
        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId() . '/submissions/export?limit=5',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();

        // Test avec limit et offset
        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId() . '/submissions/export?limit=5&offset=0',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
    }

    public function testExportSubmissionsWithInvalidPagination(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $token = $this->loginUser($user);

        // Test avec limit négatif
        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId() . '/submissions/export?limit=-1',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertStringContainsString('Le paramètre limit doit être un entier positif', $responseData['error']);

        // Test avec offset négatif
        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId() . '/submissions/export?offset=-1',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertStringContainsString('Le paramètre offset doit être un entier positif ou nul', $responseData['error']);
    }

    public function testExportSubmissionsRequiresAuthentication(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);

        $this->client->request('GET', '/api/forms/' . $form->getId() . '/submissions/export');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testExportSubmissionsForbiddenForOtherUser(): void
    {
        $user1 = $this->createTestUser('user1@example.com');
        $user2 = $this->createTestUser('user2@example.com');
        $form = $this->createTestForm($user1);
        $token = $this->loginUser($user2);

        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId() . '/submissions/export',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
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

    private function createTestForm(User $user, string $status = 'DRAFT'): Form
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setTitle('Formulaire de test');
        $form->setDescription('Description du formulaire de test pour les soumissions');
        $form->setStatus($status);
        $form->setUser($user);

        if ($status === 'PUBLISHED') {
            $form->setPublishedAt(new \DateTimeImmutable());
        }

        $this->em->persist($form);
        $this->em->flush();

        // Créer une version du formulaire avec un schéma
        $this->createTestFormVersion($form);

        return $form;
    }

    private function createTestFormVersion(Form $form): FormVersion
    {
        $formVersion = new FormVersion();
        $formVersion->setId(Uuid::v4());
        $formVersion->setForm($form);
        $formVersion->setVersionNumber(1);
        $formVersion->setSchema([
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'email',
                    'label' => 'email',
                    'required' => true,
                    'position' => 1,
                ],
                [
                    'id' => 'field2',
                    'type' => 'textarea',
                    'label' => 'message',
                    'required' => false,
                    'position' => 2,
                ],
            ],
        ]);

        $this->em->persist($formVersion);
        $this->em->flush();

        return $formVersion;
    }

    private function createTestSubmission(Form $form): Submission
    {
        $submission = new Submission();
        $submission->setId(Uuid::v4());
        $submission->setForm($form);
        $submission->setData([
            'field1' => 'submission@example.com',
            'field2' => 'Ceci est une soumission de test',
        ]);
        $submission->setIpAddress('127.0.0.1');
        $submission->setSubmittedAt(new \DateTimeImmutable());

        $this->em->persist($submission);
        $this->em->flush();

        return $submission;
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
        $this->em?->close();
        $this->em = null;
        parent::tearDown();
    }
}
