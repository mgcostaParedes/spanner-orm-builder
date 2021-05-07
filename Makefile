.DEFAULT_GOAL := help
.PHONY: up down install test.unit test.functional test.api test test.coverage test.coverage \
php.metrics.open php.cs php.cbf php.md php.loc build logs \
composer.update composer.install help

DOCKER_C := docker-compose -f docker-compose.yml
APP_NAME := app
DOCK_X_APP := $(DOCKER_C) exec $(APP_NAME)

ADAM_CONAINTER:= docker run --rm -v "$(PWD)":/app -w /app adamculp/php-code-quality:latest
PHP_METRICS := /usr/local/lib/php-code-quality/vendor/bin/phpmetrics

VENDOR_PHINX := vendor/bin/phinx
VENDOR_CODECEPT := vendor/bin/codecept
VENDOR_PHPCS := /usr/local/lib/php-code-quality/vendor/bin/phpcs
VENDOR_PHPCBF := /usr/local/lib/php-code-quality/vendor/bin/phpcbf
VENDOR_PHPMD := /usr/local/lib/php-code-quality/vendor/bin/phpmd

OUTPUT_COVERAGE := src/tests/_output/coverage/
OUTPUT_METRICS := src/tests/_output/php_code_quality/metrics_results/

up: ## Start docker container
	$(DOCKER_C) pull
	$(DOCKER_C) up -d

rebuild: ## Start docker container
	$(DOCKER_C) pull
	$(DOCKER_C) up --build -d

down: ## Stop docker container
	$(DOCKER_C) down

run: ## Stop docker container
	$(DOCK_X_APP) /bin/sh -c 'cd public; php index.php'

install: dependencies up composer.install ## Install the container & all the dependencies

test: ## Run unit tests suite
	$(DOCK_X_APP) php $(VENDOR_CODECEPT) run

test.integration: ## Run integration tests suite
	$(DOCK_X_APP) php $(VENDOR_CODECEPT) run integration

test.coverage: ## Check project test coverage
	$(DOCK_X_APP) php $(VENDOR_CODECEPT) run --coverage --coverage-html --coverage-text
	open $(OUTPUT_COVERAGE)index.html >&- 2>&- || \
	xdg-open $(OUTPUT_COVERAGE)index.html >&- 2>&- || \
	gnome-open $(OUTPUT_COVERAGE)index.html >&- 2>&-

test.unit: ## Run unit tests suite
	$(DOCK_X_APP) php $(VENDOR_CODECEPT) run unit

php.metrics: ## Run php metrics & open metrics web
	$(ADAM_CONAINTER) php \
	$(PHP_METRICS) --excluded-dirs 'vendor','tests' \
	--report-html=./src/tests/_output/php_code_quality/metrics_results .
	make php.metrics.open

php.metrics.open:
	open $(OUTPUT_METRICS)index.html >&- 2>&- || \
	xdg-open $(OUTPUT_METRICS)index.html >&- 2>&- || \
	gnome-open $(OUTPUT_METRICS)index.html >&- 2>&-

php.cs: ## Run php code sniffer
	$(ADAM_CONAINTER) php $(VENDOR_PHPCS) \
	-sv --extensions=php --standard=PSR12 --ignore=vendor,tests,c3.php .

php.cbf: ## Run php Code Beautifier and Fixer
	$(ADAM_CONAINTER) php $(VENDOR_PHPCBF) \
	-sv --extensions=php --standard=PSR12 --ignore=vendor,tests,c3.php .

php.md: ## Run php mess detector
	$(ADAM_CONAINTER) php $(VENDOR_PHPMD) . \
	text cleancode,codesize,design,unusedcode --exclude 'vendor','tests','c3.php' --ignore-violations-on-exit

php.loc: ## Run php loc that analyzing the size and structure of the php project
	$(ADAM_CONAINTER) php \
	./vendor/phploc/phploc/phploc -v --names "*.php"  --exclude 'vendor','tests' .

build: ## Build docker image
	$(DOCKER_C) build

logs: ## Watch docker log files
	$(DOCKER_C) logs -f

composer.install:
	$(DOCK_X_APP) composer install

composer.update: ## Run composer update inside container
	$(DOCK_X_APP) composer update

composer.normalize: ## Run composer.json formater
	$(DOCK_X_APP) composer normalize

dependencies:
	docker pull php:7.3-fpm-alpine

help:
	@grep -E '^[a-zA-Z._-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'