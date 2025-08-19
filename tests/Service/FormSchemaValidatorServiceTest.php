<?php

namespace App\Tests\Service;

use App\Service\FormSchemaValidatorService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FormSchemaValidatorServiceTest extends TestCase
{
    private FormSchemaValidatorService $validator;

    protected function setUp(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->validator = new FormSchemaValidatorService($logger);
    }

    public function testValidateEmptySchema(): void
    {
        $this->validator->validateSchema([]);
        $this->assertTrue(true); // Pas d'exception levée
    }

    public function testValidateValidSchema(): void
    {
        $schema = [
            'title' => 'Test Form',
            'description' => 'Test Description',
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                    'position' => 1,
                ],
                [
                    'id' => 'field2',
                    'type' => 'email',
                    'label' => 'Email Address',
                    'required' => false,
                    'position' => 2,
                    'placeholder' => 'Enter your email',
                ],
            ],
        ];

        $this->validator->validateSchema($schema);
        $this->assertTrue(true); // Pas d'exception levée
    }

    public function testValidateSchemaWithAllFieldTypes(): void
    {
        $schema = [
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'label' => 'Text Field', 'position' => 1],
                ['id' => 'f2', 'type' => 'email', 'label' => 'Email Field', 'position' => 2],
                ['id' => 'f3', 'type' => 'number', 'label' => 'Number Field', 'position' => 3],
                ['id' => 'f4', 'type' => 'textarea', 'label' => 'Textarea Field', 'position' => 4],
                ['id' => 'f5', 'type' => 'select', 'label' => 'Select Field', 'position' => 5, 'options' => [
                    ['value' => 'option1', 'label' => 'Option 1'],
                    ['value' => 'option2', 'label' => 'Option 2'],
                ]],
                ['id' => 'f6', 'type' => 'checkbox', 'label' => 'Checkbox Field', 'position' => 6],
                ['id' => 'f7', 'type' => 'radio', 'label' => 'Radio Field', 'position' => 7, 'options' => [
                    ['value' => 'yes', 'label' => 'Yes'],
                    ['value' => 'no', 'label' => 'No'],
                ]],
                ['id' => 'f8', 'type' => 'date', 'label' => 'Date Field', 'position' => 8],
                ['id' => 'f9', 'type' => 'file', 'label' => 'File Field', 'position' => 9],
                ['id' => 'f10', 'type' => 'url', 'label' => 'URL Field', 'position' => 10],
                ['id' => 'f11', 'type' => 'tel', 'label' => 'Tel Field', 'position' => 11],
            ],
        ];

        $this->validator->validateSchema($schema);
        $this->assertTrue(true);
    }

    public function testValidateSchemaWithInvalidFieldType(): void
    {
        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'invalid_type',
                    'label' => 'Invalid Field',
                ],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Type de champ invalide 'invalid_type'");

        $this->validator->validateSchema($schema);
    }

    public function testValidateSchemaWithMissingRequiredProperties(): void
    {
        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    // Manque 'label'
                ],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Propriété obligatoire manquante 'label'");

        $this->validator->validateSchema($schema);
    }

    public function testValidateSchemaWithEmptyLabel(): void
    {
        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => '',
                ],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Propriété obligatoire manquante \'label\'');

        $this->validator->validateSchema($schema);
    }

    public function testValidateSchemaWithInvalidRequired(): void
    {
        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => 'Test Field',
                    'required' => 'yes', // Doit être un booléen
                ],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("doit être un booléen");

        $this->validator->validateSchema($schema);
    }

    public function testValidateSchemaWithInvalidPosition(): void
    {
        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => 'Test Field',
                    'position' => 0, // Doit être positif
                ],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('doit être un entier positif');

        $this->validator->validateSchema($schema);
    }

    public function testValidateSchemaWithDuplicateIds(): void
    {
        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => 'Field 1',
                ],
                [
                    'id' => 'field1', // ID dupliqué
                    'type' => 'email',
                    'label' => 'Field 2',
                ],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ID de champ dupliqué: field1');

        $this->validator->validateSchema($schema);
    }

    public function testValidateSchemaWithDuplicatePositions(): void
    {
        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => 'Field 1',
                    'position' => 1,
                ],
                [
                    'id' => 'field2',
                    'type' => 'email',
                    'label' => 'Field 2',
                    'position' => 1, // Position dupliquée
                ],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Position de champ dupliquée: 1');

        $this->validator->validateSchema($schema);
    }

    public function testValidateSchemaWithInvalidSelectOptions(): void
    {
        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'select',
                    'label' => 'Select Field',
                    'options' => [], // Options vides
                ],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('doivent être un tableau non vide');

        $this->validator->validateSchema($schema);
    }

    public function testValidateSchemaWithInvalidOptionStructure(): void
    {
        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'select',
                    'label' => 'Select Field',
                    'options' => [
                        [
                            'value' => 'option1',
                            // Manque 'label'
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("doit avoir 'value' et 'label'");

        $this->validator->validateSchema($schema);
    }

    public function testValidateSchemaWithValidationRules(): void
    {
        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => 'Text Field',
                    'validation' => [
                        'required' => true,
                        'minLength' => 3,
                        'maxLength' => 50,
                        'pattern' => '/^[a-zA-Z]+$/',
                    ],
                ],
                [
                    'id' => 'field2',
                    'type' => 'number',
                    'label' => 'Number Field',
                    'validation' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 5,
                    ],
                ],
                [
                    'id' => 'field3',
                    'type' => 'file',
                    'label' => 'File Field',
                    'validation' => [
                        'maxSize' => 1024,
                        'allowedTypes' => ['jpg', 'png', 'pdf'],
                    ],
                ],
            ],
        ];

        $this->validator->validateSchema($schema);
        $this->assertTrue(true);
    }

    public function testValidateSchemaWithInvalidValidationRule(): void
    {
        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => 'Text Field',
                    'validation' => [
                        'invalidRule' => true,
                    ],
                ],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Règle de validation 'invalidRule' non autorisée");

        $this->validator->validateSchema($schema);
    }

    public function testValidateSchemaWithInvalidPattern(): void
    {
        $schema = [
            'fields' => [
                [
                    'id' => 'field1',
                    'type' => 'text',
                    'label' => 'Text Field',
                    'validation' => [
                        'pattern' => '[invalid-regex',
                    ],
                ],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('expression régulière invalide');

        $this->validator->validateSchema($schema);
    }

    public function testValidateSchemaWithSettings(): void
    {
        $schema = [
            'fields' => [],
            'settings' => [
                'submitButton' => [
                    'text' => 'Submit Form',
                ],
                'successMessage' => 'Thank you for your submission!',
                'errorMessage' => 'Please correct the errors below.',
                'redirectUrl' => 'https://example.com/thank-you',
                'emailNotification' => [
                    'enabled' => true,
                    'recipients' => ['admin@example.com'],
                ],
                'theme' => [
                    'primaryColor' => '#007bff',
                    'backgroundColor' => '#ffffff',
                ],
            ],
        ];

        $this->validator->validateSchema($schema);
        $this->assertTrue(true);
    }

    public function testValidateSchemaWithInvalidSetting(): void
    {
        $schema = [
            'settings' => [
                'invalidSetting' => 'value',
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Paramètre de formulaire non autorisé: 'invalidSetting'");

        $this->validator->validateSchema($schema);
    }

    public function testValidateSchemaWithInvalidEmailNotificationStructure(): void
    {
        $schema = [
            'settings' => [
                'emailNotification' => [
                    'enabled' => true,
                    'recipients' => [], // Tableau vide
                ],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('doit être un tableau non vide');

        $this->validator->validateSchema($schema);
    }

    public function testValidateSchemaWithInvalidBasicStructure(): void
    {
        $schema = [
            'title' => 123, // Doit être une chaîne
            'fields' => [],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('doit être une chaîne de caractères');

        $this->validator->validateSchema($schema);
    }

    public function testValidateSchemaWithNonArrayField(): void
    {
        $schema = [
            'fields' => [
                'not-an-array', // Doit être un tableau
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('doit être un tableau');

        $this->validator->validateSchema($schema);
    }
}
