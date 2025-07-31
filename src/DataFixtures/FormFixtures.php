<?php

namespace App\DataFixtures;

use App\Entity\Form;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class FormFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $forms = [
            [
                'id' => '550e8400-e29b-41d4-a716-446655440301',
                'user' => '550e8400-e29b-41d4-a716-446655440002',
                'title' => 'Contact Lead Generation',
                'description' => 'Formulaire de contact pour prospects',
                'status' => 'PUBLISHED',
                'publishedAt' => '2024-07-10T15:30:00Z',
                'createdAt' => '2024-06-15T10:00:00Z',
                'updatedAt' => '2024-07-10T15:30:00Z',
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440302',
                'user' => '550e8400-e29b-41d4-a716-446655440002',
                'title' => 'Inscription Newsletter',
                'description' => 'Collecte d\'emails pour la newsletter',
                'status' => 'PUBLISHED',
                'publishedAt' => '2024-05-20T14:00:00Z',
                'createdAt' => '2024-05-20T14:00:00Z',
                'updatedAt' => '2024-07-12T09:15:00Z',
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440303',
                'user' => '550e8400-e29b-41d4-a716-446655440003',
                'title' => 'Sondage Satisfaction',
                'description' => 'Évaluation de la satisfaction client',
                'status' => 'DRAFT',
                'publishedAt' => null,
                'createdAt' => '2024-06-01T09:00:00Z',
                'updatedAt' => '2024-06-01T09:00:00Z',
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440304',
                'user' => '550e8400-e29b-41d4-a716-446655440004',
                'title' => 'Demande de Devis',
                'description' => 'Formulaire de demande de devis',
                'status' => 'PUBLISHED',
                'publishedAt' => '2024-04-15T11:00:00Z',
                'createdAt' => '2024-04-15T11:00:00Z',
                'updatedAt' => '2024-04-15T11:00:00Z',
            ],
        ];

        foreach ($forms as $formData) {
            $form = new Form();
            $form->setId($formData['id']);
            $form->setUser($this->getReference($formData['user'], User::class));
            $form->setTitle($formData['title']);
            $form->setDescription($formData['description']);
            $form->setStatus($formData['status']);

            if ($formData['publishedAt']) {
                $form->setPublishedAt(new \DateTimeImmutable($formData['publishedAt']));
            }

            $form->setCreatedAt(new \DateTimeImmutable($formData['createdAt']));
            $form->setUpdatedAt(new \DateTimeImmutable($formData['updatedAt']));

            $manager->persist($form);
            $this->addReference($formData['id'], $form);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
