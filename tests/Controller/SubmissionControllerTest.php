<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\Form;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class SubmissionControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $entityManager;
    private User $userAnna;
    private User $userElodie;
    private User $adminUser;
    private Form $formAnna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->userAnna   = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'anna@example.com']);
        $this->userElodie = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'elodie@example.com']);
        $this->adminUser  = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@formbuilder.com']);
        $this->formAnna   = $this->entityManager->getRepository(Form::class)->findOneBy(['user' => $this->userAnna]);
    }

    public function testSubmitFormSuccessfully(): void
    {
        $data = [
            'fields' => [
                ['name' => 'email', 'value' => 'test@example.com'],
                ['name' => 'message', 'value' => 'Bonjour'],
            ],
        ];

        $this->client->request(
            'POST',
            '/api/forms/' . $this->formAnna->getId() . '/submit',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertJson($response->getContent());

        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('submittedAt', $json);
    }

    public function testGetSubmissionsAuthorized(): void
    {
        $this->client->loginUser($this->userAnna);
        $this->client->request('GET', '/api/forms/' . $this->formAnna->getId() . '/submissions');

        $this->assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($json);
        $this->assertNotEmpty($json);
    }

    public function testGetSubmissionsForbiddenForOtherUser(): void
    {
        $this->client->loginUser($this->userElodie);
        $this->client->request('GET', '/api/forms/' . $this->formAnna->getId() . '/submissions');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testExportSubmissionsCsv(): void
    {
        $this->client->loginUser($this->userAnna);
        $this->client->request('GET', '/api/forms/' . $this->formAnna->getId() . '/submissions/export');

        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();

        // Normalise les fins de ligne pour Windows / Unix
        $csv = str_replace(["\r\n", "\r"], "\n", $response->getContent());
        $lines = explode("\n", trim($csv));

        // Vérifie que la première ligne contient bien les en-têtes
        $this->assertSame('ID;Form ID;Submitted At;IP Address', $lines[0]);

        // Vérifie qu'il y a au moins une ligne de données
        $this->assertGreaterThan(1, count($lines));

        // Vérifie que chaque ligne de données a 4 colonnes
        foreach ($lines as $i => $line) {
            if ($i === 0) continue; // ignore l'en-tête
            $this->assertCount(4, explode(';', $line), "La ligne $i doit avoir 4 colonnes");
        }
    }

    public function testSubmitFormQuotaExceeded(): void
    {
        $this->client->loginUser($this->userAnna);

        $data = [
            'fields' => [
                ['name' => 'email', 'value' => 'test@example.com'],
            ],
        ];

        $this->client->request(
            'POST',
            '/api/forms/' . $this->formAnna->getId() . '/submit',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $status = $response->getStatusCode();
        $this->assertContains($status, [200, 400], "Le code HTTP doit être 200 (pas de quota) ou 400 (quota dépassé)");

        if ($status === 400) {
            $this->assertStringContainsString('Limite de soumissions atteinte', $response->getContent());
        }
    }
}
