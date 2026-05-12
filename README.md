# My Little Shop

Exercice technique de démonstration — API REST Symfony + SPA React pour la gestion d'une chaîne de boutiques de mode.

## Note d'accompagnement

### Ce qui a été réalisé

IA utilisée comme accélérateur : Claude + Codex. Développement itératif, feature par feature, avec recadrage sur l'architecture, Symfony et la qualité du code.

Quelques points structurants :

- **Frontend** : interface React vibe codée, sans tests, hors périmètre mais souhaitée pour visualiser le résultat et créer un effet waouh.
- **Structure** : Docker, `Makefile`, PostGIS, JWT Lexik, OpenAPI et outillage repris de mes habitudes projet.
- **Backend** : progression endpoint par endpoint, en commençant par les shops.
- **Géodistance** : logique déjà utilisée sur un ancien projet, reprise ici pour calculer et trier les boutiques par distance.
- **Tests** : ajout de tests unitaires, fonctionnels et de cas d'injections SQL courants.
- **Qualité** : `php-cs-fixer`, `phpstan`, PHPUnit et Dama Doctrine Test Bundle.
- **Bruno** : collection ajoutée pour tester et debugger l'API sans Postman.
- **Pagination** : service maison léger, avec réponses encapsulées dans `PaginatedResult`.

#### Cadrage IA

- **Sérialisation** : passage de tableaux JSON mappés manuellement au serializer Symfony avec groupes de sérialisation.
- **Validation** : remplacement des contrôles `if` par des DTO et le validateur Symfony.
- **Persistance** : requêtes SQL dans les contrôleurs > centralisées dans les repositories.
- **SQL** : suppression du SQL brut généré au profit du DQL > vigilance sur les injections.
- **Fixtures** : remplacement des fixtures PHP par des fixtures YAML Alice/Hautelook.

### Ce qui resterait à faire pour une exploitation pro

- **Organisation** : séparer tests fonctionnels et tests unitaires.
- **CI/CD** : ajouter une pipeline GitHub ou GitLab.
- **PHPStan** : rendre l'analyse encore plus stricte.
- **JWT** : injecter les clés publique/privée autrement que par fichiers dans un contexte de pods distribués.
- **Droits** : remplacer le contrôle basique actuel par une vraie gestion des rôles et permissions.
- **Pagination** : revoir l'hydratation de `Shop::distance` et éviter le `$rowHydrator` si possible.
- **FrontendController** : solution pratique sans Twig, mais inélégante pour une production.
- **Healthcheck** : vérification des services, pas seulement retourner `200`.
- **Désérialisation** : pousser les serializers/DTO, notamment pour sortir `hydrate` de `ShopController`.
- **Cache frontend** : générer des assets versionnés avec hash, plutôt que contourner le cache navigateur côté Nginx.
- **Documentation IA** : ajouter les fichiers du type `CLAUDE.md` avec consignes et conventions projet.

## Stack technique

| Couche | Technologie |
|---|---|
| Langage backend | PHP 8.5 |
| Framework | Symfony 7.4 |
| ORM | Doctrine ORM 3 + DBAL 3 |
| Base de données | PostgreSQL 16 |
| Authentification | JWT (`lexik/jwt-authentication-bundle`) |
| Framework frontend | React 18 + React Router 6 |
| CSS | Tailwind CSS v4 |
| Bundler | Webpack Encore 5 |
| Carte | Leaflet.js 1.9 |

## Démarrage

```bash
make          # build, install dépendances, compiler les assets, démarrer
make db       # créer la base, migrer, charger les fixtures
```

L'application écoute sur `http://127.0.0.1:8000`.

## API

### Authentification

Toutes les routes `/api/*` (sauf `/api/login` et `/api/health`) sont protégées par un token JWT Bearer.

## Choix techniques notables

### DQL exclusif pour les requêtes complexes

Toutes les requêtes métier passent par le DQL Doctrine plutôt que du SQL brut, y compris les opérations les plus complexes :

- **Filtre par boutique sur les produits** : sous-requête `EXISTS` en DQL pour retrouver les produits ayant du stock dans les boutiques sélectionnées.
- **Comptage pour la pagination** : méthodes `countByFilters` / `countByShopIds` qui répliquent les conditions `WHERE` sans `LIMIT`/`OFFSET`.

### Fonctions DQL personnalisées pour la géodistance

Doctrine ORM 3 ne fournit pas nativement `COS`, `SIN`, `ACOS` et `RADIANS`. Quatre classes `FunctionNode` ont été créées (`src/Doctrine/DQL/`) et enregistrées dans `config/packages/doctrine.yaml`.

Elles permettent d'exprimer la **formule de Haversine** entièrement en DQL :

## Qualité

| Outil | Rôle |
|---|---|
| `php-cs-fixer` | Formatage automatique selon PSR-12 |
| `phpstan` (niveau max) | Analyse statique (256 M de mémoire allouée) |
| `phpunit` | Tests d'intégration API + tests unitaires |

### Stratégie de test

Les tests d'intégration étendent `ApiTestCase` qui :
- Crée un client HTTP Symfony (`WebTestCase`)
- S'appuie sur `dama/doctrine-test-bundle` pour isoler les tests via une connexion statique en environnement de test
- Expose des helpers de création de fixtures de test (`createUser`, `createShop`, `createProduct`, `createStock`)

La méthode `assertShape(array $expectedKeys, array $actual)` vérifie qu'une réponse JSON contient **exactement** les clés attendues — ni plus, ni moins. Cela garantit qu'aucune propriété sensible (ex. hash de mot de passe) ne peut fuiter sans que les tests le détectent.

## Données de démo

Les fixtures (`fixtures/`) sont chargées avec Alice (Hautelook) :

- 3 utilisateurs : `admin@example.com`, `manager1@example.com`, `manager2@example.com` (mot de passe : `password`)
- Plusieurs boutiques avec coordonnées GPS réelles
- Produits avec URLs d'images externes
- Stocks distribués entre boutiques et produits
