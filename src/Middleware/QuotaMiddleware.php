<?php

namespace App\Middleware;

use App\Repository\UserRepository;
use App\Service\QuotaService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class QuotaMiddleware
{
    private const QUOTA_PROTECTED_ROUTES = [
        'POST:/api/forms' => ['action_type' => 'create_form', 'quantity' => 1],
        'POST:/api/forms/{id}/submit' => ['action_type' => 'submit_form', 'quantity' => 1],
        'POST:/api/forms/{id}/files' => ['action_type' => 'upload_file', 'quantity_from' => 'file_size'],
    ];

    public function __construct(
        private QuotaService $quotaService,
        private UserRepository $userRepository,
        private TokenStorageInterface $tokenStorage,
        private LoggerInterface $logger
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (! $event->isMainRequest()) {
            return;
        }

        $routeKey = $this->getRouteKey($request);
        if (! $this->isQuotaProtectedRoute($routeKey)) {
            return;
        }

        $user = $this->getAuthenticatedUser();
        if (! $user) {
            return;
        }

        $quotaConfig = $this->getQuotaConfigForRoute($routeKey, $request);
        if (! $quotaConfig) {
            return;
        }

        try {
            $this->quotaService->enforceQuotaLimit(
                $user,
                $quotaConfig['action_type'],
                $quotaConfig['quantity']
            );

        } catch (\RuntimeException $e) {
            $response = new JsonResponse([
                'success' => false,
                'error' => 'Quota dépassé',
                'details' => $e->getMessage(),
            ], Response::HTTP_TOO_MANY_REQUESTS);

            $event->setResponse($response);

            return;

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la vérification des quotas', [
                'user_id' => $user->getId(),
                'route' => $routeKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isQuotaProtectedRoute(string $routeKey): bool
    {
        foreach (self::QUOTA_PROTECTED_ROUTES as $pattern => $config) {
            if ($this->matchesRoutePattern($routeKey, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getQuotaConfigForRoute(string $routeKey, Request $request): ?array
    {
        foreach (self::QUOTA_PROTECTED_ROUTES as $pattern => $config) {
            if ($this->matchesRoutePattern($routeKey, $pattern)) {
                return $this->resolveQuotaConfig($config, $request);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function resolveQuotaConfig(array $config, Request $request): array
    {
        $resolvedConfig = $config;

        if (isset($config['quantity_from']) && $config['quantity_from'] === 'file_size') {
            $resolvedConfig['quantity'] = $this->getFileSizeFromRequest($request);
            unset($resolvedConfig['quantity_from']);
        }

        return $resolvedConfig;
    }

    private function getFileSizeFromRequest(Request $request): int
    {
        $files = $request->files->all();
        $totalSize = 0;

        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $totalSize += $file->getSize();
            }
        }

        return (int) ceil($totalSize / (1024 * 1024)); // Convertir en MB
    }

    private function matchesRoutePattern(string $routeKey, string $pattern): bool
    {
        $regexPattern = preg_replace('/\{[^}]+\}/', '[^/]+', $pattern);
        $regexPattern = str_replace('/', '\/', $regexPattern ?? '');
        $regexPattern = '/^' . $regexPattern . '$/';

        return preg_match($regexPattern, $routeKey) === 1;
    }

    private function getRouteKey(Request $request): string
    {
        return $request->getMethod() . ':' . $request->getPathInfo();
    }

    private function getAuthenticatedUser(): ?\App\Entity\User
    {
        $token = $this->tokenStorage->getToken();
        if (! $token) {
            return null;
        }

        $user = $token->getUser();
        if (! $user instanceof UserInterface) {
            return null;
        }

        return $this->userRepository->findOneBy(['email' => $user->getUserIdentifier()]);
    }
}
