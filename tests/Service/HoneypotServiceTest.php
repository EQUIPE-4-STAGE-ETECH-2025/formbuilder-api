<?php

namespace App\Tests\Service;

use App\Service\HoneypotService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class HoneypotServiceTest extends TestCase
{
    private HoneypotService $honeypotService;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->honeypotService = new HoneypotService($this->logger);
    }

    public function testIsBotReturnsFalseWithEmptyField(): void
    {
        // Utiliser un timestamp plus ancien (plus de 2 secondes)
        $oldTime = time() - 10;
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_HONEYPOT_FIELD' => (string)$oldTime,
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['_website_url' => '']));

        $result = $this->honeypotService->isBot($request);

        $this->assertFalse($result);
    }

    public function testIsBotReturnsFalseWithValidTiming(): void
    {
        // Utiliser un timestamp plus ancien (plus de 2 secondes)
        $oldTime = time() - 10;
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_HONEYPOT_FIELD' => (string)$oldTime,
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['_website_url' => '']));

        $result = $this->honeypotService->isBot($request);

        $this->assertFalse($result);
    }

    public function testIsBotReturnsTrueWithFilledField(): void
    {
        $oldTime = time() - 10;
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_HONEYPOT_FIELD' => (string)$oldTime,
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['_website_url' => 'spam@example.com']));

        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('Champ honeypot rempli'));

        $result = $this->honeypotService->isBot($request);

        $this->assertTrue($result);
    }

    public function testIsBotReturnsFalseWithMissingHeader(): void
    {
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['_website_url' => '']));

        $result = $this->honeypotService->isBot($request);

        $this->assertFalse($result);
    }

    public function testIsBotReturnsTrueWithMissingField(): void
    {
        $oldTime = time() - 10;
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_HONEYPOT_FIELD' => (string)$oldTime,
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('Champ honeypot rempli'));

        $result = $this->honeypotService->isBot($request);

        $this->assertTrue($result);
    }

    public function testIsBotReturnsTrueWithTooFastSubmission(): void
    {
        // Timestamp très récent (moins de 1 seconde)
        $recentTime = time() - 0;
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_HONEYPOT_FIELD' => (string)$recentTime,
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['_website_url' => '']));

        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('Soumission trop rapide'));

        $result = $this->honeypotService->isBot($request);

        $this->assertTrue($result);
    }

    public function testIsBotReturnsFalseWithValidTimingAfterDelay(): void
    {
        // Timestamp valide (plus de 2 secondes)
        $validTime = time() - 5;
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_HONEYPOT_FIELD' => (string)$validTime,
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['_website_url' => '']));

        $result = $this->honeypotService->isBot($request);

        $this->assertFalse($result);
    }

    public function testIsBotReturnsTrueWithSuspiciousUserAgent(): void
    {
        $oldTime = time() - 10;
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_HONEYPOT_FIELD' => (string)$oldTime,
            'HTTP_USER_AGENT' => 'bot/1.0',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['_website_url' => '']));

        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('User-Agent suspect'));

        $result = $this->honeypotService->isBot($request);

        $this->assertTrue($result);
    }

    public function testIsBotReturnsTrueWithEmptyUserAgent(): void
    {
        $oldTime = time() - 10;
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_HONEYPOT_FIELD' => (string)$oldTime,
            'HTTP_USER_AGENT' => '', // User-Agent vide
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['_website_url' => '']));

        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('User-Agent suspect'));

        $result = $this->honeypotService->isBot($request);

        $this->assertTrue($result);
    }

    public function testIsBotReturnsFalseWithNormalUserAgent(): void
    {
        $oldTime = time() - 10;
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_HONEYPOT_FIELD' => (string)$oldTime,
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['_website_url' => '']));

        $result = $this->honeypotService->isBot($request);

        $this->assertFalse($result);
    }

    public function testIsBotWithEmptyRequest(): void
    {
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('Champ honeypot rempli'));

        $result = $this->honeypotService->isBot($request);

        $this->assertTrue($result);
    }

    public function testIsBotWithInvalidJson(): void
    {
        $oldTime = time() - 10;
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_HONEYPOT_FIELD' => (string)$oldTime,
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json');

        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('Champ honeypot rempli'));

        $result = $this->honeypotService->isBot($request);

        $this->assertTrue($result);
    }

    public function testIsBotWithNonArrayData(): void
    {
        $oldTime = time() - 10;
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_HONEYPOT_FIELD' => (string)$oldTime,
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode('string'));

        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('Champ honeypot rempli'));

        $result = $this->honeypotService->isBot($request);

        $this->assertTrue($result);
    }

    public function testGetHoneypotFieldName(): void
    {
        $fieldName = $this->honeypotService->getHoneypotFieldName();
        $this->assertEquals('_website_url', $fieldName);
    }

    public function testGetHoneypotHeader(): void
    {
        $headerName = $this->honeypotService->getHoneypotHeader();
        $this->assertEquals('X-Honeypot-Field', $headerName);
    }
}
