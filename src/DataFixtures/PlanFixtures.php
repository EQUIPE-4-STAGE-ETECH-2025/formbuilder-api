<?php

namespace App\DataFixtures;

use App\Entity\Plan;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PlanFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $plans = [
            [
                'id' => '550e8400-e29b-41d4-a716-446655440201',
                'name' => 'Free',
                'priceCents' => 0,
                'stripeProductId' => 'prod_free',
                'maxForms' => 3,
                'maxSubmissionsPerMonth' => 500,
                'maxStorageMb' => 10,
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440202',
                'name' => 'Premium',
                'priceCents' => 2900,
                'stripeProductId' => 'prod_premium',
                'maxForms' => 20,
                'maxSubmissionsPerMonth' => 10000,
                'maxStorageMb' => 100,
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440203',
                'name' => 'Pro',
                'priceCents' => 9900,
                'stripeProductId' => 'prod_pro',
                'maxForms' => -1, // Illimité
                'maxSubmissionsPerMonth' => 100000,
                'maxStorageMb' => 500,
            ],
        ];

        foreach ($plans as $planData) {
            $plan = new Plan();
            $plan->setId($planData['id']);
            $plan->setName($planData['name']);
            $plan->setPriceCents($planData['priceCents']);
            $plan->setStripeProductId($planData['stripeProductId']);
            $plan->setMaxForms($planData['maxForms']);
            $plan->setMaxSubmissionsPerMonth($planData['maxSubmissionsPerMonth']);
            $plan->setMaxStorageMb($planData['maxStorageMb']);
            
            $manager->persist($plan);
            $this->addReference($planData['id'], $plan);
        }

        $manager->flush();
    }
} 