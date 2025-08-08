<?php

namespace App\Controller;

use App\Dto\LoginDto;
use App\Dto\RegisterDto;
use App\Service\AuthService;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    #[Route('/api/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        ValidatorInterface $validator,
        AuthService $authService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $dto = (new LoginDto())
            ->setEmail($data['email'] ?? '')
            ->setPassword($data['password'] ?? '');

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json(['errors' => $errorMessages], 422);
        }

        $authData = $authService->login($dto);

        return $this->json($authData);
    }

    #[Route('/api/auth/me', name: 'auth_me', methods: ['GET'])]
    public function me(Request $request, AuthService $authService): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->json(['error' => 'Token manquant'], 401);
        }

        $token = substr($authHeader, 7);

        try {
            $dto = $authService->getCurrentUser($token);

            return $this->json($dto->toArray());
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }

    #[Route('/api/auth/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(Request $request, AuthService $authService): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->json(['error' => 'Token manquant'], 401);
        }

        $token = substr($authHeader, 7);

        try {
            $authService->logout($token);

            return $this->json(['message' => 'Déconnexion réussie']);
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }

    #[Route('/api/auth/verify-email', name: 'auth_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request, AuthService $authService): JsonResponse
    {
        $token = $request->query->get('token');

        if (!$token) {
            return $this->json(['error' => 'Token manquant'], 400);
        }

        try {
            $authService->verifyEmail($token);
            return $this->json(['message' => 'Email vérifié avec succès']);
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
