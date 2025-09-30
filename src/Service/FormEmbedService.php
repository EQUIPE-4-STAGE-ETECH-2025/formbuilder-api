<?php

namespace App\Service;

use App\Dto\FormEmbedDto;
use App\Entity\Form;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FormEmbedService
{
    public function __construct(
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

            // URL de base pour l'iframe (sans token - approche Google Forms)
            $baseUrl = $_ENV['FRONTEND_URL'] ?? $this->parameterBag->get('frontend_url');
            if (! is_string($baseUrl)) {
                $baseUrl = 'http://localhost:3000';
            }
            $embedUrl = $baseUrl . '/embed/' . $formId;

            // Générer le code HTML d'intégration
            $embedCode = $this->generateHtmlEmbedCode($embedUrl, $customization);

            $this->logger->info('Code d\'intégration généré avec succès', [
                'form_id' => $formId,
            ]);

            return new FormEmbedDto(
                embedCode: $embedCode,
                formId: $formId,
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


    /**
     * @param array<string, mixed> $customization
     */
    private function generateHtmlEmbedCode(string $embedUrl, array $customization): string
    {
        // Validation et sanitization stricte des paramètres
        $width = $this->sanitizeWidth($customization['width'] ?? '100%');
        $height = $this->sanitizeHeight($customization['height'] ?? '600px');
        $border = $this->sanitizeBorder($customization['border'] ?? 'none');
        $borderRadius = $this->sanitizeBorderRadius($customization['borderRadius'] ?? '8px');
        $boxShadow = $this->sanitizeBoxShadow($customization['boxShadow'] ?? '0 4px 6px rgba(0, 0, 0, 0.1)');

        // Extraire la valeur numérique de height pour l'attribut HTML
        $heightValue = preg_replace('/[^0-9]/', '', $height);
        if (empty($heightValue)) {
            $heightValue = '600';
        }

        $style = sprintf(
            'width: %s; height: %s; border: %s; border-radius: %s; box-shadow: %s;',
            $width,
            $height,
            $border,
            $borderRadius,
            $boxShadow
        );

        return sprintf(
            '<iframe src="%s" style="%s" frameborder="0" scrolling="auto" allowtransparency="true" height="%s"></iframe>',
            htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($style, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($heightValue, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Valide et sanitize la largeur (width)
     */
    private function sanitizeWidth(mixed $width): string
    {
        if (! is_string($width)) {
            return '100%';
        }

        // Whitelist des unités autorisées
        if (preg_match('/^(\d+)(px|%|em|rem|vw)$/', $width, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];
            
            // Limites raisonnables
            if ($unit === 'px' && $value > 2000) {
                $value = 2000;
            }
            if ($unit === '%' && $value > 100) {
                $value = 100;
            }
            
            return $value . $unit;
        }

        return '100%'; // Valeur par défaut sécurisée
    }

    /**
     * Valide et sanitize la hauteur (height)
     */
    private function sanitizeHeight(mixed $height): string
    {
        if (! is_string($height)) {
            return '600px';
        }

        if (preg_match('/^(\d+)(px|vh)$/', $height, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];
            
            // Limites raisonnables
            if ($unit === 'px' && ($value < 200 || $value > 2000)) {
                $value = 600;
            }
            if ($unit === 'vh' && $value > 100) {
                $value = 100;
            }
            
            return $value . $unit;
        }

        return '600px';
    }

    /**
     * Valide et sanitize la bordure (border)
     */
    private function sanitizeBorder(mixed $border): string
    {
        if (! is_string($border)) {
            return 'none';
        }

        if ($border === 'none') {
            return 'none';
        }

        // Format: 1px solid #000000
        if (preg_match('/^(\d{1,2})px\s+(solid|dashed|dotted)\s+(#[0-9a-fA-F]{6}|#[0-9a-fA-F]{3})$/', $border)) {
            return $border;
        }

        return 'none';
    }

    /**
     * Valide et sanitize le border-radius
     */
    private function sanitizeBorderRadius(mixed $borderRadius): string
    {
        if (! is_string($borderRadius)) {
            return '8px';
        }

        if (preg_match('/^(\d+)(px|%)$/', $borderRadius, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];
            
            if ($value > 50) {
                $value = 50;
            }
            
            return $value . $unit;
        }

        return '8px';
    }

    /**
     * Valide et sanitize le box-shadow
     */
    private function sanitizeBoxShadow(mixed $boxShadow): string
    {
        if (! is_string($boxShadow)) {
            return 'none';
        }

        if ($boxShadow === 'none') {
            return 'none';
        }

        // Format simple: 0 4px 6px rgba(0, 0, 0, 0.1)
        if (preg_match('/^(-?\d+px\s+){2,4}rgba?\([^)]+\)$/', $boxShadow)) {
            return $boxShadow;
        }

        return 'none';
    }

}
