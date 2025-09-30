<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute les headers de sécurité nécessaires pour l'intégration iframe
 * Appliqué uniquement aux routes publiques
 */
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onKernelResponse')]
class IframeSecurityHeadersListener
{
    private const PUBLIC_ROUTES = [
        '/api/public/',
        '/api/forms/',
    ];

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $path = $request->getPathInfo();

        // Appliquer les headers uniquement aux routes publiques
        if (! $this->isPublicRoute($path)) {
            return;
        }

        // Pour les routes publiques, permettre l'intégration dans des iframes
        // IMPORTANT : Ceci est nécessaire pour Google Forms-like behavior
        
        // Supprimer X-Frame-Options (incompatible avec CSP frame-ancestors)
        $response->headers->remove('X-Frame-Options');
        
        // Permettre l'intégration dans n'importe quel site via CSP
        // Note : 'frame-ancestors *' permet l'embedding depuis n'importe quel domaine
        $currentCsp = $response->headers->get('Content-Security-Policy');
        
        if ($currentCsp !== null) {
            // Si un CSP existe déjà, ajouter frame-ancestors
            if (! str_contains($currentCsp, 'frame-ancestors')) {
                $response->headers->set(
                    'Content-Security-Policy',
                    $currentCsp . '; frame-ancestors *'
                );
            }
        } else {
            // Sinon, créer un CSP minimal
            $response->headers->set(
                'Content-Security-Policy',
                "frame-ancestors *"
            );
        }

        // Permettre les cookies cross-site (nécessaire pour les iframes)
        // Note : SameSite=None requiert Secure (HTTPS)
        $cookies = $response->headers->getCookies();
        foreach ($cookies as $cookie) {
            if ($cookie->getSameSite() !== 'none') {
                $response->headers->removeCookie($cookie->getName());
                $response->headers->setCookie(
                    new \Symfony\Component\HttpFoundation\Cookie(
                        $cookie->getName(),
                        $cookie->getValue(),
                        $cookie->getExpiresTime(),
                        $cookie->getPath(),
                        $cookie->getDomain(),
                        $cookie->isSecure() || $request->isSecure(), // Force secure si HTTPS
                        $cookie->isHttpOnly(),
                        false,
                        'none' // SameSite=None pour les iframes
                    )
                );
            }
        }
    }

    private function isPublicRoute(string $path): bool
    {
        foreach (self::PUBLIC_ROUTES as $publicRoute) {
            if (str_starts_with($path, $publicRoute)) {
                return true;
            }
        }

        return false;
    }
}
