<?php

namespace App\Controller;

use App\Dto\ChangePasswordDto;
use App\Dto\LoginDto;
use App\Dto\RegisterDto;
use App\Dto\ResetPasswordDto;
use App\Service\AuthService;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/api/auth/register', name: 'auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        ValidatorInterface $validator,
        AuthService $authService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $dto = (new RegisterDto())
            ->setFirstName($data['firstName'] ?? '')
            ->setLastName($data['lastName'] ?? '')
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

        try {
            $result = $authService->register($dto);

            return $this->json([
                'message' => 'Inscription réussie. Vérifiez votre email pour activer votre compte.',
                'user' => $result['user']->toArray(),
            ], 201);
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

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

            return $this->json(['success' => false, 'error' => $errorMessages], 422);
        }

        try {
            $authData = $authService->login($dto);

            return $this->json(['success' => true, 'data' => $authData]);
        } catch (UnauthorizedHttpException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 401);
        }
    }

    #[Route('/api/auth/me', name: 'auth_me', methods: ['GET'])]
    public function me(Request $request, AuthService $authService): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');
        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
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

    #[Route('/api/auth/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function refresh(Request $request, AuthService $authService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $refreshToken = $data['refresh_token'] ?? '';

        if (! $refreshToken) {
            return $this->json(['success' => false, 'error' => 'Refresh token manquant'], 400);
        }

        try {
            $authData = $authService->refreshAccessToken($refreshToken);

            return $this->json(['success' => true, 'data' => $authData]);
        } catch (UnauthorizedHttpException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 401);
        }
    }

    #[Route('/api/auth/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(Request $request, AuthService $authService): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return $this->json(['error' => 'Token manquant'], 401);
        }

        $token = substr($authHeader, 7);
        $data = json_decode($request->getContent(), true);
        $refreshToken = $data['refresh_token'] ?? null;

        try {
            $authService->logout($token, $refreshToken);

            return $this->json(['message' => 'Déconnexion réussie']);
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }

    #[Route('/api/auth/verify-email', name: 'auth_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request, AuthService $authService): JsonResponse
    {
        $token = $request->query->get('token');
        if (! $token || ! is_string($token)) {
            return $this->json(['error' => 'Token manquant'], 400);
        }

        try {
            $authService->verifyEmail($token);
            return $this->json(['success' => true, 'message' => 'Email vérifié avec succès']);
        } catch (RuntimeException $e) {
            // Codes HTTP plus spécifiques selon le type d'erreur
            $statusCode = match ($e->getMessage()) {
                'Token révoqué.' => 410, // Gone - Token déjà utilisé
                'Email déjà vérifié.' => 409, // Conflict - Email déjà vérifié
                'Type de token invalide.' => 422, // Unprocessable Entity
                'Utilisateur introuvable.' => 404, // Not Found
                default => 400 // Bad Request pour les autres erreurs
            };

            return $this->json(['success' => false, 'error' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/api/auth/resend-verification', name: 'auth_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request, AuthService $authService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';

        if (! $email) {
            return $this->json(['error' => 'Email manquant'], 400);
        }

        try {
            $authService->resendEmailVerification($email);

            return $this->json(['message' => 'Email de vérification renvoyé avec succès']);
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }


    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/api/auth/forgot-password', name: 'auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        AuthService $authService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';

        try {
            $authService->forgotPassword($email);

            return $this->json(['success' => true, 'message' => 'Email de réinitialisation envoyé']);
        } catch (RuntimeException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/auth/reset-password', name: 'auth_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        ValidatorInterface $validator,
        AuthService $authService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $dto = (new ResetPasswordDto())
            ->setToken($data['token'] ?? '')
            ->setNewPassword($data['newPassword'] ?? '');

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], 422);
        }

        try {
            $authService->resetPassword($dto);
            return $this->json(['success' => true, 'message' => 'Mot de passe réinitialisé avec succès']);
        } catch (RuntimeException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/auth/change-password', name: 'auth_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        ValidatorInterface $validator,
        AuthService $authService
    ): JsonResponse {
        $authHeader = $request->headers->get('Authorization');
        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return $this->json(['error' => 'Token manquant'], 401);
        }
        $token = substr($authHeader, 7);

        $data = json_decode($request->getContent(), true);
        $dto = (new ChangePasswordDto())
            ->setCurrentPassword($data['currentPassword'] ?? null)
            ->setNewPassword($data['newPassword'] ?? null);

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json(['errors' => $errorMessages], 422);
        }

        try {
            $authService->changePassword($token, $dto);

            return $this->json(['success' => true, 'message' => 'Mot de passe changé avec succès']);
        } catch (RuntimeException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
