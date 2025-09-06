<?php

namespace App\Controller;

use App\Dto\CreateSubscriptionDto;
use App\Dto\SubscriptionResponseDto;
use App\Entity\Subscription;
use App\Repository\PlanRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\SubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class SubscriptionController extends AbstractController
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private UserRepository $userRepository,
        private PlanRepository $planRepository,
        private SubscriptionService $subscriptionService
    ) {
    }

    #[Route('/users/{id}/subscriptions', name: 'get_user_subscriptions', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUserSubscriptions(string $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (! $user) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        // Vérifier que l'utilisateur connecté peut accéder aux abonnements
        $currentUser = $this->getUser();
        if ($currentUser !== $user && ! $this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Accès non autorisé'], 403);
        }

        $subscriptions = $this->subscriptionRepository->findBy(['user' => $user]);
        $data = array_map(fn (Subscription $s) => (new SubscriptionResponseDto($s))->toArray(), $subscriptions);

        return $this->json($data);
    }

    #[Route('/subscriptions', name: 'create_subscription', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createSubscription(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $dto = new CreateSubscriptionDto($data);

            $user = $this->userRepository->findOneBy(['email' => $dto->userEmail]);
            if (! $user) {
                return $this->json(['error' => 'Utilisateur introuvable'], 404);
            }

            // Vérification de sécurité
            $currentUser = $this->getUser();
            if ($currentUser !== $user && ! $this->isGranted('ROLE_ADMIN')) {
                return $this->json(['error' => 'Accès non autorisé'], 403);
            }

            $plan = $this->planRepository->find($dto->planId);
            if (! $plan) {
                return $this->json(['error' => 'Plan introuvable'], 404);
            }

            // Créer l'abonnement avec intégration Stripe si ce n'est pas le plan gratuit
            if ($plan->getPriceCents() > 0) {
                // Pour les plans payants, utiliser le StripeController
                return $this->json([
                    'message' => 'Pour les plans payants, utilisez l\'endpoint /api/stripe/checkout-session',
                    'redirect_to' => '/api/stripe/checkout-session',
                ], 400);
            }

            // Pour le plan gratuit, créer directement
            $subscription = $this->subscriptionService->createSubscription($user, $plan);

            return $this->json((new SubscriptionResponseDto($subscription))->toArray(), 201);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la création de l\'abonnement',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/subscriptions/{id}', name: 'update_subscription', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateSubscription(string $id, Request $request): JsonResponse
    {
        try {
            $subscription = $this->subscriptionRepository->find($id);
            if (! $subscription) {
                return $this->json(['error' => 'Abonnement introuvable'], 404);
            }

            // Vérification de sécurité
            $currentUser = $this->getUser();
            if ($currentUser !== $subscription->getUser() && ! $this->isGranted('ROLE_ADMIN')) {
                return $this->json(['error' => 'Accès non autorisé'], 403);
            }

            $data = json_decode($request->getContent(), true);

            if (isset($data['planId'])) {
                $newPlan = $this->planRepository->find($data['planId']);
                if (! $newPlan) {
                    return $this->json(['error' => 'Plan introuvable'], 404);
                }

                // Gestion du changement de plan avec intégration Stripe
                if ($newPlan->getPriceCents() > 0 && $subscription->getStripeSubscriptionId()) {
                    // Pour les changements vers des plans payants avec un abonnement Stripe existant
                    return $this->json([
                        'message' => 'Pour les changements de plan payant, utilisez l\'endpoint /api/stripe/subscription/{id}',
                        'redirect_to' => '/api/stripe/subscription/' . $subscription->getStripeSubscriptionId(),
                    ], 400);
                }

                // Changement de plan local (gratuit vers gratuit ou cas spéciaux)
                $user = $subscription->getUser();
                if (! $user) {
                    return $this->json(['error' => 'Utilisateur de l\'abonnement introuvable'], 404);
                }

                $updatedSubscription = $this->subscriptionService->changePlan($user, $newPlan);

                return $this->json((new SubscriptionResponseDto($updatedSubscription))->toArray());
            }

            // Autres mises à jour (statut, etc.)
            $subscription->setUpdatedAt(new \DateTimeImmutable());
            $this->subscriptionRepository->save($subscription, true);

            return $this->json((new SubscriptionResponseDto($subscription))->toArray());

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la mise à jour de l\'abonnement',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
