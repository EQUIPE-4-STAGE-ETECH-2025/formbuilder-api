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
        if (!$this->subscriptionRepository->find($id)) {
            return $this->json(['error' => 'Abonnement introuvable'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $id,
            'isActive' => $this->statusService->getStatus($id)
        ]);
    }

    #[Route('/api/subscriptions/{id}/status', name: 'update_subscription_status', methods: ['PUT'])]
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        if (!$this->subscriptionRepository->find($id)) {
            return $this->json(['error' => 'Abonnement introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['isActive'])) {
            return $this->json(['error' => 'Le champ isActive est obligatoire'], Response::HTTP_BAD_REQUEST);
        }

        $subscription = $this->statusService->updateStatus($id, (bool) $data['isActive']);

        return $this->json([
            'message' => 'Statut mis à jour avec succès',
            'id' => $subscription->getId(),
            'isActive' => $subscription->isActive()
        ]);
    }
}