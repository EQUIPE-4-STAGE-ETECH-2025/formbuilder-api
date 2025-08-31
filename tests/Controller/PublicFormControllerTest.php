<?php

namespace App\Tests\Controller;

use App\Entity\Form;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class PublicFormControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private array $createdEntities = [];

    protected function setUp(): void
    {
        // S'assurer qu'on démarre avec un kernel propre
        if (static::$kernel) {
            static::$kernel->shutdown();
            static::$kernel = null;
        }
        
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->createdEntities = [];
    }

    public function testGenerateEmbedCodeForPublishedForm(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user, 'PUBLISHED');

        $this->client->request(
            'GET',
            '/api/public/forms/' . $form->getId() . '/embed'
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

        $this->client->request(
            'GET',
            '/api/public/forms/' . $form->getId() . '/embed?width=800px&height=400px'
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

        $this->client->request(
            'GET',
            '/api/public/forms/' . $form->getId() . '/embed'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Ce formulaire n\'est pas disponible publiquement', $responseData['message']);
    }

    public function testGenerateEmbedCodeForNonExistentForm(): void
    {
        $fakeId = Uuid::v4()->toRfc4122();

        $this->client->request(
            'GET',
            '/api/public/forms/' . $fakeId . '/embed'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Formulaire non trouvé', $responseData['message']);
    }

    public function testShowPublishedFormSuccess(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user, 'PUBLISHED');

        $this->client->request(
            'GET',
            '/api/public/forms/' . $form->getId()
        );

        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($form->getId(), $responseData['data']['id']);
        $this->assertEquals('Formulaire de test', $responseData['data']['title']);
        $this->assertEquals('PUBLISHED', $responseData['data']['status']);

        // Vérifier que les données sensibles sont filtrées
        $this->assertArrayNotHasKey('user', $responseData['data']);
        $this->assertArrayNotHasKey('submissionsCount', $responseData['data']);
        $this->assertArrayNotHasKey('versions', $responseData['data']);
        $this->assertArrayNotHasKey('currentVersion', $responseData['data']);

        // Vérifier que les données essentielles sont présentes
        $this->assertArrayHasKey('schema', $responseData['data']);
        $this->assertArrayHasKey('publishedAt', $responseData['data']);
    }

    public function testShowDraftFormFails(): void
    {
        $user = $this->createTestUser();
        $form = $this->createTestForm($user, 'DRAFT');

        $this->client->request(
            'GET',
            '/api/public/forms/' . $form->getId()
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Ce formulaire n\'est pas disponible publiquement', $responseData['message']);
    }

    public function testShowNonExistentForm(): void
    {
        $fakeId = Uuid::v4()->toRfc4122();

        $this->client->request(
            'GET',
            '/api/public/forms/' . $fakeId
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Formulaire non trouvé', $responseData['message']);
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

        // Suivre les entités créées pour le nettoyage
        $this->createdEntities['users'][] = $user->getId();

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

        // Suivre les entités créées pour le nettoyage
        $this->createdEntities['forms'][] = $form->getId();

        return $form;
    }

    private function removeUserIfExists(string $email): void
    {
        $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            // Supprimer d'abord tous les formulaires associés
            $forms = $this->em->getRepository(Form::class)->findBy(['user' => $existingUser]);
            foreach ($forms as $form) {
                $this->em->remove($form);
            }
            $this->em->flush();
            
            // Maintenant on peut supprimer l'utilisateur
            $this->em->remove($existingUser);
            $this->em->flush();
        }
    }

    protected function tearDown(): void
    {
        // Nettoyer toutes les entités créées dans l'ordre inverse des dépendances
        $this->cleanupCreatedEntities();
        
        // Nettoyage de sécurité pour l'utilisateur de test par défaut
        $this->removeUserIfExists('test@example.com');
        
        parent::tearDown();
    }

    private function cleanupCreatedEntities(): void
    {
        // Supprimer les formulaires en premier (dépendants)
        if (isset($this->createdEntities['forms'])) {
            foreach ($this->createdEntities['forms'] as $formId) {
                $form = $this->em->getRepository(Form::class)->find($formId);
                if ($form) {
                    $this->em->remove($form);
                }
            }
            $this->em->flush();
        }

        // Supprimer les utilisateurs ensuite
        if (isset($this->createdEntities['users'])) {
            foreach ($this->createdEntities['users'] as $userId) {
                $user = $this->em->getRepository(User::class)->find($userId);
                if ($user) {
                    $this->em->remove($user);
                }
            }
            $this->em->flush();
        }
    }
}
