<?php

namespace App\Service;

use App\Dto\FormEmbedDto;
use App\Entity\Form;
use App\Entity\FormToken;
use App\Repository\FormTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Uid\Uuid;

class FormEmbedService
{
    public function __construct(
        private JwtService $jwtService,
        private FormTokenRepository $formTokenRepository,
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $parameterBag,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Générer le code d'intégration pour un formulaire publié
     * @param array<string, mixed> $customization
     */
    public function generateEmbedCode(Form $form, array $customization = []): FormEmbedDto
    {
        if ($form->getStatus() !== 'PUBLISHED') {
            throw new \InvalidArgumentException('Le formulaire doit être publié pour générer un code d\'intégration');
        }

        $formId = $form->getId();
        if ($formId === null) {
            throw new \InvalidArgumentException('Le formulaire doit avoir un ID valide');
        }

        try {
            $this->logger->info('Génération du code d\'intégration', [
                'form_id' => $formId,
            ]);

            // Générer ou récupérer un token pour ce formulaire
            $token = $this->getOrCreateFormToken($form);

            // URL de base pour l'iframe
            $baseUrl = $this->parameterBag->get('app.base_url');
            if (! is_string($baseUrl)) {
                $baseUrl = 'http://localhost:8000';
            }
            $embedUrl = $baseUrl . '/embed/form/' . $formId . '?token=' . $token;

            // Générer le code HTML d'intégration
            $embedCode = $this->generateHtmlEmbedCode($embedUrl, $customization);

            $this->logger->info('Code d\'intégration généré avec succès', [
                'form_id' => $formId,
            ]);

            return new FormEmbedDto(
                embedCode: $embedCode,
                formId: $formId,
                token: $token,
                embedUrl: $embedUrl,
                customization: $customization
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération du code d\'intégration', [
                'error' => $e->getMessage(),
                'form_id' => $formId,
            ]);

            throw $e;
        }
    }

    private function getOrCreateFormToken(Form $form): string
    {
        // Chercher un token existant et valide
        $existingToken = $this->formTokenRepository->findOneBy([
            'form' => $form,
            'isActive' => true,
        ]);

        if ($existingToken && $existingToken->getExpiresAt() > new \DateTimeImmutable()) {
            $token = $existingToken->getToken();
            if ($token !== null) {
                return $token;
            }
        }

        $formId = $form->getId();
        if ($formId === null) {
            throw new \InvalidArgumentException('Le formulaire doit avoir un ID valide');
        }

        // Créer un nouveau token
        $tokenData = [
            'form_id' => $formId,
            'type' => 'embed',
            'exp' => (new \DateTimeImmutable('+1 year'))->getTimestamp(),
        ];

        $jwtToken = $this->jwtService->generateToken($tokenData);

        $formToken = new FormToken();
        $formToken->setId(Uuid::v4()->toRfc4122());
        $formToken->setForm($form);
        $formToken->setToken($jwtToken);
        $formToken->setType('embed');
        $formToken->setExpiresAt(new \DateTimeImmutable('+1 year'));
        $formToken->setIsActive(true);

        $this->entityManager->persist($formToken);
        $this->entityManager->flush();

        return $jwtToken;
    }

    /**
     * @param array<string, mixed> $customization
     */
    private function generateHtmlEmbedCode(string $embedUrl, array $customization): string
    {
        $width = $customization['width'] ?? '100%';
        $height = $customization['height'] ?? '600px';
        $border = $customization['border'] ?? 'none';
        $borderRadius = $customization['borderRadius'] ?? '8px';
        $boxShadow = $customization['boxShadow'] ?? '0 4px 6px rgba(0, 0, 0, 0.1)';

        $style = sprintf(
            'width: %s; height: %s; border: %s; border-radius: %s; box-shadow: %s;',
            $width,
            $height,
            $border,
            $borderRadius,
            $boxShadow
        );

        return sprintf(
            '<iframe src="%s" style="%s" frameborder="0" scrolling="auto" allowtransparency="true"></iframe>',
            htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($style, ENT_QUOTES, 'UTF-8')
        );
    }

    public function validateEmbedToken(string $token): ?string
    {
        try {
            $payload = $this->jwtService->validateToken($token);

            if (! isset($payload->form_id) || ! isset($payload->type) || $payload->type !== 'embed') {
                return null;
            }

            // Vérifier que le token existe en base et est actif
            $formToken = $this->formTokenRepository->findOneBy([
                'token' => $token,
                'isActive' => true,
            ]);

            if (! $formToken || $formToken->getExpiresAt() <= new \DateTimeImmutable()) {
                return null;
            }

            return $payload->form_id;
        } catch (\Exception $e) {
            $this->logger->warning('Token d\'intégration invalide', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function revokeFormTokens(Form $form): void
    {
        try {
            $this->logger->info('Révocation des tokens d\'intégration', [
                'form_id' => $form->getId(),
            ]);

            $tokens = $this->formTokenRepository->findBy([
                'form' => $form,
                'type' => 'embed',
                'isActive' => true,
            ]);

            foreach ($tokens as $token) {
                $token->setIsActive(false);
            }

            $this->entityManager->flush();

            $this->logger->info('Tokens d\'intégration révoqués avec succès', [
                'form_id' => $form->getId(),
                'tokens_count' => count($tokens),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la révocation des tokens', [
                'error' => $e->getMessage(),
                'form_id' => $form->getId(),
            ]);

            throw $e;
        }
    }
}
