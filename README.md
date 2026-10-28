# Prism ERP — démo

Un ERP minimal (clients, produits, commandes, lignes, factures) écrit avec **Prism seul** : aucun ORM ni DBAL Doctrine. Il sert d'exemple de référence du bundle Symfony, et de terrain pour le test du worker FrankenPHP.

Les paquets Prism viennent du monorepo (`../packages/*`, path repositories) : la démo suit le code en cours.

## Lancer

Depuis la racine du monorepo :

```bash
make up                 # MySQL et les autres bases
make demo-install       # composer install, bases prism_demo et prism_demo_docs, migrations, jeu de données, vecteurs
make demo-test          # les tests de la démo (base prism_demo_test)
```

## Ce qu'elle montre

| Page | Prism |
| --- | --- |
| `/customers` | `paginate()`, tri par le metamodel (`Customer_::as('c')`) |
| `/customers/{id}` | `{id}` → entité (`EntityValueResolver`), collection chargée au premier accès |
| `/orders` | 20 commandes, leur client, leur facture et leurs lignes en **5 requêtes**, quel que soit le nombre de commandes (batch fetching, pilier 1) |
| `/orders/{id}` | formulaire de facture : `#[UniqueEntity]`, transaction, montants en `decimal` sans flottant |
| `/reports/revenue` | requête groupée projetée dans un DTO (`selectInto()`), une seule requête |
| `/docs` | recherche dans la documentation de Prism : `hybridSearch()` sur PostgreSQL (pgvector), plein texte et vecteurs fusionnés, rangs affichés (pilier 4) |

Toutes les requêtes passent par le QueryBuilder typé : aucune chaîne de nom de champ dans `src/` (vérifié par `NoFieldNameStringTest`). Les entités ont été créées avec `make:entity`, le schéma avec `make:migration`.

Les tests utilisent `prism-orm/test-pack` : factories, `ResetDatabase` et `assertQueryCount()`, qui garde le N+1 hors des pages.

## Recherche documentaire (pilier 4)

La documentation de Prism (`website/`, `packages/*/docs`) est découpée en 324 sections, une par titre de niveau 2 ou 3. Elles vivent dans une seconde base, PostgreSQL avec pgvector, derrière un second EntityManager, `docs` : l'ERP reste sur MySQL, qui n'a pas de type vector.

- `DocSection` porte `#[EmbeddedVector(source: ['title', 'content'])]` : une section chargée ou modifiée est périmée, `db:embeddings:compute --em=docs` calcule son vecteur.
- Les vecteurs viennent de `nomic-embed-text:v1.5` (768 dimensions, Apache 2.0), calculés une fois par Ollama et versionnés dans `data/` (`docs.json`, `vectors.f32`). Le fournisseur `SnapshotEmbeddingProvider` les rend sans réseau ; il n'appelle Ollama que pour un texte absent du snapshot, si `EMBEDDING_BASE_URL` est donnée. Sans elle, la page `/docs` répond aux questions du jeu annoté seulement, et le dit pour les autres.
- La documentation change : `make embeddings-snapshot OLLAMA_URL=http://<hôte>:11434/v1` recalcule les textes changés. `SnapshotTest` échoue tant que le snapshot ne correspond plus aux pages.

### Mesure : rappel@10 sur le jeu annoté

`data/questions.json` : 60 questions écrites à la main, et pour chacune les sections qui y répondent (112 en tout). `bin/console app:docs:evaluate` classe les 324 sections pour chaque question, de trois façons, avec les vecteurs du snapshot :

| Recherche | Rappel@10 | MRR@10 |
| --- | --- | --- |
| `hybridSearch()` (RRF, k = 60, poids 1 et 1) | **0,922** | 0,776 |
| vectorielle seule | 0,903 | 0,807 |
| plein texte seul (`ts_rank_cd`, un OU sur les mots) | 0,719 | 0,531 |

Mesuré le 2026-10-28 sur PostgreSQL 16 et pgvector 0.8.7. L'hybride retrouve plus de sections pertinentes dans ses 10 premiers résultats ; le vectoriel seul place plus souvent la première pertinente en tête. Le corpus est petit : ces chiffres comparent les trois classements entre eux, ce n'est pas un banc d'essai public. Le rappel@10 de l'hybride au-dessus du vectoriel seul est vérifié par `DocsSearchTest`.

## En mode worker (FrankenPHP)

```bash
make worker-test            # 10 000 requêtes sur un seul worker, mémoire mesurée
docker compose --profile demo up -d frankenphp   # la démo sur http://localhost:8088 (PRISM_DEMO_PORT)
```

`tools/worker/load.php` mêle lectures et créations de factures, puis vérifie : aucune requête en échec, un seul processus worker, une identity map vide et aucune transaction ouverte au début de chaque requête (RG-07.5), et une mémoire qui ne croît pas de plus de 1 Mo après les 1 000 premières requêtes. Les mesures viennent des en-têtes `X-Prism-*` de `WorkerProbe`, actifs seulement avec `PRISM_WORKER_PROBE=1`.

Mesuré le 2026-10-22 : 10 000 requêtes en 10,4 s (962 req/s), 334 écritures, mémoire du worker stable à 1,07 Mo de la 1 000ᵉ à la 10 000ᵉ requête (−6,8 Ko). Contre-essai : sans la remise à zéro de l'EntityManager, le test échoue (2 993 requêtes sur 3 000 trouvent l'identity map pleine).

