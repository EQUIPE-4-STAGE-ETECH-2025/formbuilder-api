<?php

namespace App\Tests\EventListener;

use App\EventListener\IframeSecurityHeadersListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class IframeSecurityHeadersListenerTest extends TestCase
{
    private IframeSecurityHeadersListener $listener;

    protected function setUp(): void
    {
        $this->listener = new IframeSecurityHeadersListener();
    }

    public function testOnKernelResponseIgnoresNonMainRequest(): void
    {
        $request = Request::create('/api/public/forms/123/embed', 'GET');
        $response = new Response();
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function testOnKernelResponseIgnoresNonPublicRoutes(): void
    {
        $request = Request::create('/api/private/forms/123', 'GET');
        $response = new Response();
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function testOnKernelResponseAddsCSPForPublicRoutes(): void
    {
        $request = Request::create('/api/public/forms/123/embed', 'GET');
        $response = new Response();
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $this->assertStringContainsString('frame-ancestors *', $response->headers->get('Content-Security-Policy'));
    }

    public function testOnKernelResponseRemovesXFrameOptions(): void
    {
        $request = Request::create('/api/public/forms/123/embed', 'GET');
        $response = new Response();
        $response->headers->set('X-Frame-Options', 'DENY');
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        $this->assertFalse($response->headers->has('X-Frame-Options'));
    }

    public function testOnKernelResponseAddsCSPToExistingCSP(): void
    {
        $request = Request::create('/api/public/forms/123/embed', 'GET');
        $response = new Response();
        $response->headers->set('Content-Security-Policy', "default-src 'self'");
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors *", $csp);
    }

    public function testOnKernelResponseDoesNotDuplicateFrameAncestors(): void
    {
        $request = Request::create('/api/public/forms/123/embed', 'GET');
        $response = new Response();
        $response->headers->set('Content-Security-Policy', "frame-ancestors *");
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertEquals("frame-ancestors *", $csp);
    }

    public function testOnKernelResponseModifiesCookiesForCrossSite(): void
    {
        $request = Request::create('/api/public/forms/123/embed', 'GET');
        $response = new Response();
        $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('test_cookie', 'value'));
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertEquals('none', $cookies[0]->getSameSite());
    }

    public function testOnKernelResponseDoesNotModifyCookiesForNonPublicRoutes(): void
    {
        $request = Request::create('/api/private/forms/123', 'GET');
        $response = new Response();
        $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('test_cookie', 'value'));
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertEquals('lax', $cookies[0]->getSameSite()); // Valeur par défaut de Symfony
    }

    public function testOnKernelResponseHandlesMultipleCookies(): void
    {
        $request = Request::create('/api/public/forms/123/embed', 'GET');
        $response = new Response();
        $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('cookie1', 'value1'));
        $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('cookie2', 'value2'));
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        $cookies = $response->headers->getCookies();
        $this->assertCount(2, $cookies);
        $this->assertEquals('none', $cookies[0]->getSameSite());
        $this->assertEquals('none', $cookies[1]->getSameSite());
    }

    public function testOnKernelResponseHandlesNoCookies(): void
    {
        $request = Request::create('/api/public/forms/123/embed', 'GET');
        $response = new Response();
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $this->assertStringContainsString('frame-ancestors *', $response->headers->get('Content-Security-Policy'));
    }

    public function testOnKernelResponseWorksWithDifferentPublicRoutes(): void
    {
        $publicRoutes = [
            '/api/public/forms/123/embed',
            '/api/public/forms/123',
            '/api/forms/123/submit',
        ];

        foreach ($publicRoutes as $route) {
            $request = Request::create($route, 'GET');
            $response = new Response();
            $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

            $this->listener->onKernelResponse($event);

            $this->assertTrue($response->headers->has('Content-Security-Policy'), "Failed for route: $route");
            $this->assertStringContainsString('frame-ancestors *', $response->headers->get('Content-Security-Policy'));
        }
    }
}
