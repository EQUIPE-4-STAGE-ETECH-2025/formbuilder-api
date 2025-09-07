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
            // Plan Premium - Support prioritaire uniquement
            ['id' => '550e8400-e29b-41d4-a716-446655440401', 'plan' => '550e8400-e29b-41d4-a716-446655440202', 'feature' => '550e8400-e29b-41d4-a716-446655440106'],

            // Plan Pro - Support prioritaire + API avancée
            ['id' => '550e8400-e29b-41d4-a716-446655440402', 'plan' => '550e8400-e29b-41d4-a716-446655440203', 'feature' => '550e8400-e29b-41d4-a716-446655440103'],
            ['id' => '550e8400-e29b-41d4-a716-446655440403', 'plan' => '550e8400-e29b-41d4-a716-446655440203', 'feature' => '550e8400-e29b-41d4-a716-446655440106'],
        ];

        foreach ($planFeatures as $planFeatureData) {
            $planFeature = new PlanFeature();
            $planFeature->setId($planFeatureData['id']);
            $planFeature->setPlan($this->getReference($planFeatureData['plan'], Plan::class));
            $planFeature->setFeature($this->getReference($planFeatureData['feature'], Feature::class));

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
