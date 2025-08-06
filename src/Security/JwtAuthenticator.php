<?php

namespace App\Security;

use App\Repository\UserRepository;
use App\Service\JwtService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $authHeader = $request->headers->get('Authorization');

        return null !== $authHeader && str_starts_with($authHeader, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthenticationException('Token manquant ou invalide');
        }

        $token = substr($authHeader, 7);
        $payload = $this->jwtService->validateToken($token);

        $userId = $payload->id ?? null;

        if (! $userId) {
            throw new AuthenticationException('Token invalide : ID manquant');
        }

        return new SelfValidatingPassport(new UserBadge($userId, function ($userId) {
            $user = $this->userRepository->find($userId);
            if (! $user) {
                throw new UserNotFoundException("Utilisateur $userId non trouvé.");
            }

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, Response|TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $content = json_encode([
            'error' => 'Authentification échouée',
            'details' => $exception->getMessage(),
        ]);

        if (false === $content) {
            $content = '{"error": "Erreur de sérialisation JSON"}';
        }

        return new Response($content, Response::HTTP_UNAUTHORIZED, [
            'Content-Type' => 'application/json',
        ]);
    }
}