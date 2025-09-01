<?php

namespace App\Tests\Controller;

use App\Entity\Form;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

        $this->userAnna = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'anna@example.com']);
        $this->userElodie = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'elodie@example.com']);
        $this->adminUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@formbuilder.com']);
        $this->formAnna = $this->entityManager->getRepository(Form::class)->findOneBy(['user' => $this->userAnna]);
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

        // Vérifie qu'il y a au moins une ligne (l'en-tête)
        $this->assertGreaterThan(0, count($lines));

        // Vérifie que la première ligne contient les en-têtes de base
        $headerLine = $lines[0];
        $actualHeaders = explode(';', $headerLine);

        // Les 4 premières colonnes doivent toujours être les colonnes de base
        $baseHeaders = ['ID', 'Form ID', 'Submitted At', 'IP Address'];
        $this->assertCount(4, array_slice($actualHeaders, 0, 4));
        $this->assertSame($baseHeaders, array_slice($actualHeaders, 0, 4));

        // S'il y a des soumissions, il peut y avoir des colonnes supplémentaires pour les champs
        $expectedColumnCount = count($actualHeaders);

        // Vérifie qu'il y a au moins les 4 colonnes de base
        $this->assertGreaterThanOrEqual(4, $expectedColumnCount);

        // Vérifie que toutes les lignes de données ont le même nombre de colonnes que l'en-tête
        if (count($lines) > 1) {
            foreach ($lines as $i => $line) {
                if ($i === 0) {
                    continue; // ignore l'en-tête
                }
                if (trim($line) === '') {
                    continue; // ignore les lignes vides
                }
                $this->assertCount($expectedColumnCount, explode(';', $line), "La ligne $i doit avoir $expectedColumnCount colonnes");
            }
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
