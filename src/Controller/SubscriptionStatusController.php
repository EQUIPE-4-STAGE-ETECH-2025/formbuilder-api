<?php

namespace App\Controller;

use App\Service\SubscriptionStatusService;
use App\Repository\SubscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SubscriptionStatusController extends AbstractController
{
    public function __construct(
        private SubscriptionStatusService $statusService,
        private SubscriptionRepository $subscriptionRepository
    ) {}

    #[Route('/api/subscriptions/{id}/status', name: 'get_subscription_status', methods: ['GET'])]
    public function getStatus(string $id): JsonResponse
    {
        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription) {
            return $this->json(['error' => 'Abonnement introuvable'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $id,
            'status' => $this->statusService->getStatus($id),
        ]);
    }

    #[Route('/api/subscriptions/{id}/status', name: 'update_subscription_status', methods: ['PUT'])]
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription) {
            return $this->json(['error' => 'Abonnement introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['status'])) {
            return $this->json(['error' => 'Le champ status est obligatoire'], Response::HTTP_BAD_REQUEST);
        }

        $allowedStatuses = [
            'ACTIVE',
            'SUSPENDED',
            'CANCELLED',
        ];

        if (!in_array($data['status'], $allowedStatuses, true)) {
            return $this->json(['error' => 'Statut invalide'], Response::HTTP_BAD_REQUEST);
        }

        $subscription = $this->statusService->updateStatus($id, $data['status']);

        return $this->json([
            'message' => 'Statut mis à jour avec succès',
            'id' => $subscription->getId(),
            'status' => $subscription->getStatus(),
        ]);
    }
}
