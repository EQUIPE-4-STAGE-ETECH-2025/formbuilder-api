<?php

namespace App\DataFixtures;

use App\Entity\FormField;
use App\Entity\FormVersion;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class FormFieldFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $formFields = [
            // Version 1 du formulaire 1
            [
                'id' => '550e8400-e29b-41d4-a716-446655441001',
                'formVersion' => '550e8400-e29b-41d4-a716-446655440801',
                'label' => 'Nom complet',
                'type' => 'text',
                'isRequired' => true,
                'options' => ['placeholder' => 'Votre nom'],
                'position' => 1,
                'validationRules' => ['required' => true, 'min_length' => 2],
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655441002',
                'formVersion' => '550e8400-e29b-41d4-a716-446655440801',
                'label' => 'Email',
                'type' => 'email',
                'isRequired' => true,
                'options' => ['placeholder' => 'votre@email.com'],
                'position' => 2,
                'validationRules' => ['required' => true, 'pattern' => '^[^@]+@[^@]+\\.[^@]+$'],
            ],

            // Version 2 du formulaire 1
            [
                'id' => '550e8400-e29b-41d4-a716-446655441003',
                'formVersion' => '550e8400-e29b-41d4-a716-446655440802',
                'label' => 'Nom complet',
                'type' => 'text',
                'isRequired' => true,
                'options' => ['placeholder' => 'Votre nom'],
                'position' => 1,
                'validationRules' => ['required' => true, 'min_length' => 2],
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655441004',
                'formVersion' => '550e8400-e29b-41d4-a716-446655440802',
                'label' => 'Email',
                'type' => 'email',
                'isRequired' => true,
                'options' => ['placeholder' => 'votre@email.com'],
                'position' => 2,
                'validationRules' => ['required' => true, 'pattern' => '^[^@]+@[^@]+\\.[^@]+$'],
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655441005',
                'formVersion' => '550e8400-e29b-41d4-a716-446655440802',
                'label' => 'Message',
                'type' => 'textarea',
                'isRequired' => false,
                'options' => ['placeholder' => 'Votre message'],
                'position' => 3,
                'validationRules' => ['required' => false, 'max_length' => 1000],
            ],

            // Version 1 du formulaire 2
            [
                'id' => '550e8400-e29b-41d4-a716-446655441006',
                'formVersion' => '550e8400-e29b-41d4-a716-446655440803',
                'label' => 'Prénom',
                'type' => 'text',
                'isRequired' => true,
                'options' => ['placeholder' => 'Votre prénom'],
                'position' => 1,
                'validationRules' => ['required' => true, 'min_length' => 2],
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655441007',
                'formVersion' => '550e8400-e29b-41d4-a716-446655440803',
                'label' => 'Email',
                'type' => 'email',
                'isRequired' => true,
                'options' => ['placeholder' => 'votre@email.com'],
                'position' => 2,
                'validationRules' => ['required' => true, 'pattern' => '^[^@]+@[^@]+\\.[^@]+$'],
            ],

            // Version 1 du formulaire 3
            [
                'id' => '550e8400-e29b-41d4-a716-446655441008',
                'formVersion' => '550e8400-e29b-41d4-a716-446655440804',
                'label' => 'Note globale',
                'type' => 'radio',
                'isRequired' => true,
                'options' => ['choices' => ['1', '2', '3', '4', '5']],
                'position' => 1,
                'validationRules' => ['required' => true],
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655441009',
                'formVersion' => '550e8400-e29b-41d4-a716-446655440804',
                'label' => 'Commentaires',
                'type' => 'textarea',
                'isRequired' => false,
                'options' => ['placeholder' => 'Vos commentaires'],
                'position' => 2,
                'validationRules' => ['required' => false, 'max_length' => 500],
            ],

            // Version 1 du formulaire 4
            [
                'id' => '550e8400-e29b-41d4-a716-446655441010',
                'formVersion' => '550e8400-e29b-41d4-a716-446655440805',
                'label' => 'Nom de l\'entreprise',
                'type' => 'text',
                'isRequired' => true,
                'options' => ['placeholder' => 'Nom de votre entreprise'],
                'position' => 1,
                'validationRules' => ['required' => true, 'min_length' => 2],
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655441011',
                'formVersion' => '550e8400-e29b-41d4-a716-446655440805',
                'label' => 'Email professionnel',
                'type' => 'email',
                'isRequired' => true,
                'options' => ['placeholder' => 'contact@entreprise.com'],
                'position' => 2,
                'validationRules' => ['required' => true, 'pattern' => '^[^@]+@[^@]+\\.[^@]+$'],
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655441012',
                'formVersion' => '550e8400-e29b-41d4-a716-446655440805',
                'label' => 'Budget estimé',
                'type' => 'select',
                'isRequired' => true,
                'options' => ['choices' => ['< 1000€', '1000€ - 5000€', '5000€ - 10000€', '> 10000€']],
                'position' => 3,
                'validationRules' => ['required' => true],
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655441013',
                'formVersion' => '550e8400-e29b-41d4-a716-446655440805',
                'label' => 'Description du projet',
                'type' => 'textarea',
                'isRequired' => true,
                'options' => ['placeholder' => 'Décrivez votre projet'],
                'position' => 4,
                'validationRules' => ['required' => true, 'min_length' => 50, 'max_length' => 2000],
            ],
        ];

        foreach ($formFields as $fieldData) {
            $formField = new FormField();
            $formField->setId($fieldData['id']);
            $formField->setFormVersion($this->getReference($fieldData['formVersion'], FormVersion::class));
            $formField->setLabel($fieldData['label']);
            $formField->setType($fieldData['type']);
            $formField->setIsRequired($fieldData['isRequired']);
            $formField->setOptions($fieldData['options']);
            $formField->setPosition($fieldData['position']);
            $formField->setValidationRules($fieldData['validationRules']);

            $manager->persist($formField);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            FormVersionFixtures::class,
        ];
    }
}
