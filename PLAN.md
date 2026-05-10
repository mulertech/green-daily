# green-daily — Plan d'implémentation

App Symfony de tracking nutritionnel orientée végétarien. Usage personnel/famille (multi-comptes), mobile-first, Tailwind, Twig + Stimulus/Turbo. Suggestion de recettes via webhook synchrone vers n8n interne.

## 1. Stack & infra

- **PHP 8.4 / Symfony 7.x** (aligné `mulertech/`)
- **PostgreSQL 16+** (cohérence infra)
- **Twig + Tailwind + JS vanilla** via **AssetMapper** (pas de Stimulus, pas de Turbo, pas de framework JS)
- **Docker dev** : `mulertech/docker-dev` → `./vendor/bin/mtdocker` (auto-détection des modules : frankenphp + symfony + postgres + mailpit + adminer)
- **Docker prod** : déploiement via `../prod-templates` (à brancher au moment du déploiement, hors scope L1)
- **Réseaux Docker (prod)** : rejoindre uniquement `traefik_proxy` — `docker-n8n` y est déjà attaché, joignable directement par nom de container (même pattern que `docker-mulertech-www` ↔ `docker-n8n`).
- **Auth** : Symfony Security, email + mot de passe, form login. Pas de register public en v1 → commande CLI `app:user:create` (limite l'accès à toi/famille). Reset password Symfony bundle.

## 2. Modèle de données

```
User
 - id, email, password, roles
 - sex (enum: male|female)
 - birth_date
 - created_at

Food                                   # CIQUAL
 - id (alim_code CIQUAL)
 - name_fr (alim_nom_fr)
 - group, sub_group
 - search_vector (tsvector pour autocomplete FR)

Nutrient                               # référentiel des 11 nutriments suivis
 - id, code (B12, FE, ZN, VITD, OMEGA3_DHA_EPA, IODE, CA, MG, VITA, SE, PROT)
 - name, unit (mg/µg/g)

FoodNutrient                           # valeur par 100 g
 - food_id, nutrient_id, amount_per_100g

Rda                                    # ANSES par profil
 - nutrient_id
 - sex (male|female|any)
 - age_min, age_max
 - amount, unit
 - source (ANSES 2016 / EFSA…)

ConsumptionEntry
 - id, user_id
 - food_id
 - quantity_grams
 - consumed_at (date+heure, default now)
 - created_at

RecipeSuggestion                       # historique des appels n8n
 - id, user_id
 - requested_at
 - target_nutrients (jsonb : restant à atteindre)
 - response_markdown
 - duration_ms, status
```

Index : `consumption_entry(user_id, consumed_at)` ; GIN sur `food.search_vector`.

## 3. Import CIQUAL 2020

- Télécharger le XLSX depuis data.gouv.fr (URL versionnée stockée dans un README du dossier `import/`)
- Commande `app:ciqual:import` (Symfony Console)
  - Parse XLSX (`phpoffice/phpspreadsheet`)
  - Normalise les colonnes vers nos 11 nutriments (mapping explicite documenté)
  - Gère les valeurs `traces` / `<` / `-` → null
  - Upsert idempotent sur `alim_code`
  - Met à jour `search_vector` (français, unaccent)
- Fixtures pour `Nutrient` et `Rda` (ANSES adulte H/F, tranches d'âge standard 18-65/65+)

## 4. Calcul des % AJR

Service `DailyIntakeCalculator` :
1. Charge les `ConsumptionEntry` du user pour une date donnée
2. Pour chaque entry → somme `amount_per_100g * quantity / 100` par nutriment
3. Récupère le RDA applicable au profil (sex + âge calculé depuis birth_date)
4. Retourne `[{nutrient, consumed, target, percent, remaining}]`

Cache léger par (user_id, date) invalidé à chaque write d'entry.

## 5. UX (mobile-first, Tailwind)

Pages (navigation classique, form submits + redirect/PRG, pas de SPA) :
- **Login** : email + mdp
- **Dashboard (/)** : date du jour, liste des 11 nutriments avec barre de progression %AJR, code couleur (rouge <50, orange <80, vert ≥80, bleu si >120). Lien vers page d'ajout.
- **Ajout aliment** (`/entries/new`) : champ recherche `<input list="foods-datalist">` + `<datalist id="foods-datalist">` rempli dynamiquement, input quantité (g), submit form classique → redirect dashboard.
- **Historique du jour** : liste des entries du jour avec bouton suppression (form POST classique).
- **Suggérer une recette** : bouton form POST `/recipe/suggest` → page de résultat avec markdown rendu.
- **Profil** : email, sexe, date de naissance.

**Autocomplete (option `<datalist>` natif + fetch vanilla)** :
- `<input name="food_query" list="foods-datalist" autocomplete="off">` + `<datalist id="foods-datalist">`
- Module JS vanilla `assets/foods-search.js` (importé via AssetMapper) :
  - écoute `input` sur le champ, debounce 200ms
  - `fetch('/api/foods/search?q=' + encodeURIComponent(q))` → JSON `[{id, label}]`
  - vide et remplit `<datalist>` avec `<option value="label" data-id="id">`
  - sur submit du form, un listener convertit la `value` sélectionnée en `food_id` via une lookup map locale (`Map<label,id>`) ; champ caché `food_id` envoyé au serveur
  - fallback serveur : si `food_id` absent ou label modifié à la main, le contrôleur fait une recherche exact-match sur `name_fr`
- Le navigateur gère l'UI (ouverture, navigation clavier, mobile) → zéro CSS custom, zéro dépendance.

Composants Twig réutilisés :
- `nutrient_bar.html.twig`
- `food_picker.html.twig` (input + datalist + hidden food_id)

Pas de PWA en v1 (sortie de scope).

## 6. Intégration IA via n8n (webhook synchrone)

- Service `RecipeSuggester` :
  - Calcule les nutriments restants (target - consumed) pour la date
  - POST JSON vers `http://docker-n8n:5678/webhook/green-daily/recipe` (URL interne Docker)
  - Body : `{ user_id, date, locale: "fr", remaining: [{code, amount, unit}], constraints: {vegetarian: true} }`
  - Timeout HTTP 60s ; en cas d'échec → message d'erreur friendly, pas de retry auto
  - Persiste la `RecipeSuggestion` (succès ou échec)
- Côté n8n : à créer (workflow `Green_daily_recipe`) — webhook trigger → AI agent (OpenAI via credential `MulerTechAi`) → respond webhook avec markdown
- Secret partagé : header `X-Webhook-Token` configuré côté green-daily (env) et côté n8n (vérif au début du workflow)

## 7. Sécurité & conformité

- HTTPS via Traefik (label dans `compose.yml`)
- CSRF Symfony sur toutes les forms
- Rate limit sur `/recipe/suggest` (Symfony RateLimiter, 10/h/user) — évite de cramer les tokens OpenAI
- Hash mdp Argon2id (défaut Symfony)
- Aucune donnée santé sensible exportée hors infra (n8n est interne)

## 8. Découpage en lots

| Lot | Contenu | Validation |
|-----|---------|------------|
| **L1 — Bootstrap** | `composer create-project`, install AssetMapper + Tailwind (sans Stimulus ni Turbo), Docker via `mulertech/docker-dev`, `compose.yml` avec Postgres, Traefik labels | `mtdocker ps-ai` OK, page d'accueil HTTPS |
| **L2 — Auth + profil** | Entité User, Security, login/logout, commande `app:user:create`, page profil | Login fonctionnel, profil éditable |
| **L3 — Schéma + CIQUAL** | Entités Food/Nutrient/FoodNutrient/Rda, fixtures Nutrient + Rda ANSES, commande import CIQUAL, endpoint autocomplete | Recherche "lentille" → résultats pertinents |
| **L4 — Saisie + dashboard** | ConsumptionEntry, calculateur AJR, dashboard mobile, ajout/suppression Turbo | Ajouter "200g lentilles cuites" reflète % fer/protéines |
| **L5 — Recette IA** | Service `RecipeSuggester`, page de résultat, workflow n8n côté `../n8n` (à versionner là-bas) | Bouton suggère une recette en français, stockée |
| **L6 — Polish** | Mobile QA, accessibilité de base, traductions FR, `all-ai` clean | `mtdocker all-ai` vert |

## 9. Points ouverts / décisions à valider plus tard

- **AJR grossesse/allaitement** : hors v1, mais laisser le schéma `Rda` extensible (ajouter colonnes `pregnant`/`lactating` plus tard).
- **Compatibilité unités CIQUAL** : valider mapping pour vitamine A (RE vs µg), oméga-3 (somme DHA+EPA dans CIQUAL ?), iode (souvent absent → fallback table de sels iodés).
- **Conversion β-carotène → vitamine A** : appliquer ratio 1:12 (RE) ou afficher séparément ? → afficher séparément en v1.
- **Backups** : à intégrer dans `vps/` une fois en prod (pg_dump quotidien chiffré).

## 10. À ne PAS faire en v1

- Historique multi-jours / graphiques (lot futur)
- PWA installable
- Register public
- Génération de listes de courses
- Mobile native app
