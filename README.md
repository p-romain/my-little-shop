# My Little Shop

Exercice technique de démonstration — API REST Symfony + SPA React pour la gestion d'une chaîne de boutiques de mode.

## Stack technique

| Couche | Technologie |
|---|---|
| Langage backend | PHP 8.5 |
| Framework | Symfony 7.2 |
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
- Truncate les tables `users` et `product CASCADE` avant chaque test pour l'isolation (pas de dépendance à `dama/doctrine-test-bundle`)
- Expose des helpers de création de fixtures de test (`createUser`, `createShop`, `createProduct`, `createStock`)

La méthode `assertShape(array $expectedKeys, array $actual)` vérifie qu'une réponse JSON contient **exactement** les clés attendues — ni plus, ni moins. Cela garantit qu'aucune propriété sensible (ex. hash de mot de passe) ne peut fuiter sans que les tests le détectent.

## Données de démo

Les fixtures (`fixtures/`) sont chargées avec Alice (Hautelook) :

- 3 utilisateurs : `admin@example.com`, `manager1@example.com`, `manager2@example.com` (mot de passe : `password`)
- Plusieurs boutiques avec coordonnées GPS réelles
- Produits avec URLs d'images externes
- Stocks distribués entre boutiques et produits
