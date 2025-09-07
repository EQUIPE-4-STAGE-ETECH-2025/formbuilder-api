<?php

namespace App\DataFixtures;

use App\Entity\Feature;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class FeatureFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $features = [
            ['id' => '550e8400-e29b-41d4-a716-446655440103', 'code' => 'api_access', 'label' => 'API avancée'],
            ['id' => '550e8400-e29b-41d4-a716-446655440106', 'code' => 'priority_support', 'label' => 'Support prioritaire'],
        ];

        foreach ($features as $featureData) {
            $feature = new Feature();
            $feature->setId($featureData['id']);
            $feature->setCode($featureData['code']);
            $feature->setLabel($featureData['label']);

            $manager->persist($feature);
            $this->addReference($featureData['id'], $feature);
        }

        $manager->flush();
    }
}
