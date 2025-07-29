# 📋 Backlog API Symfony - FormBuilder SaaS

## 🎯 Vue d'ensemble

Ce backlog détaille toutes les tâches nécessaires pour développer l'API Symfony complète du système FormBuilder SaaS. Les tâches sont organisées par priorité et modules fonctionnels, en correspondance avec le backlog UI.

---

## 🚀 Priorité 1 - Authentification et Sécurité

### 1.1 Controllers d'authentification

-   [ ] **AUTH-001** : Créer `AuthController` avec endpoint de connexion

    -   Endpoint : `POST /api/auth/login`
    -   Validation des credentials avec Argon2
    -   Génération et retour du token JWT
    -   Gestion des erreurs (401, 422)
    -   Tests unitaires et d'intégration

-   [ ] **AUTH-002** : Implémenter l'endpoint d'inscription

    -   Endpoint : `POST /api/auth/register`
    -   Validation des données (email unique, force mot de passe)
    -   Hachage du mot de passe avec Argon2
    -   Envoi d'email de vérification
    -   Création automatique du profil utilisateur

-   [ ] **AUTH-003** : Créer l'endpoint de vérification du profil

    -   Endpoint : `GET /api/auth/me`
    -   Validation du token JWT
    -   Retour des informations utilisateur complètes
    -   Gestion de l'expiration du token
    -   Middleware d'authentification

-   [ ] **AUTH-004** : Implémenter la vérification d'email

    -   Endpoint : `GET /api/auth/verify-email`
    -   Validation du token de vérification
    -   Mise à jour du statut `is_email_verified`
    -   Gestion des tokens expirés

-   [ ] **AUTH-005** : Créer les endpoints de réinitialisation de mot de passe

    -   Endpoint : `POST /api/auth/forgot-password`
    -   Endpoint : `POST /api/auth/reset-password`
    -   Génération de tokens sécurisés
    -   Envoi d'emails de réinitialisation

-   [ ] **AUTH-006** : Implémenter la déconnexion
    -   Endpoint : `POST /api/auth/logout`
    -   Invalidation du token côté serveur
    -   Blacklist des tokens révoqués

### 1.2 Services d'authentification

-   [ ] **AUTH-007** : Créer `AuthService`

    -   Logique métier d'authentification
    -   Validation des credentials
    -   Génération des tokens JWT
    -   Gestion des sessions

-   [ ] **AUTH-008** : Créer `EmailService`

    -   Envoi d'emails de vérification
    -   Envoi d'emails de réinitialisation
    -   Templates d'emails personnalisés
    -   Gestion des erreurs d'envoi
    -   Notifications de quotas
    -   Notifications de paiement

-   [ ] **AUTH-009** : Créer `JwtService`
    -   Génération des tokens JWT
    -   Validation des tokens
    -   Gestion de l'expiration
    -   Refresh tokens

### 1.3 DTOs d'authentification

-   [ ] **AUTH-010** : Créer les DTOs d'authentification
    -   `LoginDto` avec validation
    -   `RegisterDto` avec validation
    -   `ResetPasswordDto` avec validation
    -   `UserResponseDto` pour les réponses

---

## 📊 Priorité 2 - Gestion des Formulaires

### 2.1 Controllers de formulaires

-   [ ] **FORMS-001** : Créer `FormController` avec CRUD complet

    -   Endpoint : `GET /api/forms` (liste avec pagination)
    -   Endpoint : `GET /api/forms/{id}` (détail)
    -   Endpoint : `POST /api/forms` (création)
    -   Endpoint : `PUT /api/forms/{id}` (mise à jour)
    -   Endpoint : `DELETE /api/forms/{id}` (suppression)
    -   Validation des permissions utilisateur
    -   Gestion des erreurs appropriées

-   [ ] **FORMS-002** : Implémenter la publication de formulaires

    -   Endpoint : `POST /api/forms/{id}/publish`
    -   Changement de statut vers "published"
    -   Génération du token JWT pour l'iframe
    -   Mise à jour de `published_at`

-   [ ] **FORMS-003** : Créer l'endpoint de génération du code iframe
    -   Endpoint : `GET /api/forms/{id}/embed`
    -   Génération du code HTML avec token JWT
    -   Paramètres de personnalisation
    -   Validation des permissions

### 2.2 Controllers de versions

-   [ ] **VERSIONS-001** : Créer `FormVersionController`
    -   Endpoint : `GET /api/forms/{id}/versions`
    -   Endpoint : `POST /api/forms/{id}/versions`
    -   Endpoint : `POST /api/forms/{id}/versions/{version}/restore`
    -   Endpoint : `DELETE /api/forms/{id}/versions/{version}`
    -   Limitation à 10 versions maximum
    -   Validation des permissions

### 2.3 Services de formulaires

-   [ ] **FORMS-004** : Créer `FormService`

    -   Logique métier de création/modification
    -   Validation des schémas de formulaires
    -   Gestion des permissions
    -   Optimisation des requêtes

-   [ ] **FORMS-005** : Créer `FormVersionService`

    -   Logique de création de versions
    -   Validation des changements
    -   Gestion de l'historique
    -   Restauration de versions

-   [ ] **FORMS-006** : Créer `FormEmbedService`
    -   Génération des tokens JWT pour iframe
    -   Personnalisation du code HTML
    -   Validation de sécurité
    -   Gestion des paramètres

### 2.4 DTOs de formulaires

-   [ ] **FORMS-007** : Créer les DTOs de formulaires
    -   `CreateFormDto` avec validation
    -   `UpdateFormDto` avec validation
    -   `FormResponseDto` pour les réponses
    -   `FormVersionDto` pour les versions
    -   `FormEmbedDto` pour l'intégration

---

## 📝 Priorité 3 - Gestion des Soumissions

### 3.1 Controllers de soumissions

-   [ ] **SUBMISSIONS-001** : Créer `SubmissionController`
    -   Endpoint : `POST /api/forms/{id}/submit` (public)
    -   Endpoint : `GET /api/forms/{id}/submissions` (privé)
    -   Endpoint : `GET /api/forms/{id}/submissions/export`
    -   Validation des données selon le schéma
    -   Vérification des quotas utilisateur
    -   Enregistrement de l'IP et timestamp

### 3.2 Services de soumissions

-   [ ] **SUBMISSIONS-002** : Créer `SubmissionService`

    -   Validation des données de soumission
    -   Vérification des quotas
    -   Enregistrement sécurisé
    -   Notifications email

-   [ ] **SUBMISSIONS-003** : Créer `SubmissionExportService`
    -   Génération de fichiers CSV
    -   Gestion des caractères spéciaux
    -   Pagination des exports
    -   Sécurisation des téléchargements

### 3.3 DTOs de soumissions

-   [ ] **SUBMISSIONS-004** : Créer les DTOs de soumissions
    -   `SubmitFormDto` avec validation dynamique
    -   `SubmissionResponseDto` pour les réponses
    -   `SubmissionExportDto` pour les exports

---

## 💳 Priorité 4 - Gestion des Abonnements et Paiements

### 4.1 Controllers d'abonnements

-   [ ] **SUBSCRIPTIONS-001** : Créer `PlanController`

    -   Endpoint : `GET /api/plans`
    -   Récupération depuis la base de données
    -   Tri par prix croissant
    -   Informations détaillées des plans

-   [ ] **SUBSCRIPTIONS-002** : Créer `SubscriptionController`
    -   Endpoint : `GET /api/users/{id}/subscriptions`
    -   Endpoint : `POST /api/subscriptions`
    -   Endpoint : `PUT /api/subscriptions/{id}`
    -   Intégration avec Stripe
    -   Gestion des changements de plan

### 4.2 Services de paiement

-   [ ] **STRIPE-001** : Créer `StripeService`

    -   Intégration avec l'API Stripe
    -   Création de customers
    -   Gestion des subscriptions
    -   Paiements et factures

-   [ ] **STRIPE-002** : Créer `WebhookService`

    -   Gestion des webhooks Stripe
    -   `customer.subscription.created`
    -   `customer.subscription.updated`
    -   `customer.subscription.deleted`
    -   `invoice.payment_failed`
    -   `invoice.payment_succeeded`

-   [ ] **STRIPE-003** : Créer `PaymentFailureService`
    -   Gestion des échecs de paiement
    -   Notifications utilisateur
    -   Rétrogradation automatique
    -   Suspension des fonctionnalités

### 4.3 Controllers de webhooks

-   [ ] **WEBHOOKS-001** : Créer `WebhookController`
    -   Endpoint : `POST /api/webhooks/stripe`
    -   Validation des signatures Stripe
    -   Gestion des événements de paiement
    -   Mise à jour automatique des abonnements
    -   Logs des webhooks reçus
    -   Gestion des erreurs de webhook

### 4.4 DTOs d'abonnements

-   [ ] **SUBSCRIPTIONS-003** : Créer les DTOs d'abonnements
    -   `PlanDto` pour les plans
    -   `CreateSubscriptionDto` avec validation
    -   `SubscriptionResponseDto` pour les réponses
    -   `WebhookDto` pour les webhooks Stripe

---

## 📊 Priorité 5 - Gestion des Quotas

### 5.1 Controllers de quotas

-   [ ] **QUOTAS-001** : Créer `QuotaController`
    -   Endpoint : `GET /api/users/{id}/quotas`
    -   Calcul en temps réel des utilisations
    -   Comparaison avec les limites du plan
    -   Historique des utilisations

### 5.2 Services de quotas

-   [ ] **QUOTAS-002** : Créer `QuotaService`

    -   Calcul des quotas en temps réel
    -   Vérification avant actions
    -   Notifications automatiques
    -   Blocage des actions

-   [ ] **QUOTAS-003** : Créer `QuotaNotificationService`
    -   Notification à 80% d'utilisation
    -   Notification à 100% d'utilisation
    -   Envoi d'emails d'alerte
    -   Gestion des seuils

### 5.3 Middleware de quotas

-   [ ] **QUOTAS-004** : Créer `QuotaMiddleware`
    -   Vérification automatique des quotas
    -   Blocage des requêtes si quota dépassé
    -   Logs des tentatives d'actions
    -   Messages d'erreur appropriés

---

## 🎛️ Priorité 6 - Tableau de Bord et Statistiques

### 6.1 Controllers de tableau de bord

-   [ ] **DASHBOARD-001** : Créer `DashboardController`

    -   Endpoint : `GET /api/dashboard/stats`
    -   Statistiques utilisateur en temps réel
    -   Métriques de performance
    -   Graphiques de données

-   [ ] **ADMIN-001** : Créer `AdminController`
    -   Endpoint : `GET /api/admin/stats`
    -   Endpoint : `GET /api/admin/users`
    -   Métriques globales de la plateforme
    -   Gestion des utilisateurs

### 6.2 Services de statistiques

-   [ ] **DASHBOARD-002** : Créer `DashboardService`

    -   Calcul des métriques utilisateur
    -   Agrégation des données
    -   Cache des statistiques
    -   Optimisation des requêtes

-   [ ] **ADMIN-002** : Créer `AdminService`
    -   Statistiques globales
    -   Gestion des utilisateurs
    -   Actions administratives
    -   Monitoring de la plateforme

### 6.3 DTOs de statistiques

-   [ ] **DASHBOARD-003** : Créer les DTOs de statistiques
    -   `DashboardStatsDto` pour les stats utilisateur
    -   `AdminStatsDto` pour les stats admin
    -   `UserListDto` pour la liste des utilisateurs

---

## 🔧 Priorité 7 - Configuration et Paramètres

### 7.1 Controllers de paramètres

-   [ ] **SETTINGS-001** : Créer `UserSettingsController`

    -   Endpoint : `GET /api/users/{id}/settings`
    -   Endpoint : `PUT /api/users/{id}/settings`
    -   Gestion des préférences utilisateur
    -   Configuration des notifications

-   [ ] **ADMIN-003** : Créer `SystemSettingsController`
    -   Endpoint : `GET /api/admin/settings`
    -   Endpoint : `PUT /api/admin/settings`
    -   Configuration globale de la plateforme
    -   Mode maintenance

### 7.2 Services de paramètres

-   [ ] **SETTINGS-002** : Créer `UserSettingsService`

    -   Gestion des préférences utilisateur
    -   Validation des paramètres
    -   Sauvegarde sécurisée
    -   Cache des paramètres

-   [ ] **ADMIN-004** : Créer `SystemSettingsService`
    -   Configuration système
    -   Mode maintenance
    -   Paramètres globaux
    -   Cache de configuration

### 7.3 Controllers de notifications

-   [ ] **NOTIFICATIONS-001** : Créer `NotificationController`
    -   Endpoint : `POST /api/notifications/email`
    -   Envoi d'emails de vérification
    -   Envoi d'emails de réinitialisation
    -   Notifications de quotas
    -   Notifications de paiement
    -   Templates d'emails personnalisés
    -   Gestion des erreurs d'envoi

---

## 🛡️ Priorité 8 - Sécurité et Validation

### 8.1 Middleware de sécurité

-   [ ] **SECURITY-001** : Créer `AuthMiddleware`

    -   Validation des tokens JWT
    -   Gestion des rôles utilisateur
    -   Protection des routes sensibles
    -   Logs d'authentification

-   [ ] **SECURITY-002** : Créer `CorsMiddleware`

    -   Configuration CORS pour l'iframe
    -   Headers de sécurité appropriés
    -   Gestion des domaines autorisés
    -   Protection contre les attaques

-   [ ] **SECURITY-003** : Créer `CsrfMiddleware`

    -   Protection CSRF
    -   Validation des tokens
    -   Protection des formulaires
    -   Logs de sécurité

-   [ ] **SECURITY-004** : Créer `RateLimitMiddleware`
    -   Limitation du nombre de requêtes
    -   Protection contre les attaques par force brute
    -   Configuration par endpoint
    -   Headers de rate limiting appropriés

### 8.2 Services de validation

-   [ ] **SECURITY-005** : Créer `FormValidationService`

    -   Validation des schémas de formulaires
    -   Validation des soumissions
    -   Sanitisation des données
    -   Protection contre les injections

-   [ ] **SECURITY-006** : Créer `PermissionService`
    -   Vérification des permissions
    -   Gestion des rôles
    -   Accès aux ressources
    -   Audit des actions

### 8.3 Services d'audit

-   [ ] **SECURITY-007** : Créer `AuditService`
    -   Logs des actions sensibles
    -   Traçabilité des modifications
    -   Historique des connexions
    -   Alertes de sécurité

---

## 🔄 Priorité 9 - Optimisations et Performance

### 9.1 Cache et optimisation

-   [ ] **PERF-001** : Configurer le cache Redis

    -   Cache des formulaires fréquents
    -   Cache des statistiques
    -   Cache des paramètres
    -   Invalidation intelligente

-   [ ] **PERF-002** : Optimiser les requêtes Doctrine
    -   Requêtes optimisées avec jointures
    -   Pagination des listes longues
    -   Indexation de la base de données
    -   Lazy loading approprié

### 9.2 Monitoring et logs

-   [ ] **MONITORING-001** : Configurer le logging

    -   Logs d'erreurs API
    -   Logs de performance
    -   Logs de sécurité
    -   Alertes automatiques

-   [ ] **MONITORING-002** : Créer `HealthController`
    -   Endpoint : `GET /api/health`
    -   Vérification de la base de données
    -   Vérification des services externes (Stripe, Email)
    -   Métriques de santé et performance
    -   Statut des services critiques
    -   Temps de réponse des endpoints

---

## 🧪 Priorité 10 - Tests et Documentation

### 10.1 Tests unitaires

-   [ ] **TESTS-001** : Tests des services

    -   Tests `AuthService`
    -   Tests `FormService`
    -   Tests `SubmissionService`
    -   Tests `QuotaService`
    -   Tests `StripeService`

-   [ ] **TESTS-002** : Tests des controllers
    -   Tests `AuthController`
    -   Tests `FormController`
    -   Tests `SubmissionController`
    -   Tests `DashboardController`

### 10.2 Tests d'intégration

-   [ ] **TESTS-003** : Tests d'intégration
    -   Tests des endpoints complets
    -   Tests avec base de données
    -   Tests de performance
    -   Tests de sécurité

### 10.3 Documentation API

-   [ ] **DOC-001** : Configuration OpenAPI/Swagger

    -   Documentation automatique des endpoints
    -   Exemples de requêtes et réponses
    -   Codes d'erreur détaillés
    -   Authentification documentée

-   [ ] **DOC-002** : Documentation technique

    -   Guide d'installation
    -   Guide de configuration
    -   Guide de déploiement
    -   Guide de maintenance

-   [ ] **DOC-003** : Codes de retour HTTP standardisés

    -   200 : Succès (GET, PUT, PATCH)
    -   201 : Créé (POST)
    -   204 : Succès sans contenu (DELETE)
    -   400 : Erreur de validation
    -   401 : Non authentifié
    -   403 : Non autorisé
    -   404 : Ressource non trouvée
    -   422 : Erreur de validation métier
    -   429 : Trop de requêtes (Rate limiting)
    -   500 : Erreur serveur

-   [ ] **DOC-004** : Exemples de requêtes et réponses
    -   Exemples JSON pour chaque endpoint
    -   Headers d'authentification
    -   Codes d'erreur détaillés
    -   Cas d'usage courants
