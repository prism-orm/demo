# Prism ERP — démo

Un ERP minimal (clients, produits, commandes, lignes, factures) écrit avec **Prism seul** : aucun ORM ni DBAL Doctrine. Il sert d'exemple de référence du bundle Symfony, et de terrain pour le test du worker FrankenPHP.

Les paquets Prism viennent du monorepo (`../packages/*`, path repositories) : la démo suit le code en cours.

## Lancer

Depuis la racine du monorepo :

```bash
make up                 # MySQL et les autres bases
make demo-install       # composer install, base prism_demo, migrations, jeu de données
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

Toutes les requêtes passent par le QueryBuilder typé : aucune chaîne de nom de champ dans `src/` (vérifié par `NoFieldNameStringTest`). Les entités ont été créées avec `make:entity`, le schéma avec `make:migration`.

Les tests utilisent `prism-orm/test-pack` : factories, `ResetDatabase` et `assertQueryCount()`, qui garde le N+1 hors des pages.
