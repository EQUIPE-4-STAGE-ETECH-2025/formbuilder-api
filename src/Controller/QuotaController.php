<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\AuthorizationService;
use App\Service\QuotaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users', name: 'api_users_quotas_')]
class QuotaController extends AbstractController
{
    public function __construct(
        private QuotaService $quotaService,
        private UserRepository $userRepository,
        private AuthorizationService $authorizationService
    ) {
    }

    #[Route('/{id}/quotas', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getQuotas(string $id): JsonResponse
    {
        try {
            // Récupérer l'utilisateur
            $user = $this->userRepository->find($id);
            if (! $user) {
                return $this->json(['error' => 'Utilisateur non trouvé'], 404);
            }

            // Vérifier les permissions
            $currentUser = $this->getUser();
            if (! $currentUser instanceof \App\Entity\User) {
                return $this->json(['error' => 'Utilisateur non authentifié'], 401);
            }

            if (! $this->authorizationService->canAccessUserData($currentUser, $user)) {
                return $this->json(['error' => 'Accès non autorisé'], 403);
            }

            // Calculer les quotas actuels
            $quotas = $this->quotaService->calculateCurrentQuotas($user);

            return $this->json([
                'success' => true,
                'data' => $quotas,
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des quotas',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
