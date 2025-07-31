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
            ['id' => '550e8400-e29b-41d4-a716-446655440101', 'code' => 'unlimited_forms', 'label' => 'Formulaires illimités'],
            ['id' => '550e8400-e29b-41d4-a716-446655440102', 'code' => 'webhooks', 'label' => 'Webhooks'],
            ['id' => '550e8400-e29b-41d4-a716-446655440103', 'code' => 'api_access', 'label' => 'Accès API'],
            ['id' => '550e8400-e29b-41d4-a716-446655440104', 'code' => 'advanced_analytics', 'label' => 'Analyses avancées'],
            ['id' => '550e8400-e29b-41d4-a716-446655440105', 'code' => 'custom_domains', 'label' => 'Domaines personnalisés'],
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
