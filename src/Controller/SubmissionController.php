<?php

namespace App\Controller;

use App\Dto\SubmitFormDto;
use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\SubmissionExportService;
use App\Service\SubmissionService;
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
    ) {
    }

    #[Route('/{id}/submit', name: 'submit_form', methods: ['POST'])]
    public function submitForm(Request $request, string $id): JsonResponse
    {
        $form = $this->submissionService->getFormById($id);
        if (! $form) {
            throw new NotFoundHttpException('Formulaire introuvable.');
        }

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return $this->json(['error' => 'Données invalides'], 400);
        }

        $dto = new SubmitFormDto($data);
        $user = $this->getUser(); // null si non authentifié
        $userEntity = $user instanceof User ? $user : null;

        $submission = $this->submissionService->submitForm(
            $form,
            $dto->getData(),
            $userEntity,
            $request->getClientIp()
        );

        return $this->json([
            'id' => $submission->getId(),
            'formId' => $submission->getForm()?->getId(),
            'data' => $submission->getData(),
            'submittedAt' => $submission->getSubmittedAt()?->format('c'),
            'ipAddress' => $submission->getIpAddress(),
        ]);
    }

    #[Route('/{id}/submissions', name: 'list_submissions', methods: ['GET'])]
    public function listSubmissions(string $id): JsonResponse
    {
        $form = $this->submissionService->getFormById($id);
        if (! $form) {
            throw new NotFoundHttpException('Formulaire introuvable.');
        }

        $user = $this->getUser();
        $userEntity = $user instanceof User ? $user : null;
        if (! $userEntity || ! $this->authorizationService->canAccessForm($userEntity, $form)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $submissions = $this->submissionService->getFormSubmissions($form);

        $result = array_map(fn ($s) => [
            'id' => $s->getId(),
            'formId' => $s->getForm()?->getId(),
            'data' => $s->getData(),
            'submittedAt' => $s->getSubmittedAt()?->format('c'),
            'ipAddress' => $s->getIpAddress(),
        ], $submissions);

        return $this->json($result);
    }

    #[Route('/{id}/submissions/export', name: 'export_submissions', methods: ['GET'])]
    public function exportSubmissions(Request $request, string $id): Response
    {
        $form = $this->submissionService->getFormById($id);
        if (! $form) {
            throw new NotFoundHttpException('Formulaire introuvable.');
        }

        $user = $this->getUser();
        $userEntity = $user instanceof User ? $user : null;
        if (! $userEntity || ! $this->authorizationService->canAccessForm($userEntity, $form)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        // Récupération des paramètres de pagination
        $limit = $request->query->has('limit') ? $request->query->getInt('limit') : null;
        $offset = $request->query->getInt('offset', 0);

        // Validation des paramètres
        if ($limit !== null && $limit <= 0) {
            return $this->json(['error' => 'Le paramètre limit doit être un entier positif'], 400);
        }

        if ($offset < 0) {
            return $this->json(['error' => 'Le paramètre offset doit être un entier positif ou nul'], 400);
        }

        $csvContent = $this->submissionExportService->exportFormSubmissionsToCsv($form, $userEntity, $limit, $offset);

        $filename = sprintf('submissions_%s_%s.csv', $form->getTitle(), date('Y-m-d_H-i-s'));
        $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);

        return new Response(
            $csvContent,
            200,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
    }
}
