<?php

namespace App\Tests\Controller;

use App\Entity\Form;
use App\Entity\FormVersion;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class FormVersionControllerTest extends WebTestCase
{
    private ?EntityManagerInterface $em = null;
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    public function testGetVersionsRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/forms/' . Uuid::v4() . '/versions');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetVersionsSuccess(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $version = $this->createTestVersion($form);
        $token = $this->loginUser($user);

        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId() . '/versions',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertIsArray($responseData['data']);
        $this->assertNotEmpty($responseData['data']);
        $this->assertEquals($version->getId(), $responseData['data'][0]['id']);
    }

    public function testGetVersionsFormNotFound(): void
    {
        $user = $this->createTestUser();
        $token = $this->loginUser($user);

        $this->client->request(
            'GET',
            '/api/forms/' . Uuid::v4() . '/versions',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testGetVersionsUnauthorized(): void
    {
        $user = $this->createTestUser();
        $otherUser = $this->createTestUser('other@example.com');
        $form = $this->createTestForm($user);
        $token = $this->loginUser($otherUser);

        $this->client->request(
            'GET',
            '/api/forms/' . $form->getId() . '/versions',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateVersionSuccess(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $token = $this->loginUser($user);

        $versionData = [
            'schema' => [
                'fields' => [
                    [
                        'id' => 'field1',
                        'type' => 'text',
                        'label' => 'Nom complet',
                        'required' => true,
                        'position' => 1,
                    ],
                    [
                        'id' => 'field2',
                        'type' => 'email',
                        'label' => 'Email',
                        'required' => true,
                        'position' => 2,
                    ],
                ],
            ],
        ];

        $this->client->request(
            'POST',
            '/api/forms/' . $form->getId() . '/versions',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
            json_encode($versionData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals($versionData['schema'], $responseData['data']['schema']);
    }

    public function testCreateVersionWithInvalidSchema(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $token = $this->loginUser($user);

        $invalidVersionData = [
            'schema' => [
                'fields' => [
                    [
                        'id' => 'field1',
                        'type' => 'invalid_type', // Type invalide
                        'label' => 'Nom',
                        'required' => true,
                        'position' => 1,
                    ],
                ],
            ],
        ];

        $this->client->request(
            'POST',
            '/api/forms/' . $form->getId() . '/versions',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
            json_encode($invalidVersionData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCreateVersionWithoutSchema(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $token = $this->loginUser($user);

        $this->client->request(
            'POST',
            '/api/forms/' . $form->getId() . '/versions',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRestoreVersionSuccess(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $version1 = $this->createTestVersion($form, 1);
        $version2 = $this->createTestVersion($form, 2);
        $token = $this->loginUser($user);

        $this->client->request(
            'POST',
            '/api/forms/' . $form->getId() . '/versions/1/restore',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertStringContainsString('restaurée avec succès', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals(3, $responseData['data']['versionNumber']); // Nouvelle version créée
    }

    public function testRestoreVersionNotFound(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $token = $this->loginUser($user);

        $this->client->request(
            'POST',
            '/api/forms/' . $form->getId() . '/versions/99/restore',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteVersionSuccess(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $version1 = $this->createTestVersion($form, 1);
        $version2 = $this->createTestVersion($form, 2);
        $token = $this->loginUser($user);

        $this->client->request(
            'DELETE',
            '/api/forms/' . $form->getId() . '/versions/1',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testDeleteLastVersionFails(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $version = $this->createTestVersion($form, 1);
        $token = $this->loginUser($user);

        $this->client->request(
            'DELETE',
            '/api/forms/' . $form->getId() . '/versions/1',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('dernière version', $responseData['message']);
    }

    public function testDeleteMostRecentVersionFails(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user);
        $version1 = $this->createTestVersion($form, 1);
        $version2 = $this->createTestVersion($form, 2);
        $token = $this->loginUser($user);

        $this->client->request(
            'DELETE',
            '/api/forms/' . $form->getId() . '/versions/2',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('plus récente', $responseData['message']);
    }

    public function testVersionOperationsUnauthorized(): void
    {
        $user = $this->createTestUser();
        $otherUser = $this->createTestUser('other@example.com');
        $form = $this->createTestForm($user);
        $version = $this->createTestVersion($form);
        $token = $this->loginUser($otherUser);

        // Test création
        $this->client->request(
            'POST',
            '/api/forms/' . $form->getId() . '/versions',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
            json_encode(['schema' => ['fields' => []]])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // Test restauration
        $this->client->request(
            'POST',
            '/api/forms/' . $form->getId() . '/versions/1/restore',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // Test suppression
        $this->client->request(
            'DELETE',
            '/api/forms/' . $form->getId() . '/versions/1',
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

    private function createTestForm(User $user): Form
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setTitle('Formulaire de test');
        $form->setDescription('Description du formulaire de test');
        $form->setStatus('DRAFT');
        $form->setUser($user);

        $this->em->persist($form);
        $this->em->flush();

        return $form;
    }

    private function createTestVersion(Form $form, int $versionNumber = 1): FormVersion
    {
        $version = new FormVersion();
        $version->setId(Uuid::v4());
        $version->setForm($form);
        $version->setVersionNumber($versionNumber);
        $version->setSchema([
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => 'Nom',
                    'required' => true,
                    'position' => 1,
                ],
            ],
        ]);

        $this->em->persist($version);
        $this->em->flush();

        return $version;
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
