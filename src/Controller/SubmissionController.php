<?php

namespace App\Controller;

use App\Dto\SubmitFormDto;
use App\Exception\QuotaExceededException;
use App\Service\AuthorizationService;
use App\Service\SubmissionExportService;
use App\Service\SubmissionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/forms')]
class SubmissionController extends AbstractController
{
    public function __construct(
        private readonly SubmissionService $submissionService,
        private readonly SubmissionExportService $submissionExportService,
        private readonly AuthorizationService $authorizationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/{id}/submit', name: 'submit_form', methods: ['POST'])]
    public function submitForm(Request $request, string $id): JsonResponse
    {
        try {
            $form = $this->submissionService->getFormById($id);
            if (! $form) {
                throw new NotFoundHttpException('Formulaire introuvable.');
            }

            $data = json_decode($request->getContent(), true);
            if (! is_array($data)) {
                return $this->json(['error' => 'Données invalides'], 400);
            }

            $dto = new SubmitFormDto($data);

            $submission = $this->submissionService->submitForm(
                $form,
                $dto->getData(),
                $request->getClientIp()
            );

            return $this->json([
                'id' => $submission->getId(),
                'formId' => $submission->getForm()?->getId(),
                'data' => $submission->getData(),
                'submittedAt' => $submission->getSubmittedAt()?->format('c'),
                'ipAddress' => $submission->getIpAddress(),
            ]);
        } catch (QuotaExceededException $e) {
            $this->logger->warning('Quota dépassé lors de la soumission du formulaire', [
                'form_id' => $id,
                'action_type' => $e->getActionType(),
                'current_usage' => $e->getCurrentUsage(),
                'max_limit' => $e->getMaxLimit(),
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Quota dépassé',
                'data' => $e->toArray(),
            ], $e->getHttpStatusCode());
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la soumission du formulaire', [
                'form_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la soumission du formulaire',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/submissions', name: 'list_submissions', methods: ['GET'])]
    public function listSubmissions(string $id, Request $request): JsonResponse
    {
        $form = $this->submissionService->getFormById($id);
        if (! $form) {
            throw new NotFoundHttpException('Formulaire introuvable.');
        }

        $user = $this->getUser();
        if (! $user instanceof \App\Entity\User || ! $this->authorizationService->canAccessForm($user, $form)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        // Paramètres de pagination avec validation améliorée
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(5, (int) $request->query->get('limit', 10))); // Réduire la limite max pour de meilleures performances

        // Utiliser une seule requête pour récupérer les données paginées et le total
        $result = $this->submissionService->getFormSubmissionsWithTotal($form, $page, $limit);
        $submissions = $result['submissions'];
        $totalSubmissions = $result['total'];

        $result = array_map(fn ($s) => [
            'id' => $s->getId(),
            'formId' => $s->getForm()?->getId(),
            'data' => $s->getData(),
            'submittedAt' => $s->getSubmittedAt()?->format('c'),
            'ipAddress' => $s->getIpAddress(),
        ], $submissions);

        return $this->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalSubmissions,
                'totalPages' => ceil($totalSubmissions / $limit),
            ],
        ]);
    }

    #[Route('/{id}/submissions/export', name: 'export_submissions', methods: ['GET'])]
    public function exportSubmissions(string $id): Response
    {
        $form = $this->submissionService->getFormById($id);
        if (! $form) {
            throw new NotFoundHttpException('Formulaire introuvable.');
        }

        $user = $this->getUser();
        if (! $user instanceof \App\Entity\User || ! $this->authorizationService->canAccessForm($user, $form)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $csvContent = $this->submissionExportService->exportFormSubmissionsToCsv($form, $user);

        return new Response(
            $csvContent,
            200,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="submissions.csv"',
            ]
        );
    }
}
