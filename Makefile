DOCKER_COMPOSE := docker compose -f compose.local.yaml --env-file .env.local

.PHONY: up
up:
	${DOCKER_COMPOSE} up -d

.PHONY: down
down:
	${DOCKER_COMPOSE} down

.PHONY: build
build: build-assets
	${DOCKER_COMPOSE} build

.PHONY: migrate
migrate: down
	${DOCKER_COMPOSE} up -d albatross-db
	${DOCKER_COMPOSE} run --rm --entrypoint="php bin/albctl migrate" albatross-jobworker

.PHONY: promote
promote: up
	${DOCKER_COMPOSE} run --rm --entrypoint="php bin/albctl promote" albatross-jobworker

.PHONY: deluser
deluser: up
	${DOCKER_COMPOSE} run --rm --entrypoint="php bin/albctl deluser" albatross-jobworker

.PHONY: sh
sh:
	${DOCKER_COMPOSE} exec albatross-app sh

.PHONY: psql
psql:
	${DOCKER_COMPOSE} exec albatross-db psql -U postgres

.PHONY: logs
logs:
	${DOCKER_COMPOSE} logs

.PHONY: build-assets
build-assets: services/app/public/assets
	docker build -t albatross-build-assets -f services/app/Dockerfile.frontend ./services/app
	docker run --rm -v "$$(pwd)"/services/app/esbuild.mjs:/app/esbuild.mjs -v "$$(pwd)"/services/app/assets:/app/assets -v "$$(pwd)"/services/app/public/assets:/app/public/assets --env-file "$$(pwd)"/.env.local albatross-build-assets npm run build
	rm -f services/app/public/assets/favicon.svg
	cp services/app/assets/favicon.svg services/app/public/assets

services/app/public/assets:
	@mkdir -p services/app/public/assets
