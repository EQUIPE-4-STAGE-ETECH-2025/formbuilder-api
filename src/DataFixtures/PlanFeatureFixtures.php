<?php

namespace App\DataFixtures;

use App\Entity\Feature;
use App\Entity\Plan;
use App\Entity\PlanFeature;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PlanFeatureFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $planFeatures = [
            // Plan Premium
            [
                'id' => '550e8400-e29b-41d4-a716-446655440401',
                'plan' => '550e8400-e29b-41d4-a716-446655440202',
                'feature' => '550e8400-e29b-41d4-a716-446655440101',
                'limitValue' => 100, 
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440402',
                'plan' => '550e8400-e29b-41d4-a716-446655440202',
                'feature' => '550e8400-e29b-41d4-a716-446655440102',
                'limitValue' => null, // Illimité
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440403',
                'plan' => '550e8400-e29b-41d4-a716-446655440202',
                'feature' => '550e8400-e29b-41d4-a716-446655440104',
                'limitValue' => 10, 
            ],

            // Plan Pro - Toutes les fonctionnalités avec limites
            [
                'id' => '550e8400-e29b-41d4-a716-446655440404',
                'plan' => '550e8400-e29b-41d4-a716-446655440203',
                'feature' => '550e8400-e29b-41d4-a716-446655440101',
                'limitValue' => 1000,
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440405',
                'plan' => '550e8400-e29b-41d4-a716-446655440203',
                'feature' => '550e8400-e29b-41d4-a716-446655440102',
                'limitValue' => null,
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440406',
                'plan' => '550e8400-e29b-41d4-a716-446655440203',
                'feature' => '550e8400-e29b-41d4-a716-446655440103',
                'limitValue' => 50,
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440407',
                'plan' => '550e8400-e29b-41d4-a716-446655440203',
                'feature' => '550e8400-e29b-41d4-a716-446655440104',
                'limitValue' => 50,
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440408',
                'plan' => '550e8400-e29b-41d4-a716-446655440203',
                'feature' => '550e8400-e29b-41d4-a716-446655440105',
                'limitValue' => null,
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440409',
                'plan' => '550e8400-e29b-41d4-a716-446655440203',
                'feature' => '550e8400-e29b-41d4-a716-446655440106',
                'limitValue' => 5,
            ],
        ];

        foreach ($planFeatures as $planFeatureData) {
            $planFeature = new PlanFeature();
            $planFeature->setId($planFeatureData['id']);
            $planFeature->setPlan($this->getReference($planFeatureData['plan'], Plan::class));
            $planFeature->setFeature($this->getReference($planFeatureData['feature'], Feature::class));
            $planFeature->setLimitValue($planFeatureData['limitValue'] ?? null);

            $manager->persist($planFeature);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            PlanFixtures::class,
            FeatureFixtures::class,
        ];
    }
}
