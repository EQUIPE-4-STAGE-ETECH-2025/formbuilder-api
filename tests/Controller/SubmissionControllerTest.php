<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SubmissionControllerTest extends WebTestCase
{
    private function getAuthToken($client, string $email, string $password): string
    {
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => $password,
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        return $data['token'] ?? '';
    }

    public function testSubmitFormWithValidData(): void
    {
        $client = static::createClient();

        $formId = '550e8400-e29b-41d4-a716-446655440301'; // Appartient à Anna
        $data = [
            'data' => [
                '550e8400-e29b-41d4-a716-446655441003' => 'Jean Dupont',
                '550e8400-e29b-41d4-a716-446655441004' => 'jean@example.com',
                '550e8400-e29b-41d4-a716-446655441005' => 'Test message',
            ]
        ];

        $client->request('POST', "/api/forms/$formId/submit", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data));
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testListSubmissionsAsOwner(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client, 'anna@example.com', 'password');
        $formId = '550e8400-e29b-41d4-a716-446655440301'; // Appartient à Anna

        $client->request('GET', "/api/forms/$formId/submissions", [], [], [
            'HTTP_Authorization' => "Bearer $token",
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testListSubmissionsAsUnauthorizedUser(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client, 'lucas@example.com', 'password');

        // Formulaire qui appartient à Élodie, donc Lucas n'est pas autorisé
        $formId = '550e8400-e29b-41d4-a716-446655440304';

        $client->request('GET', "/api/forms/$formId/submissions", [], [], [
            'HTTP_Authorization' => "Bearer $token",
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testExportSubmissionsAsOwner(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client, 'anna@example.com', 'password');
        $formId = '550e8400-e29b-41d4-a716-446655440301';

        $client->request('GET', "/api/forms/$formId/submissions/export", [], [], [
            'HTTP_Authorization' => "Bearer $token",
        ]);

        $this->assertResponseIsSuccessful();

        $this->assertStringContainsString('text/csv', $client->getResponse()->headers->get('content-type'));
    }
}
