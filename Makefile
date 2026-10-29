# Prism ERP demo : make up, make install, puis http://acme.localhost:8000
DC := docker compose
RUN := $(DC) exec -T app

.DEFAULT_GOAL := help
.PHONY: help up install test worker-test docs-snapshot down

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

up: ## Démarre la démo et ses bases (http://acme.localhost:8000, http://globex.localhost:8000)
	$(DC) build app
	@# le worker a besoin de vendor/ pour démarrer : Composer d'abord, avec l'utilisateur courant
	test -d vendor || $(DC) run --rm --no-deps --user $$(id -u):$$(id -g) -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer app composer install --no-interaction --no-progress
	$(DC) up -d --wait

install: ## Dépendances, bases, migrations, jeu de données, documentation et ses vecteurs
	$(RUN) sh -c 'bin/console db:database:create --if-not-exists && bin/console db:migrations:migrate -n && bin/console app:seed'
	$(RUN) sh -c 'bin/console db:database:create --if-not-exists --em=docs && bin/console db:migrations:migrate -n --em=docs && bin/console app:docs:load && bin/console db:embeddings:compute --em=docs'

test: ## Les tests (bases prism_demo_test et prism_demo_docs_test)
	$(RUN) sh -c 'APP_ENV=test bin/console db:database:create --if-not-exists && APP_ENV=test bin/console db:database:create --if-not-exists --em=docs && vendor/bin/phpunit'

worker-test: ## 10 000 requêtes sur un seul worker, mémoire mesurée : make worker-test [N=10000]
	PRISM_WORKER_PROBE=1 $(DC) up -d --wait app
	$(RUN) php tools/worker/load.php http://localhost $(or $(N),10000)
	$(DC) up -d --wait app

docs-snapshot: ## Les vecteurs de la documentation, par un Ollama : make docs-snapshot PRISM=../prism OLLAMA_URL=http://<hôte>:11434/v1
	@test -n "$(PRISM)" -a -n "$(OLLAMA_URL)" || { echo 'PRISM et OLLAMA_URL sont requis'; exit 2; }
	$(DC) run --rm -v $(abspath $(PRISM)):/prism -e EMBEDDING_BASE_URL=$(OLLAMA_URL) app bin/console app:docs:snapshot --prism=/prism

down: ## Arrête et supprime les conteneurs
	$(DC) down --remove-orphans
