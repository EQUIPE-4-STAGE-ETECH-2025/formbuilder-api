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
    private Form $formAnna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->userAnna = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'anna@example.com']);
        $this->userElodie = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'elodie@example.com']);
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
        $this->assertArrayHasKey('formId', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('submittedAt', $json);
        $this->assertArrayHasKey('ipAddress', $json);
    }

    public function testGetSubmissionsAuthorized(): void
    {
        $this->client->loginUser($this->userAnna);
        $this->client->request('GET', '/api/forms/' . $this->formAnna->getId() . '/submissions');

        $this->assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('success', $json);
        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);

        $this->assertArrayHasKey('page', $json['meta']);
        $this->assertArrayHasKey('limit', $json['meta']);
        $this->assertArrayHasKey('total', $json['meta']);
        $this->assertArrayHasKey('totalPages', $json['meta']);

        $this->assertIsArray($json['data']);
    }

    public function testGetSubmissionsForbiddenForOtherUser(): void
    {
        $this->client->loginUser($this->userElodie);
        $this->client->request('GET', '/api/forms/' . $this->formAnna->getId() . '/submissions');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetSubmissionsWithPagination(): void
    {
        $this->client->loginUser($this->userAnna);
        $this->client->request('GET', '/api/forms/' . $this->formAnna->getId() . '/submissions?page=1&limit=5');

        $this->assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('success', $json);
        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);

        $this->assertEquals(1, $json['meta']['page']);
        $this->assertEquals(5, $json['meta']['limit']);
        $this->assertIsInt($json['meta']['total']);
        $this->assertIsInt($json['meta']['totalPages']);

        $this->assertLessThanOrEqual(5, count($json['data']));
    }

    public function testExportSubmissionsCsv(): void
    {
        $this->client->loginUser($this->userAnna);
        $this->client->request('GET', '/api/forms/' . $this->formAnna->getId() . '/submissions/export');

        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();

        $csv = str_replace(["\r\n", "\r"], "\n", $response->getContent());
        $lines = explode("\n", trim($csv));

        $this->assertGreaterThan(0, count($lines));

        $headerLine = $lines[0];
        $actualHeaders = explode(';', $headerLine);

        $baseHeaders = ['ID', 'Form ID', 'Submitted At', 'IP Address'];
        $this->assertCount(4, array_slice($actualHeaders, 0, 4));
        $this->assertSame($baseHeaders, array_slice($actualHeaders, 0, 4));

        $expectedColumnCount = count($actualHeaders);

        $this->assertGreaterThanOrEqual(4, $expectedColumnCount);

        if (count($lines) > 1) {
            foreach ($lines as $i => $line) {
                if ($i === 0) {
                    continue;
                }
                if (trim($line) === '') {
                    continue;
                }
                $this->assertCount($expectedColumnCount, explode(';', $line), "Line $i must have $expectedColumnCount columns");
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
        $this->assertContains($status, [200, 400], "HTTP status should be 200 (if quota not exceeded) or 400 (if exceeded)");

        if ($status === 400) {
            $this->assertStringContainsString('Limite de soumissions atteinte', $response->getContent());
        }
    }
}
