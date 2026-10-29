# Prism ERP demo

A small ERP (customers, products, orders, order lines, invoices) built with [Prism ORM](https://github.com/prism-orm/prism) alone, without Doctrine. Two companies, Acme and Globex, share one database, each on its own subdomain. It installs Prism from Packagist, exactly as your application would.

## Run it

You need Docker and `make`.

```bash
git clone https://github.com/prism-orm/demo.git prism-demo && cd prism-demo
make up        # builds the image, installs the dependencies, starts the app and its databases
make install   # creates the databases, runs the migrations, loads the data and the documentation
```

Then open <http://acme.localhost:8000> and <http://globex.localhost:8000>. Browsers send `*.localhost` to your machine without any setup. If port 8000 is taken, start with `DEMO_PORT=8080 make up`.

The app is served by FrankenPHP in worker mode: one PHP process serves every request.

## What to look at

| Page | What Prism does |
| --- | --- |
| `/customers` | Pagination, sorting through the generated metamodel (`Customer_::as('c')`) |
| `/customers/{id}` | `{id}` resolved to the entity; its orders loaded on first access |
| `/orders` | 20 orders, their customer, invoice and lines in **5 queries**, whatever the number of orders: batch fetching |
| `/orders/{id}` | Issuing an invoice: `#[UniqueEntity]`, a transaction, amounts as `decimal` strings, never floats |
| `/reports/revenue` | A grouped query projected into a DTO (`selectInto()`), in one query |
| `/docs` | Searching Prism's documentation with `hybridSearch()`: full text and vectors fused, both ranks shown |

Every company sees only its own customers, orders and invoices, and an unknown subdomain is a 404: the tenant filter fails closed. No query in `src/` names a field with a string; `NoFieldNameStringTest` checks it.

## Documentation search

The documentation search lives in a second database, PostgreSQL with pgvector, behind a second entity manager; the ERP stays on MySQL. Vectors do not work with MySQL: it has no vector distance nor vector index, so use MariaDB or PostgreSQL for them.

The 324 sections of the documentation and their vectors (`nomic-embed-text:v1.5`, 768 dimensions) are versioned in `data/`, so the search works offline. On 60 questions annotated by hand, `hybridSearch()` finds a relevant section in its first ten results more often than the vector search alone (recall@10: 0.922 against 0.903). `bin/console app:docs:evaluate` measures it again.

To index a newer version of the documentation, with an Ollama server that has `nomic-embed-text:v1.5`:

```bash
make docs-snapshot PRISM=../prism OLLAMA_URL=http://<host>:11434/v1
```

## Tests

```bash
make test          # the test suite, with prism-orm/test-pack: factories, ResetDatabase, assertQueryCount()
make worker-test   # 10,000 requests on one worker: no failure, no leak, an empty identity map at each request
```

## Prism version

The demo follows the latest Prism release, currently `1.0.0-RC1`. While 1.0 is a release candidate, `composer.json` sets `"minimum-stability": "RC"` with `"prefer-stable": true`, so that Composer accepts the release candidates of Prism's own packages.

## License

MIT
