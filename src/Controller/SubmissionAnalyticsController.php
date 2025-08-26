<?php

namespace App\Controller;

use App\Service\SubmissionAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SubmissionAnalyticsController extends AbstractController
{
    public function __construct(private SubmissionAnalyticsService $analyticsService) {}

    /**
     * Endpoint : GET /api/forms/{id}/submissions/analytics
     * Retourne les statistiques des soumissions d’un formulaire.
     */
    #[Route('/api/forms/{id}/submissions/analytics', name: 'get_form_analytics', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAnalytics(string $id): JsonResponse
    {
        try {
            // Appel du service pour récupérer les statistiques
            $analytics = $this->analyticsService->getFormAnalytics($id);

            return $this->json($analytics, JsonResponse::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            // Gestion des erreurs si le formulaire est introuvable
            return $this->json(['error' => $e->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            // Gestion des erreurs générales
            return $this->json(['error' => 'An unexpected error occurred.'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}