<?php

namespace App\Tests\Service;

use App\Service\RateLimitService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class RateLimitServiceTest extends TestCase
{
    private RateLimitService $rateLimitService;
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();

        // Configuration pour le rate limiter horaire (10 requêtes par heure)
        $hourlyConfig = [
            'id' => 'form_submission_hourly',
            'policy' => 'sliding_window',
            'limit' => 10,
            'interval' => '1 hour',
        ];
        $hourlyLimiter = new \Symfony\Component\RateLimiter\RateLimiterFactory(
            $hourlyConfig,
            $this->storage
        );

        // Configuration pour le rate limiter journalier (100 requêtes par jour)
        $dailyConfig = [
            'id' => 'form_submission_daily',
            'policy' => 'fixed_window',
            'limit' => 100,
            'interval' => '1 day',
        ];
        $dailyLimiter = new \Symfony\Component\RateLimiter\RateLimiterFactory(
            $dailyConfig,
            $this->storage
        );

        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $this->rateLimitService = new RateLimitService($hourlyLimiter, $dailyLimiter, $logger);
    }

    public function testCanSubmitFormSuccessOnFirstRequest(): void
    {
        $request = Request::create('/api/forms/123/submit', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        $result = $this->rateLimitService->canSubmitForm($request);

        $this->assertTrue($result);
    }

    public function testCanSubmitFormSuccessWithinHourlyLimit(): void
    {
        $request = Request::create('/api/forms/123/submit', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        // Simuler plusieurs requêtes dans la limite horaire
        for ($i = 0; $i < 5; $i++) {
            $result = $this->rateLimitService->canSubmitForm($request);
            $this->assertTrue($result, "Request $i should be allowed");
        }
    }

    public function testCanSubmitFormFailsWhenHourlyLimitExceeded(): void
    {
        $request = Request::create('/api/forms/123/submit', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        // Simuler plus de 10 requêtes par heure
        for ($i = 0; $i < 10; $i++) {
            $result = $this->rateLimitService->canSubmitForm($request);
            $this->assertTrue($result, "Request $i should be allowed");
        }

        // La 11ème requête devrait être bloquée
        $result = $this->rateLimitService->canSubmitForm($request);
        $this->assertFalse($result);
    }

    public function testCanSubmitFormFailsWhenDailyLimitExceeded(): void
    {
        // Créer un rate limiter avec une limite journalière plus faible pour le test
        $dailyConfig = [
            'id' => 'form_submission_daily_test',
            'policy' => 'fixed_window',
            'limit' => 5, // Limite plus faible pour le test
            'interval' => '1 day',
        ];
        $dailyLimiter = new \Symfony\Component\RateLimiter\RateLimiterFactory(
            $dailyConfig,
            $this->storage
        );

        $hourlyConfig = [
            'id' => 'form_submission_hourly_test',
            'policy' => 'sliding_window',
            'limit' => 10,
            'interval' => '1 hour',
        ];
        $hourlyLimiter = new \Symfony\Component\RateLimiter\RateLimiterFactory(
            $hourlyConfig,
            $this->storage
        );

        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $rateLimitService = new RateLimitService($hourlyLimiter, $dailyLimiter, $logger);

        $request = Request::create('/api/forms/123/submit', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        // Simuler 5 requêtes (limite journalière)
        for ($i = 0; $i < 5; $i++) {
            $result = $rateLimitService->canSubmitForm($request);
            $this->assertTrue($result, "Request $i should be allowed");
        }

        // La 6ème requête devrait être bloquée par la limite journalière
        $result = $rateLimitService->canSubmitForm($request);
        $this->assertFalse($result);
    }

    public function testCanSubmitFormDifferentIpsAreIndependent(): void
    {
        $request1 = Request::create('/api/forms/123/submit', 'POST');
        $request1->server->set('REMOTE_ADDR', '192.168.1.1');

        $request2 = Request::create('/api/forms/123/submit', 'POST');
        $request2->server->set('REMOTE_ADDR', '192.168.1.2');

        // IP 1 fait 10 requêtes
        for ($i = 0; $i < 10; $i++) {
            $result = $this->rateLimitService->canSubmitForm($request1);
            $this->assertTrue($result);
        }

        // IP 1 est maintenant bloquée
        $result = $this->rateLimitService->canSubmitForm($request1);
        $this->assertFalse($result);

        // IP 2 peut encore faire des requêtes
        $result = $this->rateLimitService->canSubmitForm($request2);
        $this->assertTrue($result);
    }

    public function testCanSubmitFormHandlesMissingIpAddress(): void
    {
        $request = Request::create('/api/forms/123/submit', 'POST');
        // Pas d'IP définie

        $result = $this->rateLimitService->canSubmitForm($request);

        // Devrait utiliser une IP par défaut et fonctionner
        $this->assertTrue($result);
    }

    public function testCanSubmitFormHandlesXForwardedFor(): void
    {
        $request = Request::create('/api/forms/123/submit', 'POST');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->headers->set('X-Forwarded-For', '203.0.113.1');

        $result = $this->rateLimitService->canSubmitForm($request);

        $this->assertTrue($result);
    }

    public function testCanSubmitFormHandlesMultipleXForwardedFor(): void
    {
        $request = Request::create('/api/forms/123/submit', 'POST');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->headers->set('X-Forwarded-For', '203.0.113.1, 70.41.3.18, 150.172.238.178');

        $result = $this->rateLimitService->canSubmitForm($request);

        $this->assertTrue($result);
    }

    public function testGetRateLimitInfoReturnsCorrectData(): void
    {
        $request = Request::create('/api/forms/123/submit', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        $info = $this->rateLimitService->getRateLimitInfo($request);

        $this->assertArrayHasKey('hourly', $info);
        $this->assertArrayHasKey('daily', $info);
        $this->assertArrayHasKey('limit', $info['hourly']);
        $this->assertArrayHasKey('remaining', $info['hourly']);
        $this->assertArrayHasKey('limit', $info['daily']);
        $this->assertArrayHasKey('remaining', $info['daily']);
    }

    public function testGetRateLimitInfoUpdatesAfterConsumption(): void
    {
        $request = Request::create('/api/forms/123/submit', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        // Consommer une requête
        $this->rateLimitService->canSubmitForm($request);

        $info = $this->rateLimitService->getRateLimitInfo($request);

        $this->assertEquals(9, $info['hourly']['remaining']);
        $this->assertEquals(99, $info['daily']['remaining']);
    }

    public function testCanSubmitFormWithEmptyRequest(): void
    {
        $request = new Request();

        $result = $this->rateLimitService->canSubmitForm($request);

        // Devrait fonctionner même sans IP
        $this->assertTrue($result);
    }
}
