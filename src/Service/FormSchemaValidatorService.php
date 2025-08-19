<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class FormSchemaValidatorService
{
    private const ALLOWED_FIELD_TYPES = [
        'text', 'email', 'number', 'textarea', 'select',
        'checkbox', 'radio', 'date', 'file', 'url', 'tel',
    ];

    private const REQUIRED_FIELD_PROPERTIES = ['type', 'label'];

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $schema
     * @throws \InvalidArgumentException
     */
    public function validateSchema(array $schema): void
    {
        try {
            $this->logger->debug('Validation du schéma de formulaire');

            // Validation de la structure de base
            $this->validateBasicStructure($schema);

            // Validation des champs
            if (isset($schema['fields']) && is_array($schema['fields'])) {
                $this->validateFields($schema['fields']);
            }

            // Validation des paramètres globaux
            if (isset($schema['settings']) && is_array($schema['settings'])) {
                $this->validateSettings($schema['settings']);
            }

            $this->logger->debug('Schéma de formulaire valide');
        } catch (\Exception $e) {
            $this->logger->error('Erreur de validation du schéma', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function validateBasicStructure(array $schema): void
    {
        // Le schéma peut être vide (formulaire sans champs)
        if (empty($schema)) {
            return;
        }

        // Vérifier que les propriétés de base sont du bon type
        if (isset($schema['title']) && ! is_string($schema['title'])) {
            throw new \InvalidArgumentException('Le titre du schéma doit être une chaîne de caractères');
        }

        if (isset($schema['description']) && ! is_string($schema['description'])) {
            throw new \InvalidArgumentException('La description du schéma doit être une chaîne de caractères');
        }

        if (isset($schema['fields']) && ! is_array($schema['fields'])) {
            throw new \InvalidArgumentException('Les champs du schéma doivent être un tableau');
        }
    }

    /**
     * @param array<int, mixed> $fields
     */
    private function validateFields(array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $fieldIds = [];
        $positions = [];

        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                throw new \InvalidArgumentException("Le champ à l'index {$index} doit être un tableau");
            }

            $this->validateField($field, $index);

            // Vérifier l'unicité des IDs
            if (isset($field['id'])) {
                if (in_array($field['id'], $fieldIds)) {
                    throw new \InvalidArgumentException("ID de champ dupliqué: {$field['id']}");
                }
                $fieldIds[] = $field['id'];
            }

            // Vérifier l'unicité des positions
            if (isset($field['position'])) {
                if (in_array($field['position'], $positions)) {
                    throw new \InvalidArgumentException("Position de champ dupliquée: {$field['position']}");
                }
                $positions[] = $field['position'];
            }
        }
    }

    /**
     * @param array<string, mixed> $field
     */
    private function validateField(array $field, int $index): void
    {
        // Vérifier les propriétés obligatoires
        foreach (self::REQUIRED_FIELD_PROPERTIES as $property) {
            if (! isset($field[$property]) || empty($field[$property])) {
                throw new \InvalidArgumentException("Propriété obligatoire manquante '{$property}' pour le champ à l'index {$index}");
            }
        }

        // Valider le type de champ
        if (! in_array($field['type'], self::ALLOWED_FIELD_TYPES)) {
            throw new \InvalidArgumentException(
                "Type de champ invalide '{$field['type']}' pour le champ à l'index {$index}. " .
                "Types autorisés: " . implode(', ', self::ALLOWED_FIELD_TYPES)
            );
        }

        // Valider les propriétés spécifiques
        $this->validateFieldProperties($field, $index);

        // Valider les options pour les champs select et radio
        if (in_array($field['type'], ['select', 'radio']) && isset($field['options'])) {
            $this->validateFieldOptions($field['options'], $index);
        }

        // Valider les règles de validation
        if (isset($field['validation']) && is_array($field['validation'])) {
            $this->validateFieldValidationRules($field['validation'], $field['type'], $index);
        }
    }

    /**
     * @param array<string, mixed> $field
     */
    private function validateFieldProperties(array $field, int $index): void
    {
        // Label
        if (! is_string($field['label']) || strlen(trim($field['label'])) < 1) {
            throw new \InvalidArgumentException("Le libellé du champ à l'index {$index} doit être une chaîne non vide");
        }

        // Required
        if (isset($field['required']) && ! is_bool($field['required'])) {
            throw new \InvalidArgumentException("La propriété 'required' du champ à l'index {$index} doit être un booléen");
        }

        // Position
        if (isset($field['position']) && (! is_int($field['position']) || $field['position'] < 1)) {
            throw new \InvalidArgumentException("La position du champ à l'index {$index} doit être un entier positif");
        }

        // Placeholder
        if (isset($field['placeholder']) && ! is_string($field['placeholder'])) {
            throw new \InvalidArgumentException("Le placeholder du champ à l'index {$index} doit être une chaîne de caractères");
        }

        // Help text
        if (isset($field['helpText']) && ! is_string($field['helpText'])) {
            throw new \InvalidArgumentException("Le texte d'aide du champ à l'index {$index} doit être une chaîne de caractères");
        }
    }

    /**
     * @param mixed $options
     */
    private function validateFieldOptions($options, int $index): void
    {
        if (! is_array($options) || empty($options)) {
            throw new \InvalidArgumentException("Les options du champ à l'index {$index} doivent être un tableau non vide");
        }

        foreach ($options as $optionIndex => $option) {
            if (! is_array($option)) {
                throw new \InvalidArgumentException("L'option à l'index {$optionIndex} du champ {$index} doit être un tableau");
            }

            if (! isset($option['value']) || ! isset($option['label'])) {
                throw new \InvalidArgumentException("L'option à l'index {$optionIndex} du champ {$index} doit avoir 'value' et 'label'");
            }

            if (! is_string($option['label']) || empty(trim($option['label']))) {
                throw new \InvalidArgumentException("Le label de l'option à l'index {$optionIndex} du champ {$index} doit être une chaîne non vide");
            }
        }
    }

    /**
     * @param array<string, mixed> $validationRules
     */
    private function validateFieldValidationRules(array $validationRules, string $fieldType, int $index): void
    {
        $allowedRules = $this->getAllowedValidationRules($fieldType);

        foreach ($validationRules as $rule => $value) {
            if (! in_array($rule, $allowedRules)) {
                throw new \InvalidArgumentException(
                    "Règle de validation '{$rule}' non autorisée pour le type '{$fieldType}' du champ à l'index {$index}"
                );
            }

            $this->validateValidationRuleValue($rule, $value, $index);
        }
    }

    /**
     * @return string[]
     */
    private function getAllowedValidationRules(string $fieldType): array
    {
        $commonRules = ['required', 'custom'];

        return match ($fieldType) {
            'text', 'textarea' => array_merge($commonRules, ['minLength', 'maxLength', 'pattern']),
            'email' => array_merge($commonRules, ['minLength', 'maxLength']),
            'number' => array_merge($commonRules, ['min', 'max', 'step']),
            'date' => array_merge($commonRules, ['minDate', 'maxDate']),
            'file' => array_merge($commonRules, ['maxSize', 'allowedTypes']),
            'url', 'tel' => array_merge($commonRules, ['pattern']),
            default => $commonRules,
        };
    }

    /**
     * @param mixed $value
     */
    private function validateValidationRuleValue(string $rule, $value, int $index): void
    {
        switch ($rule) {
            case 'required':
                if (! is_bool($value)) {
                    throw new \InvalidArgumentException("La règle 'required' du champ à l'index {$index} doit être un booléen");
                }

                break;

            case 'minLength':
            case 'maxLength':
            case 'min':
            case 'max':
            case 'step':
            case 'maxSize':
                if (! is_numeric($value) || $value < 0) {
                    throw new \InvalidArgumentException("La règle '{$rule}' du champ à l'index {$index} doit être un nombre positif");
                }

                break;

            case 'pattern':
                if (! is_string($value)) {
                    throw new \InvalidArgumentException("La règle 'pattern' du champ à l'index {$index} doit être une chaîne de caractères");
                }
                // Tester la validité de l'expression régulière
                if (@preg_match($value, '') === false) {
                    throw new \InvalidArgumentException("La règle 'pattern' du champ à l'index {$index} contient une expression régulière invalide");
                }

                break;

            case 'allowedTypes':
                if (! is_array($value) || empty($value)) {
                    throw new \InvalidArgumentException("La règle 'allowedTypes' du champ à l'index {$index} doit être un tableau non vide");
                }
                foreach ($value as $type) {
                    if (! is_string($type)) {
                        throw new \InvalidArgumentException("Chaque type autorisé du champ à l'index {$index} doit être une chaîne de caractères");
                    }
                }

                break;
        }
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function validateSettings(array $settings): void
    {
        $allowedSettings = [
            'submitButton', 'successMessage', 'errorMessage',
            'redirectUrl', 'emailNotification', 'theme',
        ];

        foreach ($settings as $key => $value) {
            if (! in_array($key, $allowedSettings)) {
                throw new \InvalidArgumentException("Paramètre de formulaire non autorisé: '{$key}'");
            }

            $this->validateSettingValue($key, $value);
        }
    }

    /**
     * @param mixed $value
     */
    private function validateSettingValue(string $key, $value): void
    {
        switch ($key) {
            case 'submitButton':
                if (! is_array($value)) {
                    throw new \InvalidArgumentException("Le paramètre 'submitButton' doit être un tableau");
                }
                if (isset($value['text']) && ! is_string($value['text'])) {
                    throw new \InvalidArgumentException("Le texte du bouton de soumission doit être une chaîne de caractères");
                }

                break;

            case 'successMessage':
            case 'errorMessage':
            case 'redirectUrl':
                if (! is_string($value)) {
                    throw new \InvalidArgumentException("Le paramètre '{$key}' doit être une chaîne de caractères");
                }

                break;

            case 'emailNotification':
                if (! is_array($value)) {
                    throw new \InvalidArgumentException("Le paramètre 'emailNotification' doit être un tableau");
                }
                if (isset($value['enabled']) && ! is_bool($value['enabled'])) {
                    throw new \InvalidArgumentException("La propriété 'enabled' de emailNotification doit être un booléen");
                }
                if (isset($value['recipients']) && (! is_array($value['recipients']) || empty($value['recipients']))) {
                    throw new \InvalidArgumentException("La propriété 'recipients' de emailNotification doit être un tableau non vide");
                }

                break;

            case 'theme':
                if (! is_array($value)) {
                    throw new \InvalidArgumentException("Le paramètre 'theme' doit être un tableau");
                }

                break;
        }
    }
}
