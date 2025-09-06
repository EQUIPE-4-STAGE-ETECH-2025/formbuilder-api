<?php

namespace App\Controller;

use App\Dto\CreateSubscriptionDto;
use App\Dto\SubscriptionResponseDto;
use App\Entity\Subscription;
use App\Repository\PlanRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class SubscriptionController extends AbstractController
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private UserRepository $userRepository,
        private PlanRepository $planRepository
    ) {
    }

    #[Route('/users/{id}/subscriptions', name: 'get_user_subscriptions', methods: ['GET'])]
    public function getUserSubscriptions(string $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (! $user) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $subscriptions = $this->subscriptionRepository->findBy(['user' => $user]);
        $data = array_map(fn (Subscription $s) => (new SubscriptionResponseDto($s))->toArray(), $subscriptions);

        return $this->json($data);
    }

    #[Route('/subscriptions', name: 'create_subscription', methods: ['POST'])]
    public function createSubscription(Request $request): JsonResponse
    {
        $dto = new CreateSubscriptionDto(json_decode($request->getContent(), true));

        $user = $this->userRepository->findOneBy(['email' => $dto->userEmail]);
        if (! $user) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        if ($dto->planId) {
            $plan = $this->planRepository->find($dto->planId);
        } else {
            $plan = $this->planRepository->find('550e8400-e29b-41d4-a716-446655440201');
        }
        if (! $plan) {
            return $this->json(['error' => 'Plan introuvable'], 404);
        }

        $subscription = new Subscription();
        $subscription->setUser($user)
                    ->setPlan($plan)
                    ->setStripeSubscriptionId('MOCK-' . uniqid())
                    ->setStartDate(new \DateTime())
                    ->setEndDate((new \DateTime())->modify('+1 month'));

        $this->subscriptionRepository->save($subscription, true);

        return $this->json((new SubscriptionResponseDto($subscription))->toArray(), 201);
    }

    #[Route('/subscriptions/{id}', name: 'update_subscription', methods: ['PUT'])]
    public function updateSubscription(string $id, Request $request): JsonResponse
    {
        $subscription = $this->subscriptionRepository->find($id);
        if (! $subscription) {
            return $this->json(['error' => 'Abonnement introuvable'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['planId'])) {
            $plan = $this->planRepository->find($data['planId']);
            if (! $plan) {
                return $this->json(['error' => 'Plan introuvable'], 404);
            }
            $subscription->setPlan($plan);
        }

        $subscription->setUpdatedAt(new \DateTimeImmutable());
        $this->subscriptionRepository->save($subscription, true);

        return $this->json((new SubscriptionResponseDto($subscription))->toArray());
    }
}
