<?php

namespace App\Controller;

use App\Repository\FormRepository;
use App\Service\FormEmbedService;
use App\Service\FormService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/forms', name: 'api_public_forms_')]
class PublicFormController extends AbstractController
{
    public function __construct(
        private FormRepository $formRepository,
        private FormEmbedService $formEmbedService,
        private FormService $formService,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '[0-9a-f-]{36}'])]
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $form = $this->formRepository->find($id);

            if (!$form) {
                return $this->json([
                    'success' => false,
                    'message' => 'Formulaire non trouvé',
                ], Response::HTTP_NOT_FOUND);
            }

            // Vérifier que le formulaire est publié
            if ($form->getStatus() !== 'PUBLISHED') {
                return $this->json([
                    'success' => false,
                    'message' => 'Ce formulaire n\'est pas disponible publiquement',
                ], Response::HTTP_FORBIDDEN);
            }

            // Récupérer les données du formulaire via le service FormService
            // mais filtrer les données sensibles pour l'accès public
            $formData = $this->formService->formatFormResponse($form);
            
            // Supprimer les informations sensibles pour l'accès public
            $publicFormData = $this->filterSensitiveData($formData);

            $this->logger->info('Formulaire récupéré (accès public)', [
                'form_id' => $id,
                'ip' => $request->getClientIp(),
            ]);

            return $this->json([
                'success' => true,
                'data' => $publicFormData,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération du formulaire public', [
                'error' => $e->getMessage(),
                'form_id' => $id,
                'ip' => $request->getClientIp(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du formulaire',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Filtrer les données sensibles pour l'accès public
     * @param array<string, mixed> $formData
     * @return array<string, mixed>
     */
    private function filterSensitiveData(array $formData): array
    {
        // Supprimer les informations utilisateur et autres données sensibles
        unset(
            $formData['user'], // Informations du propriétaire
            $formData['submissionsCount'], // Nombre de soumissions (info sensible)
            $formData['versions'], // Historique des versions
            $formData['currentVersion'] // Informations de version
        );

        return $formData;
    }

    #[Route('/{id}/embed', methods: ['GET'], requirements: ['id' => '[0-9a-f-]{36}'])]
    public function embed(string $id, Request $request): JsonResponse
    {
        try {
            $form = $this->formRepository->find($id);

            if (!$form) {
                return $this->json([
                    'success' => false,
                    'message' => 'Formulaire non trouvé',
                ], Response::HTTP_NOT_FOUND);
            }

            // Vérifier que le formulaire est publié
            if ($form->getStatus() !== 'PUBLISHED') {
                return $this->json([
                    'success' => false,
                    'message' => 'Ce formulaire n\'est pas disponible publiquement',
                ], Response::HTTP_FORBIDDEN);
            }

            // Récupérer les paramètres de personnalisation
            $customization = [];
            if ($request->query->has('width')) {
                $customization['width'] = $request->query->get('width');
            }
            if ($request->query->has('height')) {
                $customization['height'] = $request->query->get('height');
            }
            if ($request->query->has('border')) {
                $customization['border'] = $request->query->get('border');
            }
            if ($request->query->has('borderRadius')) {
                $customization['borderRadius'] = $request->query->get('borderRadius');
            }
            if ($request->query->has('boxShadow')) {
                $customization['boxShadow'] = $request->query->get('boxShadow');
            }

            $embedData = $this->formEmbedService->generateEmbedCode($form, $customization);

            $this->logger->info('Code d\'intégration généré (accès public)', [
                'form_id' => $id,
                'ip' => $request->getClientIp(),
            ]);

            return $this->json([
                'success' => true,
                'data' => $embedData,
            ], Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération du code d\'intégration public', [
                'error' => $e->getMessage(),
                'form_id' => $id,
                'ip' => $request->getClientIp(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du code d\'intégration',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
