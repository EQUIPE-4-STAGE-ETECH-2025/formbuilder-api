<?php

namespace App\Tests\Controller;

use App\Service\StripeWebhookService;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StripeWebhookControllerTest extends WebTestCase
{
    private $client;
    private MockObject $webhookService;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->webhookService = $this->createMock(StripeWebhookService::class);
        static::getContainer()->set(StripeWebhookService::class, $this->webhookService);
    }

    public function testHandleStripeWebhookMissingPayload(): void
    {
        $this->client->request(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            ['stripe-signature' => 'test-signature'],
            '' // Payload vide
        );

        $this->assertResponseStatusCodeSame(400);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Payload manquant', $responseData['error']);
    }

    public function testHandleStripeWebhookMissingSignature(): void
    {
        $payload = json_encode(['type' => 'test.event', 'data' => ['object' => []]]);

        $this->client->request(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            [],
            $payload
        );

        $this->assertResponseStatusCodeSame(400);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Signature manquante', $responseData['error']);
    }

    public function testHandleStripeWebhookSuccess(): void
    {
        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'status' => 'complete',
                ],
            ],
        ]);
        $signature = 'test-signature';

        $this->webhookService
            ->expects($this->once())
            ->method('handleWebhook')
            ->with($payload, $signature)
            ->willReturn(['processed' => true, 'event_type' => 'checkout.session.completed']);

        $this->client->request(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            ['HTTP_stripe-signature' => $signature],
            $payload
        );

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('success', $responseData['status']);
        $this->assertTrue($responseData['result']['processed']);
        $this->assertEquals('checkout.session.completed', $responseData['result']['event_type']);
    }

    public function testHandleStripeWebhookInvalidSignature(): void
    {
        $payload = json_encode(['type' => 'test.event']);
        $signature = 'invalid-signature';

        $this->webhookService
            ->expects($this->once())
            ->method('handleWebhook')
            ->with($payload, $signature)
            ->willThrowException(new \InvalidArgumentException('Signature invalide'));

        $this->client->request(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            ['HTTP_stripe-signature' => $signature],
            $payload
        );

        $this->assertResponseStatusCodeSame(401);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Signature invalide', $responseData['error']);
    }

    public function testHandleStripeWebhookProcessingError(): void
    {
        $payload = json_encode(['type' => 'test.event']);
        $signature = 'valid-signature';

        $this->webhookService
            ->expects($this->once())
            ->method('handleWebhook')
            ->with($payload, $signature)
            ->willThrowException(new \Exception('Erreur de traitement'));

        $this->client->request(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            ['HTTP_stripe-signature' => $signature],
            $payload
        );

        $this->assertResponseStatusCodeSame(500);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Erreur interne lors du traitement', $responseData['error']);
        $this->assertEquals('Erreur de traitement', $responseData['message']);
    }

    public function testWebhookTestEndpoint(): void
    {
        $this->client->request('GET', '/api/webhooks/stripe/test');

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('ok', $responseData['status']);
        $this->assertEquals('Endpoint webhook opérationnel', $responseData['message']);
        $this->assertArrayHasKey('timestamp', $responseData);
        $this->assertArrayHasKey('datetime', $responseData);
    }

    public function testWebhookStatusEndpoint(): void
    {
        $this->client->request('GET', '/api/webhooks/stripe/status');

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('operational', $responseData['status']);
        $this->assertEquals('Service webhook Stripe opérationnel', $responseData['message']);
        $this->assertArrayHasKey('supported_events', $responseData);
        $this->assertArrayHasKey('endpoint_url', $responseData);
        $this->assertArrayHasKey('test_url', $responseData);

        // Vérifier quelques événements supportés
        $this->assertContains('checkout.session.completed', $responseData['supported_events']);
        $this->assertContains('customer.subscription.created', $responseData['supported_events']);
        $this->assertContains('invoice.paid', $responseData['supported_events']);
    }
}
