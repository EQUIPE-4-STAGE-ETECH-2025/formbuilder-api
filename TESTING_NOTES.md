# Notes sur les Tests - FormBuilder API

## Problèmes Identifiés

### 1. Tests Stripe

Les tests Stripe posaient des problèmes majeurs :

-   Les services Stripe appellent directement les APIs Stripe réelles via `Stripe::setApiKey()`
-   Même avec des mocks, les appels API réels sont effectués
-   Impossibilité de mocker efficacement les classes statiques Stripe

### 2. Services avec Dépendances Externes

Les services qui appellent des APIs externes nécessitent une approche différente pour les tests.

## Solutions Appliquées

### Tests Stripe Simplifiés

-   **Avant** : Tests complexes avec mocks qui ne fonctionnaient pas
-   **Après** : Tests concentrés sur la logique métier (authentification, validation)
-   **Bénéfice** : Tests stables qui ne dépendent pas d'APIs externes

### Tests SubmissionAnalytics Réalistes

-   **Avant** : Mocks complexes qui n'étaient pas appelés
-   **Après** : Tests avec vrais données en base et validation des comportements
-   **Bénéfice** : Tests d'intégration plus représentatifs

## Recommandations Futures

### Pour les Tests Stripe

1. **Tests Unitaires** : Créer des tests unitaires pour les services Stripe avec des mocks appropriés
2. **Tests d'Intégration** : Utiliser l'environnement de test Stripe avec de vraies clés de test
3. **Tests End-to-End** : Utiliser des outils comme Stripe CLI pour simuler les webhooks

### Architecture Recommandée

```php
// Exemple d'interface pour faciliter les tests
interface StripeServiceInterface
{
    public function createCheckoutSession(User $user, CheckoutSessionDto $dto): Session;
    public function getActiveProducts(): array;
}

// Service principal
class StripeService implements StripeServiceInterface
{
    // Implémentation réelle
}

// Service de test
class MockStripeService implements StripeServiceInterface
{
    // Implémentation mockée pour les tests
}
```

### Configuration de Test

```yaml
# config/services_test.yaml
when@test:
    services:
        App\Service\StripeService:
            class: App\Service\MockStripeService
```

## Types de Tests Recommandés

### 1. Tests Unitaires

-   Services sans dépendances externes
-   Logique métier pure
-   Validation des DTOs

### 2. Tests d'Intégration

-   Controllers avec vrais services
-   Base de données réelle (test)
-   Authentification et autorisation

### 3. Tests de Contract

-   Validation des réponses API
-   Structure des données
-   Codes de statut HTTP

### 4. Tests E2E (Optionnels)

-   Parcours utilisateur complets
-   Intégrations externes réelles
-   Environnement de staging

## Commandes de Test Utiles

```bash
# Tests rapides (sans couverture)
./vendor/bin/phpunit --no-coverage

# Tests spécifiques
./vendor/bin/phpunit tests/Controller/StripeControllerTest.php

# Tests avec base de données fraîche
php bin/console doctrine:schema:drop --force --env=test
php bin/console doctrine:schema:create --env=test
php bin/console doctrine:fixtures:load --env=test --no-interaction
./vendor/bin/phpunit
```

## Métriques de Qualité

### Couverture de Code

-   **Objectif** : 80% minimum
-   **Focus** : Logique métier critique
-   **Exclusions** : DTOs, configurations, migrations

### Performance des Tests

-   **Objectif** : < 30 secondes pour la suite complète
-   **Optimisations** : Utilisation de transactions, mocks appropriés
-   **Parallélisation** : Considérer PHPUnit parallèle pour les gros projets

## Maintenance

### Révision Régulière

-   Supprimer les tests obsolètes
-   Mettre à jour les fixtures
-   Vérifier la pertinence des assertions

### Monitoring

-   Temps d'exécution des tests
-   Taux de réussite en CI/CD
-   Couverture de code par module
