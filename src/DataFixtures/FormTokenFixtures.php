<?php

namespace App\DataFixtures;

use App\Entity\FormToken;
use App\Entity\Form;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class FormTokenFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $formTokens = [
            [
                'id' => '550e8400-e29b-41d4-a716-446655440901',
                'form' => '550e8400-e29b-41d4-a716-446655440301',
                'jwt' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJmb3JtX2lkIjoiZm9ybS0xIiwiaWF0IjoxNjM5NzI5NjAwLCJleHAiOjE2Mzk4MTYwMDB9.mock-signature',
                'expiresAt' => '2024-12-31T23:59:59Z',
                'createdAt' => '2024-07-14T10:30:00Z',
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440902',
                'form' => '550e8400-e29b-41d4-a716-446655440302',
                'jwt' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJmb3JtX2lkIjoiZm9ybS0yIiwiaWF0IjoxNjM5NzI5NjAwLCJleHAiOjE2Mzk4MTYwMDB9.mock-signature',
                'expiresAt' => '2024-12-31T23:59:59Z',
                'createdAt' => '2024-07-14T10:30:00Z',
            ],
        ];

        foreach ($formTokens as $tokenData) {
            $formToken = new FormToken();
            $formToken->setId($tokenData['id']);
            $formToken->setForm($this->getReference($tokenData['form'], Form::class));
            $formToken->setJwt($tokenData['jwt']);
            $formToken->setExpiresAt(new \DateTimeImmutable($tokenData['expiresAt']));
            $formToken->setCreatedAt(new \DateTimeImmutable($tokenData['createdAt']));
            
            $manager->persist($formToken);
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