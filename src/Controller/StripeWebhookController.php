<?php

namespace App\Controller;

use App\Service\StripeWebhookService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/webhooks', name: 'api_webhooks_')]
class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripeWebhookService $webhookService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Endpoint pour recevoir les webhooks Stripe
     */
    #[Route('/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function handleStripeWebhook(Request $request): JsonResponse
    {
        try {
            // Récupérer le payload et la signature
            $payload = $request->getContent();
            $signature = $request->headers->get('stripe-signature', '');

            if (empty($payload)) {
                $this->logger->warning('Webhook Stripe reçu sans payload');

                return $this->json([
                    'error' => 'Payload manquant',
                ], Response::HTTP_BAD_REQUEST);
            }

            if (empty($signature)) {
                $this->logger->warning('Webhook Stripe reçu sans signature');

                return $this->json([
                    'error' => 'Signature manquante',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Traiter le webhook
            $result = $this->webhookService->handleWebhook($payload, $signature);

            $this->logger->info('Webhook Stripe traité avec succès', [
                'result' => $result,
                'payload_size' => strlen($payload),
            ]);

            return $this->json([
                'status' => 'success',
                'result' => $result,
            ], Response::HTTP_OK);

        } catch (\InvalidArgumentException $e) {
            // Erreur de signature - probablement une tentative malveillante
            $this->logger->error('Webhook Stripe avec signature invalide', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ]);

            return $this->json([
                'error' => 'Signature invalide',
            ], Response::HTTP_UNAUTHORIZED);

        } catch (\Exception $e) {
            // Autres erreurs de traitement
            $this->logger->error('Erreur lors du traitement du webhook Stripe', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Retourner un statut 500 pour que Stripe réessaie
            return $this->json([
                'error' => 'Erreur interne lors du traitement',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Endpoint de test pour vérifier que les webhooks sont bien reçus
     */
    #[Route('/stripe/test', name: 'stripe_webhook_test', methods: ['GET'])]
    public function testWebhookEndpoint(): JsonResponse
    {
        $this->logger->info('Test de l\'endpoint webhook Stripe');

        return $this->json([
            'status' => 'ok',
            'message' => 'Endpoint webhook opérationnel',
            'timestamp' => time(),
            'datetime' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Endpoint pour obtenir des informations sur le dernier webhook reçu (debug)
     */
    #[Route('/stripe/status', name: 'stripe_webhook_status', methods: ['GET'])]
    public function getWebhookStatus(): JsonResponse
    {
        // Cette méthode pourrait être étendue pour inclure des statistiques
        // sur les webhooks reçus, stockées en cache ou en base de données

        return $this->json([
            'status' => 'operational',
            'message' => 'Service webhook Stripe opérationnel',
            'supported_events' => [
                'checkout.session.completed',
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted',
                'invoice.created',
                'invoice.paid',
                'invoice.payment_failed',
                'invoice.payment_succeeded',
                'payment_method.attached',
                'payment_method.detached',
                'customer.created',
                'customer.updated',
                'customer.deleted',
            ],
            'endpoint_url' => $this->generateUrl('api_webhooks_stripe_webhook', [], 0),
            'test_url' => $this->generateUrl('api_webhooks_stripe_webhook_test', [], 0),
        ]);
    }
}
