# 📋 Backlog API Symfony - FormBuilder SaaS

## 🎯 Vue d'ensemble

Ce backlog détaille toutes les tâches nécessaires pour développer l'API Symfony complète du système FormBuilder SaaS. Les tâches sont organisées par priorité et modules fonctionnels, en correspondance avec le backlog UI.

---

## 🚀 Priorité 1 - Authentification et Sécurité

### 1.1 Controllers d'authentification

-   [x] **AUTH-001** (Dépendances -> AUTH-007, AUTH-010) : Créer `AuthController` avec endpoint de connexion

    -   Endpoint : `POST /api/auth/login`
    -   Validation des credentials avec Argon2
    -   Génération et retour du token JWT
    -   Gestion des erreurs (401, 422)
    -   Tests unitaires et d'intégration

-   [x] **AUTH-002** `(Dépendances -> AUTH-008, AUTH-013)` : Implémenter l'endpoint d'inscription

    -   Endpoint : `POST /api/auth/register`
    -   Validation des données (email unique, force mot de passe)
    -   Hachage du mot de passe avec Argon2
    -   Envoi d'email de vérification
    -   Création automatique du profil utilisateur

-   [x] **AUTH-003** `(Dépendances -> AUTH-009, AUTH-012)` : Créer l'endpoint de vérification du profil

    -   Endpoint : `GET /api/auth/me`
    -   Validation du token JWT
    -   Retour des informations utilisateur complètes
    -   Gestion de l'expiration du token
    -   Utilisation du middleware d'authentification

-   [x] **AUTH-004** `(Dépendances -> AUTH-008)` : Implémenter la vérification d'email

    -   Endpoint : `GET /api/auth/verify-email`
    -   Validation du token de vérification
    -   Mise à jour du statut `is_email_verified`
    -   Gestion des tokens expirés

-   [x] **AUTH-005** `(Dépendances -> AUTH-008)` : Créer les endpoints de réinitialisation de mot de passe

    -   Endpoint : `POST /api/auth/forgot-password`
    -   Endpoint : `POST /api/auth/reset-password`
    -   Génération de tokens sécurisés
    -   Envoi d'emails de réinitialisation

-   [x] **AUTH-006** `(Dépendances -> AUTH-009)` : Implémenter la déconnexion
    -   Endpoint : `POST /api/auth/logout`
    -   Invalidation du token côté serveur
    -   Blacklist des tokens révoqués

### 1.2 Services d'authentification

-   [x] **AUTH-007** `(Dépendances -> AUTH-009)` : Créer `AuthService`

    -   Logique métier d'authentification
    -   Validation des credentials
    -   Utilisation du JwtService pour les tokens
    -   Gestion des sessions

-   [x] **AUTH-008** `(Dépendances -> Aucune)` : Créer `EmailService`

    -   Envoi d'emails de vérification
    -   Envoi d'emails de réinitialisation
    -   Templates d'emails personnalisés
    -   Gestion des erreurs d'envoi
    -   Notifications de quotas
    -   Notifications de paiement

-   [x] **AUTH-009** `(Dépendances -> Aucune)` : Créer `JwtService`
    -   Génération des tokens JWT
    -   Validation des tokens
    -   Gestion de l'expiration
    -   Refresh tokens

### 1.3 DTOs d'authentification

-   [x] **AUTH-010** `(Dépendances -> Aucune)` : Créer les DTOs d'authentification
    -   `LoginDto` avec validation
    -   `RegisterDto` avec validation
    -   `ResetPasswordDto` avec validation
    -   `UserResponseDto` pour les réponses

### 1.4 Gestion des rôles et autorisations

-   [x] **AUTH-011** `(Dépendances -> AUTH-012)` : Implémenter la gestion des rôles utilisateurs

    -   Créer `UserService` pour la gestion des rôles
    -   Endpoint : `GET /api/users/{id}/roles`
    -   Endpoint : `PUT /api/users/{id}/roles`
    -   Validation des permissions d'administration
    -   Gestion des rôles : USER, ADMIN

-   [x] **AUTH-012** `(Dépendances -> Aucune)` : Créer `AuthorizationService`

    -   Vérification des permissions par rôle
    -   Middleware d'autorisation
    -   Gestion des accès aux ressources
    -   Voters Symfony pour les permissions complexes

-   [x] **AUTH-013** `(Dépendances -> Aucune)` : Implémenter la validation de force du mot de passe

    -   Service de validation des règles de sécurité
    -   Configuration des règles (longueur, caractères spéciaux, etc.)
    -   Messages d'erreur personnalisés
    -   Validation côté serveur

-   [x] **AUTH-014** `(Dépendances -> AUTH-012)` : Créer gestion du profil utilisateur
    -   Endpoint : `GET /api/users/{id}/profile`
    -   Endpoint : `PUT /api/users/{id}/profile`
    -   Validation des données personnelles

---
 
## 📊 Priorité 2 - Gestion des Formulaires

### 2.1 Controllers de formulaires

-   [x] **FORMS-001** `(Dépendances -> AUTH-012, FORMS-004, FORMS-007)` : Créer `FormController` avec CRUD complet

    -   Endpoint : `GET /api/forms` (liste avec pagination)
    -   Endpoint : `GET /api/forms/{id}` (détail)
    -   Endpoint : `POST /api/forms` (création)
    -   Endpoint : `PUT /api/forms/{id}` (mise à jour)
    -   Endpoint : `DELETE /api/forms/{id}` (suppression)
    -   Validation des permissions utilisateur
    -   Gestion des erreurs appropriées

-   [x] **FORMS-002** `(Dépendances -> FORMS-006, AUTH-012)` : Implémenter la publication de formulaires

    -   Endpoint : `POST /api/forms/{id}/publish`
    -   Changement de statut vers "published"
    -   Génération du token JWT pour l'iframe
    -   Mise à jour de `published_at`

-   [x] **FORMS-003** `(Dépendances -> FORMS-006, AUTH-012)` : Créer l'endpoint de génération du code iframe
    -   Endpoint : `GET /api/forms/{id}/embed`
    -   Génération du code HTML avec token JWT
    -   Paramètres de personnalisation
    -   Validation des permissions

### 2.2 Controllers de versions

-   [x] **VERSIONS-001** `(Dépendances -> FORMS-005, AUTH-012)` : Créer `FormVersionController`
    -   Endpoint : `GET /api/forms/{id}/versions`
    -   Endpoint : `POST /api/forms/{id}/versions`
    -   Endpoint : `POST /api/forms/{id}/versions/{version}/restore`
    -   Endpoint : `DELETE /api/forms/{id}/versions/{version}`
    -   Limitation à 10 versions maximum
    -   Validation des permissions

### 2.3 Services de formulaires

-   [x] **FORMS-004** `(Dépendances -> AUTH-012)` : Créer `FormService`

    -   Logique métier de création/modification
    -   Validation des schémas de formulaires
    -   Gestion des permissions
    -   Optimisation des requêtes

-   [x] **FORMS-005** `(Dépendances -> Aucune)` : Créer `FormVersionService`

    -   Logique de création de versions
    -   Validation des changements
    -   Gestion de l'historique
    -   Restauration de versions

-   [x] **FORMS-006** `(Dépendances -> AUTH-009)` : Créer `FormEmbedService`
    -   Génération des tokens JWT pour iframe (via JwtService)
    -   Personnalisation du code HTML
    -   Validation de sécurité
    -   Gestion des paramètres

### 2.4 DTOs de formulaires

-   [x] **FORMS-007** `(Dépendances -> Aucune)` : Créer les DTOs de formulaires
    -   `CreateFormDto` avec validation
    -   `UpdateFormDto` avec validation
    -   `FormResponseDto` pour les réponses
    -   `FormVersionDto` pour les versions
    -   `FormEmbedDto` pour l'intégration

### 2.5 Validation et templates

-   [x] **FORMS-008** `(Dépendances -> Aucune)` : Implémenter la validation des schémas de formulaires

    -   Service de validation JSON Schema
    -   Validation des types de champs
    -   Gestion des champs conditionnels
    -   Tests de validation des formulaires
    -   Validation en temps réel côté serveur

---

## 📝 Priorité 3 - Gestion des Soumissions

### 3.1 Controllers de soumissions

-   [x] **SUBMISSIONS-001** `(Dépendances -> FORMS-008, QUOTAS-002, SUBMISSIONS-002, AUTH-012)` : Créer `SubmissionController`

    -   Endpoint : `POST /api/forms/{id}/submit` (public)
    -   Endpoint : `GET /api/forms/{id}/submissions` (privé)
    -   Endpoint : `GET /api/forms/{id}/submissions/export`
    -   Validation des données selon le schéma
    -   Vérification des quotas utilisateur
    -   Enregistrement de l'IP et timestamp

### 3.2 Services de soumissions

-   [x] **SUBMISSIONS-002** `(Dépendances -> AUTH-008, QUOTAS-002)` : Créer `SubmissionService`

    -   Validation des données de soumission
    -   Vérification des quotas
    -   Enregistrement sécurisé
    -   Notifications email

-   [x] **SUBMISSIONS-003** `(Dépendances -> Aucune)` : Créer `SubmissionExportService`

    -   Génération de fichiers CSV
    -   Gestion des caractères spéciaux
    -   Pagination des exports
    -   Sécurisation des téléchargements

### 3.3 DTOs de soumissions

-   [x] **SUBMISSIONS-004** `(Dépendances -> Aucune)` : Créer les DTOs de soumissions

    -   `SubmitFormDto` avec validation dynamique
    -   `SubmissionResponseDto` pour les réponses
    -   `SubmissionExportDto` pour les exports

### 3.4 Validation et analytics

-   [x] **SUBMISSIONS-005** `(Dépendances -> FORMS-008)` : Implémenter la validation des données de soumission

    -   Validation dynamique selon le schéma du formulaire
    -   Gestion des types de données (email, date, nombre, etc.)
    -   Validation des champs obligatoires
    -   Messages d'erreur personnalisés
    -   Validation côté serveur et client

-   [x] **SUBMISSIONS-006** `(Dépendances -> Aucune)` : Créer `SubmissionAnalyticsService`
    -   Statistiques des soumissions par formulaire
    -   Endpoint : `GET /api/forms/{id}/submissions/analytics`
    -   Métriques de conversion
    -   Graphiques de tendances
    -   Analyse des performances des formulaires

---

## 💳 Priorité 4 - Gestion des Abonnements et Paiements

### 4.1 Controllers d'abonnements

-   [ ] **SUBSCRIPTIONS-001** `(Dépendances -> Aucune)` : Créer `PlanController`

    -   Endpoint : `GET /api/plans`
    -   Récupération depuis la base de données
    -   Tri par prix croissant
    -   Informations détaillées des plans

-   [ ] **SUBSCRIPTIONS-002** `(Dépendances -> STRIPE-001, SUBSCRIPTIONS-004, AUTH-012)` : Créer `SubscriptionController`
    -   Endpoint : `GET /api/users/{id}/subscriptions`
    -   Endpoint : `POST /api/subscriptions`
    -   Endpoint : `PUT /api/subscriptions/{id}`
    -   Intégration avec Stripe
    -   Gestion des changements de plan

### 4.2 Services de paiement

-   [ ] **STRIPE-001** `(Dépendances -> Aucune)` : Créer `StripeService`

    -   Intégration avec l'API Stripe
    -   Création de customers
    -   Gestion des subscriptions
    -   Paiements et factures

-   [ ] **STRIPE-002** `(Dépendances -> Aucune)` : Créer `WebhookService`

    -   Gestion des webhooks Stripe
    -   `customer.subscription.created`
    -   `customer.subscription.updated`
    -   `customer.subscription.deleted`
    -   `invoice.payment_failed`
    -   `invoice.payment_succeeded`

-   [ ] **STRIPE-003** `(Dépendances -> AUTH-008)` : Créer `PaymentFailureService`
    -   Gestion des échecs de paiement
    -   Notifications utilisateur
    -   Rétrogradation automatique
    -   Suspension des fonctionnalités

### 4.3 Controllers de webhooks

-   [ ] **WEBHOOKS-001** `(Dépendances -> STRIPE-002, STRIPE-003, AUTH-012)` : Créer `WebhookController`
    -   Endpoint : `POST /api/webhooks/stripe`
    -   Validation des signatures Stripe
    -   Gestion des événements de paiement
    -   Mise à jour automatique des abonnements
    -   Logs des webhooks reçus
    -   Gestion des erreurs de webhook

### 4.4 DTOs d'abonnements

-   [ ] **SUBSCRIPTIONS-003** `(Dépendances -> Aucune)` : Créer les DTOs d'abonnements
    -   `PlanDto` pour les plans
    -   `CreateSubscriptionDto` avec validation
    -   `SubscriptionResponseDto` pour les réponses
    -   `WebhookDto` pour les webhooks Stripe

### 4.5 Gestion des statuts et fonctionnalités

-   [ ] **SUBSCRIPTIONS-004** `(Dépendances -> Aucune)` : Implémenter la gestion des statuts d'abonnement

    -   Service de gestion des statuts (ACTIVE, SUSPENDED, CANCELLED)
    -   Logique de rétrogradation automatique
    -   Endpoint : `GET /api/subscriptions/{id}/status`
    -   Endpoint : `PUT /api/subscriptions/{id}/status`
    -   Gestion des transitions de statut

-   [ ] **SUBSCRIPTIONS-005** `(Dépendances -> Aucune)` : Créer `PlanFeatureService`
    -   Gestion des fonctionnalités par plan
    -   Validation des accès aux fonctionnalités
    -   Configuration des limites par plan
    -   Service de vérification des permissions
    -   Endpoint : `GET /api/plans/{id}/features`

---

## 📊 Priorité 5 - Gestion des Quotas

### 5.1 Controllers de quotas

-   [x] **QUOTAS-001** `(Dépendances -> QUOTAS-002, AUTH-012)` : Créer `QuotaController`
    -   Endpoint : `GET /api/users/{id}/quotas`
    -   Calcul en temps réel des utilisations
    -   Comparaison avec les limites du plan
    -   Gestion des permissions et erreurs

### 5.2 Services de quotas

-   [x] **QUOTAS-002** `(Dépendances -> Aucune)` : Créer `QuotaService`

    -   Calcul des quotas en temps réel
    -   Vérification avant actions (create_form, submit_form, upload_file)
    -   Blocage des actions si quota dépassé
    -   Intégration avec les plans d'abonnement

-   [x] **QUOTAS-003** `(Dépendances -> AUTH-008)` : Créer `QuotaNotificationService`
    -   Notification à 80% d'utilisation
    -   Notification à 100% d'utilisation
    -   Envoi d'emails d'alerte simples

### 5.3 Middleware de quotas

-   [x] **QUOTAS-004** `(Dépendances -> QUOTAS-002)` : Créer `QuotaMiddleware`
    -   Vérification automatique des quotas
    -   Blocage des requêtes si quota dépassé
    -   Routes protégées : création formulaires, soumissions, uploads
    -   Support taille de fichiers dynamique
    -   Messages d'erreur avec code HTTP 429

---

## 🎛️ Priorité 6 - Tableau de Bord et Statistiques

### 6.1 Controllers de tableau de bord

-   [x] **DASHBOARD-001** `(Dépendances -> DASHBOARD-002, AUTH-012)` : Créer `DashboardController`

    -   Endpoint : `GET /api/dashboard/stats`
    -   Statistiques utilisateur en temps réel
    -   Métriques de performance
    -   Graphiques de données

-   [x] **ADMIN-001** `(Dépendances -> ADMIN-002, AUTH-012)` : Créer `AdminController`
    -   Endpoint : `GET /api/admin/stats`
    -   Endpoint : `GET /api/admin/users`
    -   Métriques globales de la plateforme
    -   Gestion des utilisateurs

### 6.2 Services de statistiques

-   [x] **DASHBOARD-002** `(Dépendances -> Aucune)` : Créer `DashboardService`

    -   Calcul des métriques utilisateur
    -   Agrégation des données
    -   Cache des statistiques
    -   Optimisation des requêtes

-   [x] **ADMIN-002** `(Dépendances -> Aucune)` : Créer `AdminService`
    -   Statistiques globales
    -   Gestion des utilisateurs
    -   Actions administratives
    -   Monitoring de la plateforme

### 6.3 DTOs de statistiques

-   [x] **DASHBOARD-003** `(Dépendances -> Aucune)` : Créer les DTOs de statistiques
    -   `DashboardStatsDto` pour les stats utilisateur
    -   `AdminStatsDto` pour les stats admin
    -   `UserListDto` pour la liste des utilisateurs

---

## 🔧 Priorité 7 - Configuration et Paramètres

### 7.1 Controllers de paramètres

-   [ ] **SETTINGS-001** `(Dépendances -> SETTINGS-002, AUTH-012)` : Créer `UserSettingsController`

    -   Endpoint : `GET /api/users/{id}/settings`
    -   Endpoint : `PUT /api/users/{id}/settings`
    -   Gestion des préférences utilisateur
    -   Configuration des notifications

-   [ ] **ADMIN-003** `(Dépendances -> ADMIN-004, AUTH-012)` : Créer `SystemSettingsController`
    -   Endpoint : `GET /api/admin/settings`
    -   Endpoint : `PUT /api/admin/settings`
    -   Configuration globale de la plateforme
    -   Mode maintenance

### 7.2 Services de paramètres

-   [ ] **SETTINGS-002** `(Dépendances -> Aucune)` : Créer `UserSettingsService`

    -   Gestion des préférences utilisateur
    -   Validation des paramètres
    -   Sauvegarde sécurisée
    -   Cache des paramètres

-   [ ] **ADMIN-004** `(Dépendances -> Aucune)` : Créer `SystemSettingsService`
    -   Configuration système
    -   Mode maintenance
    -   Paramètres globaux
    -   Cache de configuration

### 7.3 Controllers de notifications

-   [ ] **NOTIFICATIONS-001** `(Dépendances -> AUTH-008)` : Créer `NotificationController`
    -   Endpoint : `POST /api/notifications/email`
    -   Envoi d'emails de vérification
    -   Envoi d'emails de réinitialisation
    -   Notifications de quotas
    -   Notifications de paiement
    -   Templates d'emails personnalisés
    -   Gestion des erreurs d'envoi
