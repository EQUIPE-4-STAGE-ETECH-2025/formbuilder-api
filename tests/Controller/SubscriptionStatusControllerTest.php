<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SubscriptionStatusControllerTest extends WebTestCase
{
    private function getAuthToken($client, string $email, string $password): string
    {
        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'email' => $email,
            'password' => $password,
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('token', $data, 'Token not found in login response');

        return $data['token'];
    }

    public function testGetSubscriptionStatus(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client, 'anna@example.com', 'password');

        $subscriptionId = '550e8400-e29b-41d4-a716-446655440501'; // Premium - Anna

        $client->request('GET', "/api/subscriptions/$subscriptionId/status", [], [], [
            'HTTP_Authorization' => "Bearer $token",
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('status', $data);
        $this->assertContains($data['status'], ['ACTIVE', 'SUSPENDED', 'CANCELLED']);
    }

    public function testUpdateSubscriptionStatus(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client, 'anna@example.com', 'password');

        $subscriptionId = '550e8400-e29b-41d4-a716-446655440501';

        $client->request('PUT', "/api/subscriptions/$subscriptionId/status", [], [], [
            'HTTP_Authorization' => "Bearer $token",
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'status' => 'SUSPENDED'
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('status', $data);
        $this->assertSame('SUSPENDED', $data['status']);
    }
}
