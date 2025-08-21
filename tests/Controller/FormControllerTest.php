<?php

namespace App\Tests\Controller;

use App\Entity\Form;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class FormControllerTest extends WebTestCase
{
    private ?EntityManagerInterface $em = null;
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    public function testGetFormsRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/forms');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetFormsWithAuthentication(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);

        $this->client->request(
            'GET',
            '/api/forms',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('meta', $responseData);
        $this->assertTrue($responseData['success']);
    }

    public function testGetFormsWithPagination(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);

        $this->client->request(
            'GET',
            '/api/forms?page=1&limit=5',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(1, $responseData['meta']['page']);
        $this->assertEquals(5, $responseData['meta']['limit']);
    }

    public function testGetFormsWithStatusFilter(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);

        $this->client->request(
            'GET',
            '/api/forms?status=DRAFT',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
    }

    public function testGetFormById(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $token = $this->loginUser($user);

        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId(),
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($form->getId(), $responseData['data']['id']);
        $this->assertEquals($form->getTitle(), $responseData['data']['title']);
    }

    public function testGetFormByIdNotFound(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);

        $this->client->request(
            'GET',
            '/api/forms/' . Uuid::v4(),
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateFormWithValidData(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);

        $formData = [
            'title' => 'Formulaire de test',
            'description' => 'Description du formulaire de test pour validation',
            'status' => 'DRAFT',
            'schema' => [
                'fields' => [
                    [
                        'id' => 'field1',
                        'type' => 'text',
                        'label' => 'Nom',
                        'required' => true,
                        'position' => 1,
                    ],
                ],
            ],
        ];

        $this->client->request(
            'POST',
            '/api/forms',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
            json_encode($formData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($formData['title'], $responseData['data']['title']);
        $this->assertEquals($formData['description'], $responseData['data']['description']);
    }

    public function testCreateFormWithInvalidData(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);

        $invalidFormData = [
            'title' => 'AB', // Trop court
            'description' => 'Court', // Trop court
        ];

        $this->client->request(
            'POST',
            '/api/forms',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
            json_encode($invalidFormData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('errors', $responseData);
    }

    public function testUpdateForm(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $token = $this->loginUser($user);

        $updateData = [
            'title' => 'Titre modifié',
            'description' => 'Description modifiée pour le test',
        ];

        $this->client->request(
            'PUT',
            '/api/forms/' . $form->getId(),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
            json_encode($updateData)
        );

        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($updateData['title'], $responseData['data']['title']);
        $this->assertEquals($updateData['description'], $responseData['data']['description']);
    }

    public function testUpdateFormUnauthorized(): void
    {
        $user = $this->createTestUser();
        $otherUser = $this->createTestUser('other@example.com');
        $form = $this->createTestForm($user);
        $token = $this->loginUser($otherUser);

        $updateData = [
            'title' => 'Titre modifié',
        ];

        $this->client->request(
            'PUT',
            '/api/forms/' . $form->getId(),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
            json_encode($updateData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testDeleteForm(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $token = $this->loginUser($user);

        $this->client->request(
            'DELETE',
            '/api/forms/' . $form->getId(),
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testPublishForm(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $token = $this->loginUser($user);

        $this->client->request(
            'POST',
            '/api/forms/' . $form->getId() . '/publish',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('PUBLISHED', $responseData['data']['status']);
        $this->assertNotNull($responseData['data']['publishedAt']);
    }

    public function testGenerateEmbedCode(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user, 'PUBLISHED');
        $token = $this->loginUser($user);

        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId() . '/embed',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('embedCode', $responseData['data']);
        $this->assertArrayHasKey('token', $responseData['data']);
        $this->assertArrayHasKey('embedUrl', $responseData['data']);
        $this->assertStringContainsString('<iframe', $responseData['data']['embedCode']);
    }

    public function testGenerateEmbedCodeWithCustomization(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user, 'PUBLISHED');
        $token = $this->loginUser($user);

        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId() . '/embed?width=800px&height=400px',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertStringContainsString('width: 800px', $responseData['data']['embedCode']);
        $this->assertStringContainsString('height: 400px', $responseData['data']['embedCode']);
    }

    public function testGenerateEmbedCodeForDraftFormFails(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user, 'DRAFT');
        $token = $this->loginUser($user);

        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId() . '/embed',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
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
        $form->setDescription('Description du formulaire de test');
        $form->setStatus($status);
        $form->setUser($user);

        if ($status === 'PUBLISHED') {
            $form->setPublishedAt(new \DateTimeImmutable());
        }

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

        return $responseData['token'];
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
