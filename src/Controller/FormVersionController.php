<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\FormRepository;
use App\Service\AuthorizationService;
use App\Service\FormVersionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/forms/{formId}/versions', name: 'api_form_versions_')]
#[IsGranted('ROLE_USER')]
class FormVersionController extends AbstractController
{
    public function __construct(
        private FormVersionService $formVersionService,
        private FormRepository $formRepository,
        private AuthorizationService $authorizationService,
        private LoggerInterface $logger
    ) {
    }

    #[Route('', methods: ['GET'], requirements: ['formId' => '[0-9a-f-]{36}'])]
    public function index(string $formId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $form = $this->formRepository->find($formId);

            if (! $form) {
                return $this->json([
                    'success' => false,
                    'message' => 'Formulaire non trouvé',
                ], Response::HTTP_NOT_FOUND);
            }

            if (! $this->authorizationService->canAccessForm($user, $form)) {
                throw new AccessDeniedException('Accès refusé à ce formulaire');
            }

            $versions = $this->formVersionService->getVersionsByForm($form);

            $this->logger->info('Versions du formulaire récupérées', [
                'form_id' => $formId,
                'user_id' => $user->getId(),
                'versions_count' => count($versions),
            ]);

            return $this->json([
                'success' => true,
                'data' => $versions,
            ], Response::HTTP_OK);
        } catch (AccessDeniedException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération des versions', [
                'error' => $e->getMessage(),
                'form_id' => $formId,
                'user_id' => $user->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des versions',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', methods: ['POST'], requirements: ['formId' => '[0-9a-f-]{36}'])]
    public function create(string $formId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $form = $this->formRepository->find($formId);

            if (! $form) {
                return $this->json([
                    'success' => false,
                    'message' => 'Formulaire non trouvé',
                ], Response::HTTP_NOT_FOUND);
            }

            if (! $this->authorizationService->canModifyForm($user, $form)) {
                throw new AccessDeniedException('Vous n\'avez pas les permissions pour modifier ce formulaire');
            }

            $data = json_decode($request->getContent(), true);

            if (! is_array($data) || ! isset($data['schema']) || ! is_array($data['schema'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Schéma de formulaire requis',
                ], Response::HTTP_BAD_REQUEST);
            }

            $version = $this->formVersionService->createVersion($form, $data['schema']);

            return $this->json([
                'success' => true,
                'data' => $version,
                'message' => 'Version créée avec succès',
            ], Response::HTTP_CREATED);
        } catch (AccessDeniedException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la création de la version', [
                'error' => $e->getMessage(),
                'form_id' => $formId,
                'user_id' => $user->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la version',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{version}/restore', methods: ['POST'], requirements: ['formId' => '[0-9a-f-]{36}', 'version' => '\d+'])]
    public function restore(string $formId, int $version): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $form = $this->formRepository->find($formId);

            if (! $form) {
                return $this->json([
                    'success' => false,
                    'message' => 'Formulaire non trouvé',
                ], Response::HTTP_NOT_FOUND);
            }

            if (! $this->authorizationService->canModifyForm($user, $form)) {
                throw new AccessDeniedException('Vous n\'avez pas les permissions pour modifier ce formulaire');
            }

            $restoredVersion = $this->formVersionService->restoreVersion($form, $version);

            return $this->json([
                'success' => true,
                'data' => $restoredVersion,
                'message' => "Version {$version} restaurée avec succès",
            ], Response::HTTP_OK);
        } catch (AccessDeniedException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la restauration de la version', [
                'error' => $e->getMessage(),
                'form_id' => $formId,
                'version' => $version,
                'user_id' => $user->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la restauration de la version',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{version}', methods: ['DELETE'], requirements: ['formId' => '[0-9a-f-]{36}', 'version' => '\d+'])]
    public function delete(string $formId, int $version): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $form = $this->formRepository->find($formId);

            if (! $form) {
                return $this->json([
                    'success' => false,
                    'message' => 'Formulaire non trouvé',
                ], Response::HTTP_NOT_FOUND);
            }

            if (! $this->authorizationService->canModifyForm($user, $form)) {
                throw new AccessDeniedException('Vous n\'avez pas les permissions pour modifier ce formulaire');
            }

            $this->formVersionService->deleteVersion($form, $version);

            return $this->json([
                'success' => true,
                'message' => "Version {$version} supprimée avec succès",
            ], Response::HTTP_NO_CONTENT);
        } catch (AccessDeniedException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la suppression de la version', [
                'error' => $e->getMessage(),
                'form_id' => $formId,
                'version' => $version,
                'user_id' => $user->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la version',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
