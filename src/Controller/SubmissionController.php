<?php

namespace App\Controller;

use App\Dto\SubmitFormDto;
use App\Dto\SubmissionResponseDto;
use App\Repository\FormRepository;
use App\Repository\SubmissionRepository;
use App\Service\SubmissionExportService;
use App\Service\SubmissionService;
use App\Security\FormVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SubmissionController extends AbstractController
{
    public function __construct(
        private SubmissionService $submissionService,
        private SubmissionExportService $exportService,
        private FormRepository $formRepository,
        private SubmissionRepository $submissionRepository
    ) {}

    #[Route('/api/forms/{id}/submit', name: 'submit_form', methods: ['POST'])]
    public function submit(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $dto = new SubmitFormDto($data['data'] ?? []);

        $ipAddress = $request->getClientIp() ?? '0.0.0.0';
        $submission = $this->submissionService->submit($id, $dto, $ipAddress);

        return $this->json([
            'message' => 'Soumission enregistrée avec succès.',
            'data' => (new SubmissionResponseDto($submission))->toArray(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/forms/{id}/submissions', name: 'get_form_submissions', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $id, Request $request): JsonResponse
    {
        $form = $this->formRepository->find($id);
        if (!$form) {
            return $this->json(['error' => 'Formulaire introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Vérifie l'accès via Voter
        $this->denyAccessUnlessGranted(FormVoter::VIEW_SUBMISSIONS, $form);

        $limit = (int) $request->query->get('limit', 20);
        $offset = (int) $request->query->get('offset', 0);

        $submissions = $this->submissionRepository->findBy(['form' => $form], ['submittedAt' => 'DESC'], $limit, $offset);
        $dtos = array_map(fn($s) => (new SubmissionResponseDto($s))->toArray(), $submissions);

        return $this->json($dtos);
    }

    #[Route('/api/forms/{id}/submissions/export', name: 'export_form_submissions', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function export(string $id, Request $request): Response
    {
        $form = $this->formRepository->find($id);
        if (!$form) {
            return $this->json(['error' => 'Formulaire introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Vérifie l’accès via Voter
        $this->denyAccessUnlessGranted(FormVoter::EXPORT_SUBMISSIONS, $form);

        $limit = $request->query->getInt('limit', 1000);
        $offset = $request->query->getInt('offset', 0);

        $csv = $this->exportService->exportFormSubmissionsToCsv($form, $this->getUser(), $limit, $offset);

        return new Response(
            $csv,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="submissions.csv"',
            ]
        );
    }
}
