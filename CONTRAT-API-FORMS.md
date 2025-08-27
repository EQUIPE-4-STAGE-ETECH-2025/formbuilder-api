# 📋 Contrat API - Formulaires et Versions

## 🎯 Vue d'ensemble

Ce document définit le contrat API pour les tâches React suivantes :

-   **FORMS-000** à **FORMS-007** : Migration et CRUD des formulaires
-   **VERSIONS-001** à **VERSIONS-004** : Gestion des versions de formulaires

Tous les endpoints documentés ici sont implémentés et fonctionnels dans l'API Symfony.

---

## 🔐 Authentification

**La plupart des endpoints requièrent une authentification Bearer Token JWT.**

```http
Authorization: Bearer YOUR_JWT_TOKEN
```

**Endpoints publics (sans authentification) :**

-   `GET /api/public/forms/{id}` : Récupération des données d'un formulaire publié pour affichage embed
-   `GET /api/public/forms/{id}/embed` : Génération du code d'intégration pour les formulaires publiés

**Codes d'erreur d'authentification :**

-   `401` : Token manquant ou invalide
-   `403` : Permissions insuffisantes

---

## 📝 Endpoints des Formulaires

### 1. Récupérer tous les formulaires

**Tâche :** `FORMS-001`

```http
GET /api/forms
```

**Paramètres de requête :**
| Paramètre | Type | Obligatoire | Description |
|-----------|------|-------------|-------------|
| `page` | `integer` | Non | Numéro de page (défaut: 1) |
| `limit` | `integer` | Non | Nombre d'éléments par page (défaut: 20, max: 100) |
| `status` | `string` | Non | Filtrer par statut (`DRAFT`, `PUBLISHED`, `ARCHIVED`) |
| `search` | `string` | Non | Recherche dans le titre et description |

**Réponse de succès (200) :**

```json
{
  "success": true,
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Formulaire de contact",
      "description": "Un formulaire simple pour contacter l'équipe",
      "status": "PUBLISHED",
      "publishedAt": "2024-01-15T10:30:00Z",
      "createdAt": "2024-01-15T09:00:00Z",
      "updatedAt": "2024-01-15T10:30:00Z",
      "schema": {
        "fields": [
          {
            "id": "field-1",
            "type": "text",
            "label": "Nom",
            "required": true
          }
        ],
        "settings": {
          "submitButtonText": "Envoyer"
        }
      },
      "submissionsCount": 42,
      "currentVersion": {
        "id": "version-id",
        "versionNumber": 3,
        "schema": {...},
        "createdAt": "2024-01-15T10:30:00Z",
        "fields": [...]
      },
      "versions": [...]
    }
  ],
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 50
  }
}
```

**Réponse d'erreur (500) :**

```json
{
    "success": false,
    "message": "Erreur lors de la récupération des formulaires"
}
```

### 2. Récupérer un formulaire spécifique

**Tâche :** `FORMS-002`

```http
GET /api/forms/{id}
```

**Paramètres d'URL :**
| Paramètre | Type | Description |
|-----------|------|-------------|
| `id` | `string` | UUID du formulaire |

**Réponse de succès (200) :**

```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "title": "Formulaire de contact",
    "description": "Un formulaire simple pour contacter l'équipe",
    "status": "PUBLISHED",
    "publishedAt": "2024-01-15T10:30:00Z",
    "createdAt": "2024-01-15T09:00:00Z",
    "updatedAt": "2024-01-15T10:30:00Z",
    "schema": {
      "fields": [
        {
          "id": "field-1",
          "type": "text",
          "label": "Nom",
          "required": true,
          "placeholder": "Votre nom complet",
          "validation": {
            "minLength": 2,
            "maxLength": 100
          }
        },
        {
          "id": "field-2",
          "type": "email",
          "label": "Email",
          "required": true,
          "placeholder": "votre@email.com"
        }
      ],
      "settings": {
        "submitButtonText": "Envoyer le message",
        "successMessage": "Merci pour votre message !",
        "theme": {
          "primaryColor": "#007bff",
          "fontFamily": "Arial, sans-serif"
        }
      }
    },
    "submissionsCount": 42,
    "currentVersion": {
      "id": "version-id",
      "versionNumber": 3,
      "schema": {...},
      "createdAt": "2024-01-15T10:30:00Z",
      "fields": []
    },
    "versions": []
  }
}
```

**Réponses d'erreur :**

-   `404` : Formulaire non trouvé
-   `403` : Accès refusé (pas propriétaire)
-   `500` : Erreur serveur

### 3. Créer un nouveau formulaire

**Tâche :** `FORMS-003`

```http
POST /api/forms
```

**Corps de la requête :**

```json
{
    "title": "Mon nouveau formulaire",
    "description": "Description détaillée du formulaire",
    "status": "DRAFT",
    "schema": {
        "fields": [
            {
                "id": "field-1",
                "type": "text",
                "label": "Nom",
                "required": true,
                "placeholder": "Votre nom"
            }
        ],
        "settings": {
            "submitButtonText": "Envoyer"
        }
    }
}
```

**Validation des champs :**
| Champ | Type | Obligatoire | Contraintes |
|-------|------|-------------|-------------|
| `title` | `string` | Oui | 3-255 caractères |
| `description` | `string` | Oui | 10-1000 caractères |
| `status` | `string` | Non | `DRAFT`, `PUBLISHED`, `ARCHIVED` (défaut: `DRAFT`) |
| `schema` | `object` | Non | Objet JSON valide |

**Réponse de succès (201) :**

```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "title": "Mon nouveau formulaire",
    "description": "Description détaillée du formulaire",
    "status": "DRAFT",
    "publishedAt": null,
    "createdAt": "2024-01-15T14:30:00Z",
    "updatedAt": "2024-01-15T14:30:00Z",
    "schema": {...},
    "submissionsCount": 0,
    "currentVersion": null,
    "versions": []
  },
  "message": "Formulaire créé avec succès"
}
```

**Réponses d'erreur :**

-   `400` : Données JSON invalides
-   `422` : Erreurs de validation
-   `500` : Erreur serveur

**Exemple d'erreur de validation (422) :**

```json
{
    "success": false,
    "message": "Données de validation invalides",
    "errors": [
        "Le titre doit contenir au moins 3 caractères",
        "La description doit contenir au moins 10 caractères"
    ]
}
```

### 4. Mettre à jour un formulaire

**Tâche :** `FORMS-004`

```http
PUT /api/forms/{id}
```

**Corps de la requête (tous les champs optionnels) :**

```json
{
  "title": "Titre mis à jour",
  "description": "Nouvelle description",
  "status": "PUBLISHED",
  "schema": {
    "fields": [...],
    "settings": {...}
  }
}
```

**Réponse de succès (200) :**

```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "title": "Titre mis à jour",
    "description": "Nouvelle description",
    "status": "PUBLISHED",
    "publishedAt": "2024-01-15T15:00:00Z",
    "createdAt": "2024-01-15T09:00:00Z",
    "updatedAt": "2024-01-15T15:00:00Z",
    "schema": {...},
    "submissionsCount": 42,
    "currentVersion": {...},
    "versions": [...]
  },
  "message": "Formulaire mis à jour avec succès"
}
```

**Réponses d'erreur :**

-   `400` : Données JSON invalides
-   `404` : Formulaire non trouvé
-   `403` : Permissions insuffisantes
-   `422` : Erreurs de validation
-   `500` : Erreur serveur

### 5. Supprimer un formulaire

**Tâche :** `FORMS-005`

```http
DELETE /api/forms/{id}
```

**Réponse de succès (204) :**

```json
{
    "success": true,
    "message": "Formulaire supprimé avec succès"
}
```

**Réponses d'erreur :**

-   `404` : Formulaire non trouvé
-   `403` : Permissions insuffisantes
-   `500` : Erreur serveur

### 6. Publier un formulaire

**Tâche :** `FORMS-006`

```http
POST /api/forms/{id}/publish
```

**Réponse de succès (200) :**

```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "title": "Formulaire de contact",
    "description": "Un formulaire simple pour contacter l'équipe",
    "status": "PUBLISHED",
    "publishedAt": "2024-01-15T15:30:00Z",
    "createdAt": "2024-01-15T09:00:00Z",
    "updatedAt": "2024-01-15T15:30:00Z",
    "schema": {...},
    "submissionsCount": 42,
    "currentVersion": {...},
    "versions": [...]
  },
  "message": "Formulaire publié avec succès"
}
```

**Réponses d'erreur :**

-   `404` : Formulaire non trouvé
-   `403` : Permissions insuffisantes
-   `500` : Erreur serveur

### 7. Récupérer les données d'un formulaire publié

**Tâche :** `FORMS-007a`

```http
GET /api/public/forms/{id}
```

**Authentification :** Aucune authentification requise

**Note :** Cet endpoint permet de récupérer les données complètes d'un formulaire publié pour l'affichage dans un composant embed. Seules les informations nécessaires à l'affichage sont retournées, les données sensibles sont filtrées.

**Paramètres d'URL :**
| Paramètre | Type | Description |
|-----------|------|-------------|
| `id` | `string` | UUID du formulaire |

**Réponse de succès (200) :**

```json
{
    "success": true,
    "data": {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "title": "Formulaire de contact",
        "description": "Un formulaire simple pour contacter l'équipe",
        "status": "PUBLISHED",
        "publishedAt": "2024-01-15T10:30:00Z",
        "createdAt": "2024-01-15T09:00:00Z",
        "updatedAt": "2024-01-15T10:30:00Z",
        "schema": {
            "fields": [
                {
                    "id": "field-1",
                    "type": "text",
                    "label": "Nom",
                    "required": true,
                    "placeholder": "Votre nom complet",
                    "validation": {
                        "minLength": 2,
                        "maxLength": 100
                    }
                }
            ],
            "settings": {
                "submitButtonText": "Envoyer le message",
                "successMessage": "Merci pour votre message !",
                "theme": {
                    "primaryColor": "#007bff",
                    "fontFamily": "Arial, sans-serif"
                }
            }
        }
    }
}
```

**Réponses d'erreur :**

-   `404` : Formulaire non trouvé
-   `403` : Formulaire non publié ou non disponible publiquement
-   `500` : Erreur serveur

### 8. Générer le code d'intégration (iframe)

**Tâche :** `FORMS-007b`

```http
GET /api/public/forms/{id}/embed
```

**Authentification :** Aucune authentification requise

**Note :** Cet endpoint permet d'obtenir le code d'intégration pour n'importe quel formulaire publié, sans authentification. Il est conçu pour être utilisé par des applications tierces ou des sites web qui souhaitent intégrer des formulaires.

**Paramètres de requête optionnels :**
| Paramètre | Type | Description |
|-----------|------|-------------|
| `width` | `string` | Largeur de l'iframe (ex: "100%", "600px") |
| `height` | `string` | Hauteur de l'iframe (ex: "auto", "400px") |
| `border` | `string` | Bordure CSS (ex: "1px solid #ccc") |
| `borderRadius` | `string` | Rayon des coins (ex: "8px") |
| `boxShadow` | `string` | Ombre CSS (ex: "0 2px 10px rgba(0,0,0,0.1)") |

**Réponse de succès (200) :**

```json
{
    "success": true,
    "data": {
        "embedCode": "<iframe src=\"https://votre-domaine.com/embed/550e8400-e29b-41d4-a716-446655440000?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...\" width=\"100%\" height=\"600\" frameborder=\"0\" style=\"border: 1px solid #ccc; border-radius: 8px;\"></iframe>",
        "embedUrl": "https://votre-domaine.com/embed/550e8400-e29b-41d4-a716-446655440000?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "customization": {
            "width": "100%",
            "height": "600",
            "border": "1px solid #ccc",
            "borderRadius": "8px",
            "boxShadow": "0 2px 10px rgba(0,0,0,0.1)"
        }
    }
}
```

**Réponses d'erreur :**

-   `400` : Paramètres de personnalisation invalides
-   `404` : Formulaire non trouvé
-   `403` : Formulaire non publié ou non disponible publiquement
-   `500` : Erreur serveur

---

## 🔄 Endpoints des Versions de Formulaires

### 1. Récupérer les versions d'un formulaire

**Tâche :** `VERSIONS-001`

```http
GET /api/forms/{formId}/versions
```

**Réponse de succès (200) :**

```json
{
    "success": true,
    "data": [
        {
            "id": "version-uuid-1",
            "versionNumber": 3,
            "schema": {
                "fields": [
                    {
                        "id": "field-1",
                        "type": "text",
                        "label": "Nom complet",
                        "required": true
                    }
                ],
                "settings": {
                    "submitButtonText": "Envoyer"
                }
            },
            "createdAt": "2024-01-15T10:30:00Z",
            "fields": []
        },
        {
            "id": "version-uuid-2",
            "versionNumber": 2,
            "schema": {
                "fields": [
                    {
                        "id": "field-1",
                        "type": "text",
                        "label": "Nom",
                        "required": true
                    }
                ],
                "settings": {
                    "submitButtonText": "Valider"
                }
            },
            "createdAt": "2024-01-14T15:20:00Z",
            "fields": []
        }
    ]
}
```

**Réponses d'erreur :**

-   `404` : Formulaire non trouvé
-   `403` : Accès refusé
-   `500` : Erreur serveur

### 2. Créer une nouvelle version

**Tâche :** `VERSIONS-002`

```http
POST /api/forms/{formId}/versions
```

**Corps de la requête :**

```json
{
    "schema": {
        "fields": [
            {
                "id": "field-1",
                "type": "text",
                "label": "Nom complet",
                "required": true,
                "placeholder": "Votre nom et prénom",
                "validation": {
                    "minLength": 2,
                    "maxLength": 100
                }
            },
            {
                "id": "field-2",
                "type": "email",
                "label": "Adresse email",
                "required": true,
                "placeholder": "votre@email.com"
            }
        ],
        "settings": {
            "submitButtonText": "Envoyer le formulaire",
            "successMessage": "Merci pour votre soumission !",
            "theme": {
                "primaryColor": "#007bff"
            }
        }
    }
}
```

**Réponse de succès (201) :**

```json
{
  "success": true,
  "data": {
    "id": "new-version-uuid",
    "versionNumber": 4,
    "schema": {
      "fields": [...],
      "settings": {...}
    },
    "createdAt": "2024-01-15T16:00:00Z",
    "fields": []
  },
  "message": "Version créée avec succès"
}
```

**Réponses d'erreur :**

-   `400` : Schéma manquant ou invalide
-   `404` : Formulaire non trouvé
-   `403` : Permissions insuffisantes
-   `422` : Schéma de formulaire invalide
-   `500` : Erreur serveur

**Note :** Le système limite automatiquement à 10 versions maximum par formulaire. Les anciennes versions sont supprimées automatiquement si nécessaire.

### 3. Restaurer une version

**Tâche :** `VERSIONS-003`

```http
POST /api/forms/{formId}/versions/{version}/restore
```

**Paramètres d'URL :**
| Paramètre | Type | Description |
|-----------|------|-------------|
| `formId` | `string` | UUID du formulaire |
| `version` | `integer` | Numéro de la version à restaurer |

**Réponse de succès (200) :**

```json
{
  "success": true,
  "data": {
    "id": "restored-version-uuid",
    "versionNumber": 5,
    "schema": {
      "fields": [...],
      "settings": {...}
    },
    "createdAt": "2024-01-15T16:15:00Z",
    "fields": []
  },
  "message": "Version 2 restaurée avec succès"
}
```

**Réponses d'erreur :**

-   `404` : Formulaire ou version non trouvé(e)
-   `403` : Permissions insuffisantes
-   `500` : Erreur serveur

**Note :** La restauration crée une nouvelle version basée sur l'ancienne, elle ne modifie pas l'historique existant.

### 4. Supprimer une version

**Tâche :** `VERSIONS-004`

```http
DELETE /api/forms/{formId}/versions/{version}
```

**Réponse de succès (204) :**

```json
{
    "success": true,
    "message": "Version 2 supprimée avec succès"
}
```

**Réponses d'erreur :**

-   `400` : Impossible de supprimer la version active
-   `404` : Formulaire ou version non trouvé(e)
-   `403` : Permissions insuffisantes
-   `500` : Erreur serveur

---

## 🏗️ Structure des Données

### Objet Form

```typescript
interface Form {
    id: string; // UUID
    title: string; // 3-255 caractères
    description: string; // 10-1000 caractères
    status: "DRAFT" | "PUBLISHED" | "ARCHIVED";
    publishedAt: string | null; // ISO 8601 date
    createdAt: string; // ISO 8601 date
    updatedAt: string; // ISO 8601 date
    schema: FormSchema;
    submissionsCount?: number;
    currentVersion?: FormVersion;
    versions?: FormVersion[];
}
```

### Objet FormVersion

```typescript
interface FormVersion {
    id: string; // UUID
    versionNumber: number; // Entier positif
    schema: FormSchema;
    createdAt: string; // ISO 8601 date
    fields: FormField[];
}
```

### Objet FormSchema

```typescript
interface FormSchema {
    fields: FormField[];
    settings: FormSettings;
}

interface FormField {
    id: string; // Identifiant unique du champ
    type:
        | "text"
        | "email"
        | "number"
        | "textarea"
        | "select"
        | "checkbox"
        | "radio"
        | "date"
        | "file"
        | "url"
        | "tel";
    label: string; // Libellé affiché
    required: boolean;
    placeholder?: string;
    validation?: {
        minLength?: number;
        maxLength?: number;
        min?: number;
        max?: number;
        pattern?: string;
        options?: string[]; // Pour select, checkbox, radio
    };
    conditional?: {
        field: string; // ID du champ condition
        value: any; // Valeur qui déclenche l'affichage
    };
}

interface FormSettings {
    submitButtonText: string;
    successMessage?: string;
    errorMessage?: string;
    theme?: {
        primaryColor?: string;
        fontFamily?: string;
        backgroundColor?: string;
    };
    notifications?: {
        email?: string; // Email de notification
        webhook?: string; // URL de webhook
    };
}
```

---

## 🚨 Gestion des Erreurs

### Codes de statut HTTP

-   `200` : Succès (GET, PUT, POST pour publish/restore)
-   `201` : Créé avec succès (POST)
-   `204` : Supprimé avec succès (DELETE)
-   `400` : Requête invalide
-   `401` : Non authentifié
-   `403` : Permissions insuffisantes
-   `404` : Ressource non trouvée
-   `422` : Erreurs de validation
-   `500` : Erreur serveur interne

### Format des réponses d'erreur

```json
{
    "success": false,
    "message": "Description de l'erreur",
    "errors": ["Erreur 1", "Erreur 2"] // Optionnel pour les erreurs de validation
}
```

---

## 🔒 Permissions et Sécurité

### Règles d'accès

-   **Lecture** : Seul le propriétaire peut accéder à ses formulaires
-   **Écriture** : Seul le propriétaire peut modifier ses formulaires
-   **Suppression** : Seul le propriétaire peut supprimer ses formulaires
-   **Admin** : Les administrateurs ont accès à tous les formulaires

### Validation des schémas

-   Les schémas de formulaire sont validés côté serveur
-   Types de champs supportés : `text`, `email`, `number`, `textarea`, `select`, `checkbox`, `radio`, `date`, `file`, `url`, `tel`
-   Validation des règles de validation (longueur, format, etc.)
-   Vérification de la cohérence des champs conditionnels

### Quotas et limitations

-   Maximum 10 versions par formulaire
-   Validation des quotas utilisateur avant création
-   Nettoyage automatique des anciennes versions

---

## 📚 Exemples d'utilisation

### Créer un formulaire de contact simple

```javascript
const response = await fetch("/api/forms", {
    method: "POST",
    headers: {
        Authorization: "Bearer " + token,
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        title: "Formulaire de contact",
        description: "Un formulaire simple pour nous contacter",
        status: "DRAFT",
        schema: {
            fields: [
                {
                    id: "name",
                    type: "text",
                    label: "Nom complet",
                    required: true,
                    placeholder: "Votre nom et prénom",
                },
                {
                    id: "email",
                    type: "email",
                    label: "Email",
                    required: true,
                    placeholder: "votre@email.com",
                },
                {
                    id: "message",
                    type: "textarea",
                    label: "Message",
                    required: true,
                    placeholder: "Votre message...",
                },
            ],
            settings: {
                submitButtonText: "Envoyer le message",
                successMessage:
                    "Merci pour votre message ! Nous vous répondrons bientôt.",
            },
        },
    }),
});
```

### Publier et générer le code d'intégration

```javascript
// 1. Publier le formulaire (authentifié)
await fetch(`/api/forms/${formId}/publish`, {
    method: "POST",
    headers: {
        Authorization: "Bearer " + token,
    },
});

// 2a. Récupérer les données du formulaire (sans authentification)
const formResponse = await fetch(`/api/public/forms/${formId}`);
const formData = await formResponse.json();
console.log(formData.data.schema);

// 2b. Générer le code d'intégration (sans authentification)
const embedResponse = await fetch(
    `/api/public/forms/${formId}/embed?width=100%&height=600`
);

const embedData = await embedResponse.json();
console.log(embedData.data.embedCode);
```

### Créer une nouvelle version

```javascript
const versionResponse = await fetch(`/api/forms/${formId}/versions`, {
    method: "POST",
    headers: {
        Authorization: "Bearer " + token,
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        schema: {
            fields: [
                {
                    id: "name",
                    type: "text",
                    label: "Nom et prénom", // Modification du libellé
                    required: true,
                },
                {
                    id: "phone", // Nouveau champ ajouté
                    type: "tel",
                    label: "Téléphone",
                    required: false,
                },
            ],
            settings: {
                submitButtonText: "Envoyer",
                successMessage: "Formulaire soumis avec succès !",
            },
        },
    }),
});
```

---

## 🎯 Points d'attention pour l'implémentation React

1. **Gestion des erreurs** : Toujours vérifier le champ `success` dans les réponses
2. **Pagination** : Implémenter la pagination pour la liste des formulaires
3. **Validation côté client** : Reproduire les validations serveur côté client pour une meilleure UX
4. **États de chargement** : Gérer les états de chargement pour toutes les opérations
5. **Permissions** : Vérifier les permissions avant d'afficher les actions (édition, suppression)
6. **Schémas complexes** : Bien gérer la structure imbriquée des schémas de formulaire
7. **Versions** : Limiter l'affichage à 10 versions maximum
8. **Dates** : Toutes les dates sont au format ISO 8601 UTC
9. **Endpoints publics** :
    - Utiliser `/api/public/forms/{id}` pour récupérer les données d'un formulaire publié
    - Utiliser `/api/public/forms/{id}/embed` pour générer le code d'intégration
10. **Sécurité** : Les formulaires publics ne sont accessibles que s'ils sont publiés (`status: "PUBLISHED"`)
11. **Données filtrées** : L'endpoint public `/api/public/forms/{id}` filtre automatiquement les données sensibles

---

_Ce contrat API est basé sur l'implémentation Symfony existante et tous les endpoints sont fonctionnels et testés._
