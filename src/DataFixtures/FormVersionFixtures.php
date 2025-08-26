<?php

namespace App\DataFixtures;

use App\Entity\Form;
use App\Entity\FormVersion;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class FormVersionFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $formVersions = [
            [
                'id' => '550e8400-e29b-41d4-a716-446655440801',
                'form' => '550e8400-e29b-41d4-a716-446655440301',
                'versionNumber' => 1,
                'schema' => [
                    'title' => 'Contact Lead Generation',
                    'description' => 'Formulaire de contact pour prospects',
                    'status' => 'draft',
                    'fields' => [
                        [
                            'id' => '550e8400-e29b-41d4-a716-446655441001',
                            'form_version_id' => '550e8400-e29b-41d4-a716-446655440801',
                            'label' => 'Nom complet',
                            'type' => 'text',
                            'is_required' => true,
                            'options' => ['placeholder' => 'Votre nom'],
                            'position' => 1,
                            'order' => 1,
                            'validation_rules' => ['required' => true, 'min_length' => 2],
                        ],
                        [
                            'id' => '550e8400-e29b-41d4-a716-446655441002',
                            'form_version_id' => '550e8400-e29b-41d4-a716-446655440801',
                            'label' => 'Email',
                            'type' => 'email',
                            'is_required' => true,
                            'options' => ['placeholder' => 'votre@email.com'],
                            'position' => 2,
                            'order' => 2,
                            'validation_rules' => ['required' => true, 'pattern' => '^[^@]+@[^@]+\\.[^@]+$'],
                        ],
                    ],
                    'settings' => [
                        'theme' => [
                            'primary_color' => '#3B82F6',
                            'background_color' => '#FFFFFF',
                            'text_color' => '#1F2937',
                        ],
                        'successMessage' => 'Merci pour votre message !',
                        'emailNotification' => ['enabled' => true],
                    ],
                ],
                'createdAt' => '2024-06-15T10:00:00Z',
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440802',
                'form' => '550e8400-e29b-41d4-a716-446655440301',
                'versionNumber' => 2,
                'schema' => [
                    'title' => 'Contact Lead Generation',
                    'description' => 'Formulaire de contact pour prospects',
                    'status' => 'published',
                    'fields' => [
                        [
                            'id' => '550e8400-e29b-41d4-a716-446655441003',
                            'form_version_id' => '550e8400-e29b-41d4-a716-446655440802',
                            'label' => 'Nom complet',
                            'type' => 'text',
                            'is_required' => true,
                            'options' => ['placeholder' => 'Votre nom'],
                            'position' => 1,
                            'order' => 1,
                            'validation_rules' => ['required' => true, 'min_length' => 2],
                        ],
                        [
                            'id' => '550e8400-e29b-41d4-a716-446655441004',
                            'form_version_id' => '550e8400-e29b-41d4-a716-446655440802',
                            'label' => 'Email',
                            'type' => 'email',
                            'is_required' => true,
                            'options' => ['placeholder' => 'votre@email.com'],
                            'position' => 2,
                            'order' => 2,
                            'validation_rules' => ['required' => true, 'pattern' => '^[^@]+@[^@]+\\.[^@]+$'],
                        ],
                        [
                            'id' => '550e8400-e29b-41d4-a716-446655441005',
                            'form_version_id' => '550e8400-e29b-41d4-a716-446655440802',
                            'label' => 'Message',
                            'type' => 'textarea',
                            'is_required' => false,
                            'options' => ['placeholder' => 'Votre message'],
                            'position' => 3,
                            'order' => 3,
                            'validation_rules' => ['required' => false, 'max_length' => 1000],
                        ],
                    ],
                    'settings' => [
                        'theme' => [
                            'primary_color' => '#3B82F6',
                            'background_color' => '#FFFFFF',
                            'text_color' => '#1F2937',
                        ],
                        'successMessage' => 'Merci pour votre message !',
                        'emailNotification' => ['enabled' => true],

                    ],
                ],
                'createdAt' => '2024-07-10T15:30:00Z',
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440803',
                'form' => '550e8400-e29b-41d4-a716-446655440302',
                'versionNumber' => 1,
                'schema' => [
                    'title' => 'Inscription Newsletter',
                    'description' => 'Collecte d\'emails pour la newsletter',
                    'status' => 'published',
                    'fields' => [
                        [
                            'id' => '550e8400-e29b-41d4-a716-446655441006',
                            'form_version_id' => '550e8400-e29b-41d4-a716-446655440803',
                            'label' => 'Prénom',
                            'type' => 'text',
                            'is_required' => true,
                            'options' => ['placeholder' => 'Votre prénom'],
                            'position' => 1,
                            'order' => 1,
                            'validation_rules' => ['required' => true, 'min_length' => 2],
                        ],
                        [
                            'id' => '550e8400-e29b-41d4-a716-446655441007',
                            'form_version_id' => '550e8400-e29b-41d4-a716-446655440803',
                            'label' => 'Email',
                            'type' => 'email',
                            'is_required' => true,
                            'options' => ['placeholder' => 'votre@email.com'],
                            'position' => 2,
                            'order' => 2,
                            'validation_rules' => ['required' => true, 'pattern' => '^[^@]+@[^@]+\\.[^@]+$'],
                        ],
                    ],
                    'settings' => [
                        'theme' => [
                            'primary_color' => '#3B82F6',
                            'background_color' => '#FFFFFF',
                            'text_color' => '#1F2937',
                        ],
                        'successMessage' => 'Merci pour votre message !',
                        'emailNotification' => ['enabled' => true],
                    ],
                ],
                'createdAt' => '2024-05-20T14:00:00Z',
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440804',
                'form' => '550e8400-e29b-41d4-a716-446655440303',
                'versionNumber' => 1,
                'schema' => [
                    'title' => 'Sondage Satisfaction',
                    'description' => 'Évaluation de la satisfaction client',
                    'status' => 'draft',
                    'fields' => [
                        [
                            'id' => '550e8400-e29b-41d4-a716-446655441008',
                            'form_version_id' => '550e8400-e29b-41d4-a716-446655440804',
                            'label' => 'Note globale',
                            'type' => 'radio',
                            'is_required' => true,
                            'options' => ['choices' => ['1', '2', '3', '4', '5']],
                            'position' => 1,
                            'order' => 1,
                            'validation_rules' => ['required' => true],
                        ],
                        [
                            'id' => '550e8400-e29b-41d4-a716-446655441009',
                            'form_version_id' => '550e8400-e29b-41d4-a716-446655440804',
                            'label' => 'Commentaires',
                            'type' => 'textarea',
                            'is_required' => false,
                            'options' => ['placeholder' => 'Vos commentaires'],
                            'position' => 2,
                            'order' => 2,
                            'validation_rules' => ['required' => false, 'max_length' => 500],
                        ],
                    ],
                    'settings' => [
                        'theme' => [
                            'primary_color' => '#3B82F6',
                            'background_color' => '#FFFFFF',
                            'text_color' => '#1F2937',
                        ],
                        'successMessage' => 'Merci pour votre message !',
                        'emailNotification' => ['enabled' => true],
                    ],
                ],
                'createdAt' => '2024-06-01T09:00:00Z',
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440805',
                'form' => '550e8400-e29b-41d4-a716-446655440304',
                'versionNumber' => 1,
                'schema' => [
                    'title' => 'Demande de Devis',
                    'description' => 'Formulaire de demande de devis',
                    'status' => 'published',
                    'fields' => [
                        [
                            'id' => '550e8400-e29b-41d4-a716-446655441010',
                            'form_version_id' => '550e8400-e29b-41d4-a716-446655440805',
                            'label' => 'Nom de l\'entreprise',
                            'type' => 'text',
                            'is_required' => true,
                            'options' => ['placeholder' => 'Nom de votre entreprise'],
                            'position' => 1,
                            'order' => 1,
                            'validation_rules' => ['required' => true, 'min_length' => 2],
                        ],
                        [
                            'id' => '550e8400-e29b-41d4-a716-446655441011',
                            'form_version_id' => '550e8400-e29b-41d4-a716-446655440805',
                            'label' => 'Email professionnel',
                            'type' => 'email',
                            'is_required' => true,
                            'options' => ['placeholder' => 'contact@entreprise.com'],
                            'position' => 2,
                            'order' => 2,
                            'validation_rules' => ['required' => true, 'pattern' => '^[^@]+@[^@]+\\.[^@]+$'],
                        ],
                        [
                            'id' => '550e8400-e29b-41d4-a716-446655441012',
                            'form_version_id' => '550e8400-e29b-41d4-a716-446655440805',
                            'label' => 'Budget estimé',
                            'type' => 'select',
                            'is_required' => true,
                            'options' => ['choices' => ['< 1000€', '1000€ - 5000€', '5000€ - 10000€', '> 10000€']],
                            'position' => 3,
                            'order' => 3,
                            'validation_rules' => ['required' => true],
                        ],
                        [
                            'id' => '550e8400-e29b-41d4-a716-446655441013',
                            'form_version_id' => '550e8400-e29b-41d4-a716-446655440805',
                            'label' => 'Description du projet',
                            'type' => 'textarea',
                            'is_required' => true,
                            'options' => ['placeholder' => 'Décrivez votre projet'],
                            'position' => 4,
                            'order' => 4,
                            'validation_rules' => ['required' => true, 'min_length' => 50, 'max_length' => 2000],
                        ],
                    ],
                    'settings' => [
                        'theme' => [
                            'primary_color' => '#3B82F6',
                            'background_color' => '#FFFFFF',
                            'text_color' => '#1F2937',
                        ],
                        'successMessage' => 'Merci pour votre demande ! Nous vous recontacterons rapidement.',
                        'emailNotification' => ['enabled' => true],
                    ],
                ],
                'createdAt' => '2024-04-15T11:00:00Z',
            ],
        ];

        foreach ($formVersions as $versionData) {
            $formVersion = new FormVersion();
            $formVersion->setId($versionData['id']);
            $formVersion->setForm($this->getReference($versionData['form'], Form::class));
            $formVersion->setVersionNumber($versionData['versionNumber']);
            $formVersion->setSchema($versionData['schema']);
            $formVersion->setCreatedAt(new \DateTimeImmutable($versionData['createdAt']));

            $manager->persist($formVersion);
            $this->addReference($versionData['id'], $formVersion);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            FormFixtures::class,
        ];
    }
}
