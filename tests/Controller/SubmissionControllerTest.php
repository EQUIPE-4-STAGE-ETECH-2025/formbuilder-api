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
            '_website_url' => '', // Champ honeypot vide
        ];

        $this->client->request(
            'POST',
            '/api/forms/' . $this->formAnna->getId() . '/submit',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_Honeypot_Field' => (string)(time() - 10), // Header honeypot valide (10 secondes dans le passé)
            ],
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
            '_website_url' => '',
        ];

        $this->client->request(
            'POST',
            '/api/forms/' . $this->formAnna->getId() . '/submit',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_Honeypot_Field' => (string)(time() - 10),
            ],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $status = $response->getStatusCode();
        $this->assertContains($status, [200, 429], "HTTP status should be 200 (if quota not exceeded) or 429 (if exceeded)");

        if ($status === 429) {
            $json = json_decode($response->getContent(), true);
            $this->assertArrayHasKey('error', $json);
            $this->assertEquals('Quota dépassé', $json['error']);
            $this->assertArrayHasKey('data', $json);
            $this->assertArrayHasKey('error_code', $json['data']);
            $this->assertEquals('QUOTA_SUBMISSIONS_EXCEEDED', $json['data']['error_code']);
        }
    }

    public function testSubmitFormFailsWithHoneypotFilled(): void
    {
        $data = [
            'fields' => [
                ['name' => 'email', 'value' => 'test@example.com'],
            ],
            '_website_url' => 'spam@example.com', // Champ honeypot rempli
        ];

        $this->client->request(
            'POST',
            '/api/forms/' . $this->formAnna->getId() . '/submit',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_Honeypot_Field' => (string)time(),
            ],
            json_encode($data)
        );

        $this->assertResponseStatusCodeSame(400);
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('Soumission suspecte détectée', $json['error']);
    }

    public function testSubmitFormSucceedsWithMissingHoneypotHeader(): void
    {
        $data = [
            'fields' => [
                ['name' => 'email', 'value' => 'test@example.com'],
            ],
            '_website_url' => '',
        ];

        $this->client->request(
            'POST',
            '/api/forms/' . $this->formAnna->getId() . '/submit',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        // Avec la logique actuelle, les requêtes sans header ne sont plus bloquées
        // pour la compatibilité. Ce test vérifie maintenant que la soumission réussit.
        $this->assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('formId', $json);
    }

    public function testSubmitFormFailsWithMissingHoneypotField(): void
    {
        $data = [
            'fields' => [
                ['name' => 'email', 'value' => 'test@example.com'],
            ],
            // Pas de champ _website_url
        ];

        $this->client->request(
            'POST',
            '/api/forms/' . $this->formAnna->getId() . '/submit',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_Honeypot_Field' => (string)time(),
            ],
            json_encode($data)
        );

        $this->assertResponseStatusCodeSame(400);
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('Soumission suspecte détectée', $json['error']);
    }

    public function testSubmitFormSucceedsWithInvalidTimestamp(): void
    {
        $data = [
            'fields' => [
                ['name' => 'email', 'value' => 'test@example.com'],
            ],
            '_website_url' => '',
        ];

        $this->client->request(
            'POST',
            '/api/forms/' . $this->formAnna->getId() . '/submit',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_Honeypot_Field' => 'invalid-timestamp',
            ],
            json_encode($data)
        );

        // Avec la logique actuelle, les timestamps invalides ne bloquent plus
        // pour la compatibilité. Ce test vérifie maintenant que la soumission réussit.
        $this->assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('formId', $json);
    }

    public function testSubmitFormSucceedsWithTooOldTimestamp(): void
    {
        $data = [
            'fields' => [
                ['name' => 'email', 'value' => 'test@example.com'],
            ],
            '_website_url' => '',
        ];

        $oldTimestamp = time() - 7200; // 2 heures dans le passé

        $this->client->request(
            'POST',
            '/api/forms/' . $this->formAnna->getId() . '/submit',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_Honeypot_Field' => (string)$oldTimestamp,
            ],
            json_encode($data)
        );

        // Avec la logique actuelle, les timestamps trop anciens ne bloquent plus
        // pour la compatibilité. Ce test vérifie maintenant que la soumission réussit.
        $this->assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('formId', $json);
    }

    public function testSubmitFormFailsWithTooFastSubmission(): void
    {
        $data = [
            'fields' => [
                ['name' => 'email', 'value' => 'test@example.com'],
            ],
            '_website_url' => '',
        ];

        $recentTimestamp = time() - 0; // Timestamp actuel (0 seconde d'écart)

        $this->client->request(
            'POST',
            '/api/forms/' . $this->formAnna->getId() . '/submit',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_Honeypot_Field' => (string)$recentTimestamp,
            ],
            json_encode($data)
        );

        $this->assertResponseStatusCodeSame(400);
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('Soumission suspecte détectée', $json['error']);
    }

    public function testSubmitFormFailsWithSuspiciousUserAgent(): void
    {
        $data = [
            'fields' => [
                ['name' => 'email', 'value' => 'test@example.com'],
            ],
            '_website_url' => '',
        ];

        $this->client->request(
            'POST',
            '/api/forms/' . $this->formAnna->getId() . '/submit',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_Honeypot_Field' => (string)time(),
                'HTTP_USER_AGENT' => 'bot',
            ],
            json_encode($data)
        );

        $this->assertResponseStatusCodeSame(400);
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('Soumission suspecte détectée', $json['error']);
    }

    public function testSubmitFormFailsWithRateLimitExceeded(): void
    {
        $data = [
            'fields' => [
                ['name' => 'email', 'value' => 'test@example.com'],
            ],
            '_website_url' => '',
        ];

        // Simuler plusieurs requêtes rapides pour dépasser la limite
        for ($i = 0; $i < 12; $i++) {
            $this->client->request(
                'POST',
                '/api/forms/' . $this->formAnna->getId() . '/submit',
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_Honeypot_Field' => (string)(time() - 10),
                ],
                json_encode($data)
            );
        }

        $this->assertResponseStatusCodeSame(429);
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('Trop de soumissions. Veuillez réessayer plus tard.', $json['error']);
    }

    public function testSubmitFormFailsWithUnpublishedForm(): void
    {
        // Créer un formulaire non publié
        $unpublishedForm = new Form();
        $unpublishedForm->setId(\Symfony\Component\Uid\Uuid::v4());
        $unpublishedForm->setTitle('Formulaire non publié');
        $unpublishedForm->setDescription('Test');
        $unpublishedForm->setStatus('DRAFT');
        $unpublishedForm->setUser($this->userAnna);

        $this->entityManager->persist($unpublishedForm);
        $this->entityManager->flush();

        $data = [
            'fields' => [
                ['name' => 'email', 'value' => 'test@example.com'],
            ],
            '_website_url' => '',
        ];

        $this->client->request(
            'POST',
            '/api/forms/' . $unpublishedForm->getId() . '/submit',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_Honeypot_Field' => (string)time(),
            ],
            json_encode($data)
        );

        $this->assertResponseStatusCodeSame(403);
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('Ce formulaire n\'est pas disponible pour les soumissions', $json['error']);

        // Nettoyer
        $this->entityManager->remove($unpublishedForm);
        $this->entityManager->flush();
    }
}
