<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Cette fixture ne fait rien directement
        // Elle sert juste à orchestrer l'ordre de chargement des autres fixtures
        // via les dépendances définies dans getDependencies()
    }

    public function getDependencies(): array
    {
        return [
            // 1. Utilisateurs (base de toutes les relations)
            UserFixtures::class,
            
            // 2. Fonctionnalités (indépendantes)
            FeatureFixtures::class,
            
            // 3. Plans (indépendants)
            PlanFixtures::class,
            
            // 4. Associations Plan-Fonctionnalité (dépend de Plans et Features)
            PlanFeatureFixtures::class,
            
            // 5. Abonnements (dépend de Users et Plans)
            SubscriptionFixtures::class,
            
            // 6. Formulaires (dépend de Users)
            FormFixtures::class,
            
            // 7. Versions de formulaires (dépend de Forms)
            FormVersionFixtures::class,
            
            // 8. Champs de formulaires (dépend de FormVersions)
            FormFieldFixtures::class,
            
            // 9. Soumissions (dépend de Forms)
            SubmissionFixtures::class,
            
            // 10. Statuts de quota (dépend de Users)
            QuotaStatusFixtures::class,
            
            // 11. Logs d'audit (dépend de Users)
            AuditLogFixtures::class,
            
            // 12. Tokens de formulaires (dépend de Forms)
            FormTokenFixtures::class,
        ];
    }
} 