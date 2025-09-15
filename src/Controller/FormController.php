<?php

namespace App\Controller;

use App\Dto\CreateFormDto;
use App\Dto\UpdateFormDto;
use App\Entity\User;
use App\Exception\QuotaExceededException;
use App\Service\FormService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/forms', name: 'api_forms_')]
#[IsGranted('ROLE_USER')]
class FormController extends AbstractController
{
    public function __construct(
        private FormService $formService,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            // Paramètres de pagination optimisés
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(25, max(5, (int) $request->query->get('limit', 10))); // Limites plus restreintes pour de meilleures performances

            // Filtres
            $filters = [];
            if ($request->query->has('status')) {
                $filters['status'] = $request->query->get('status');
            }
            if ($request->query->has('search')) {
                $filters['search'] = $request->query->get('search');
            }

            // Récupérer les données paginées avec le total en une seule requête
            $result = $this->formService->getAllFormsWithTotal($user, $filters, $page, $limit);
            $forms = $result['forms'];
            $total = $result['total'];

            $this->logger->info('Liste des formulaires récupérée', [
                'user_id' => $user->getId(),
                'count' => count($forms),
                'total' => $total,
                'page' => $page,
                'filters' => $filters,
            ]);

            return $this->json([
                'success' => true,
                'data' => $forms,
                'meta' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'totalPages' => ceil($total / $limit),
                ],
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération des formulaires', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des formulaires',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '[0-9a-f-]{36}'])]
    public function show(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $form = $this->formService->getFormById($id, $user);

            $this->logger->info('Formulaire récupéré', [
                'form_id' => $id,
                'user_id' => $user->getId(),
            ]);

            return $this->json([
                'success' => true,
                'data' => $form,
            ], Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération du formulaire', [
                'error' => $e->getMessage(),
                'form_id' => $id,
                'user_id' => $user->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du formulaire',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $data = json_decode($request->getContent(), true);

            if (! is_array($data)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Données JSON invalides',
                ], Response::HTTP_BAD_REQUEST);
            }

            $dto = new CreateFormDto(
                title: $data['title'] ?? '',
                description: $data['description'] ?? '',
                status: $data['status'] ?? 'DRAFT',
                schema: $data['schema'] ?? []
            );

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }

                return $this->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $errorMessages,
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $form = $this->formService->createForm($dto, $user);

            return $this->json([
                'success' => true,
                'data' => $form,
                'message' => 'Formulaire créé avec succès',
            ], Response::HTTP_CREATED);
        } catch (QuotaExceededException $e) {
            $this->logger->warning('Quota dépassé lors de la création du formulaire', [
                'user_id' => $user->getId(),
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
            $this->logger->error('Erreur lors de la création du formulaire', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création du formulaire',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '[0-9a-f-]{36}'])]
    public function update(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $data = json_decode($request->getContent(), true);

            if (! is_array($data)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Données JSON invalides',
                ], Response::HTTP_BAD_REQUEST);
            }

            $dto = new UpdateFormDto(
                title: $data['title'] ?? null,
                description: $data['description'] ?? null,
                status: $data['status'] ?? null,
                schema: $data['schema'] ?? null
            );

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }

                return $this->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $errorMessages,
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $form = $this->formService->updateForm($id, $dto, $user);

            return $this->json([
                'success' => true,
                'data' => $form,
                'message' => 'Formulaire mis à jour avec succès',
            ], Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour du formulaire', [
                'error' => $e->getMessage(),
                'form_id' => $id,
                'user_id' => $user->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du formulaire',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '[0-9a-f-]{36}'])]
    public function delete(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->formService->deleteForm($id, $user);

            return $this->json([
                'success' => true,
                'message' => 'Formulaire supprimé avec succès',
            ], Response::HTTP_NO_CONTENT);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la suppression du formulaire', [
                'error' => $e->getMessage(),
                'form_id' => $id,
                'user_id' => $user->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du formulaire',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/publish', methods: ['POST'], requirements: ['id' => '[0-9a-f-]{36}'])]
    public function publish(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $form = $this->formService->publishForm($id, $user);

            return $this->json([
                'success' => true,
                'data' => $form,
                'message' => 'Formulaire publié avec succès',
            ], Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la publication du formulaire', [
                'error' => $e->getMessage(),
                'form_id' => $id,
                'user_id' => $user->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la publication du formulaire',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
