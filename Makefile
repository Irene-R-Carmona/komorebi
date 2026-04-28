.PHONY: help up down restart logs bash db-reset db-seed db-migrate clean test test-unit test-integration test-coverage test-build test-clean ci coverage coverage-check cs-check cs-fix audit sonar-up sonar-down analyze e2e e2e-a11y lighthouse playwright-install dev dev-full workers-up workers-down xdebug-on phpstan phpstan-quick

# Colores para output
GREEN=\033[0;32m
YELLOW=\033[1;33m
NC=\033[0m # No Color

help: ## Mostrar esta ayuda
	@echo "$(GREEN)Komorebi Café - Comandos disponibles:$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(YELLOW)%-20s$(NC) %s\n", $$1, $$2}'

up: ## Levantar contenedores
	docker compose up -d

dev: ## Levantar en modo desarrollo (con utilidades)
	docker compose --profile dev up -d

dev-full: ## Levantar en modo desarrollo completo (incluye workers de cola)
	docker compose --profile dev --profile workers up -d

workers-up: ## Arrancar workers de cola (queue, email, notification)
	docker compose --profile workers up -d

workers-down: ## Detener workers de cola
	docker compose --profile workers stop

xdebug-on: ## Reconstruir contenedor app con Xdebug habilitado (tarda 1-2 min)
	docker compose build --build-arg XDEBUG_ENABLE=true app
	docker compose up -d app

down: ## Detener contenedores (mantener volúmenes)
	docker compose down

down-v: ## Detener contenedores y ELIMINAR volúmenes (reseteo completo)
	@echo "$(YELLOW)⚠️  ADVERTENCIA: Esto eliminará todos los datos de la BD$(NC)"
	@read -p "¿Continuar? (yes/no): " confirm && [ "$$confirm" = "yes" ] && \
		docker compose down -v && \
		echo "$(GREEN)✓ Volúmenes eliminados$(NC)" || \
		echo "$(YELLOW)Operación cancelada$(NC)"

restart: ## Reiniciar todos los servicios
	docker compose restart

restart-app: ## Reiniciar solo el contenedor app
	docker compose restart app

logs: ## Ver logs en tiempo real
	docker compose logs -f

logs-app: ## Ver logs solo del contenedor app
	docker compose logs -f app

logs-errors: ## Filtrar solo errores y críticos del contenedor app
	docker compose logs app | grep -E '"level":(4[0-9]{2}|"ERROR"|"CRITICAL"|ERROR|CRITICAL)'

logs-slow: ## Filtrar queries lentas (slow queries detectadas por LoggingPDO)
	docker compose logs app | grep -i "Slow query"

logs-http: ## Filtrar líneas canónicas HTTP (RequestLogMiddleware canonical events)
	docker compose logs app | grep '"canonical"'

logs-trace: ## Filtrar trazas de un request específico (uso: make logs-trace REQUEST_ID=abc123)
	docker compose logs app | grep "$(REQUEST_ID)"

bash: ## Acceder al shell del contenedor app
	docker compose exec app bash

db-bash: ## Acceder a MySQL CLI
	docker compose exec db mysql -u komorebi_user -p komorebi_db

db-reset: ## Resetear base de datos completamente (elimina y recrea)
	@echo "$(YELLOW)⚠️  ADVERTENCIA: Esto eliminará TODOS los datos de la BD$(NC)"
	@read -p "¿Continuar? (yes/no): " confirm && [ "$$confirm" = "yes" ] && \
		docker compose down -v && \
		docker compose up -d && \
		echo "$(GREEN)✓ Base de datos reseteada$(NC)" || \
		echo "$(YELLOW)Operación cancelada$(NC)"

db-seed-force: ## Forzar re-ejecución de seeders (limpia tablas primero)
	@echo "$(YELLOW)⚠️  Esto limpiará todas las tablas y recreará datos de prueba$(NC)"
	@read -p "¿Continuar? (yes/no): " confirm && [ "$$confirm" = "yes" ] && \
		docker compose exec app sh -c "FORCE_SEED=1 php scripts/apply-db.php --force" && \
		echo "$(GREEN)✓ Seeders ejecutados$(NC)" || \
		echo "$(YELLOW)Operación cancelada$(NC)"

db-migrate: ## Ejecutar migraciones solamente (sin seeders)
	docker compose exec app php scripts/apply-db.php

db-seed: ## Ejecutar seeders solamente (sin migraciones)
	docker compose exec app php -r "require 'vendor/autoload.php'; (new App\Core\DatabaseSeeder())->run();"

db-verify: ## Verificar estado de la base de datos
	docker compose exec app php scripts/verify-db-schema.php

db-backup: ## Crear backup de la base de datos
	@mkdir -p backups
	@docker compose exec db mysqldump -u komorebi_user -p komorebi_db > backups/backup_$$(date +%Y%m%d_%H%M%S).sql
	@echo "$(GREEN)✓ Backup creado en backups/$(NC)"

clean: ## Limpiar cache y logs
	docker compose exec app rm -rf storage/cache/* storage/logs/*
	@echo "$(GREEN)✓ Cache y logs limpiados$(NC)"

test: ## Ciclo completo de tests (build → migraciones → phpunit → down)
	@echo "$(GREEN)Construyendo imagen de test...$(NC)"
	docker compose -f docker-compose.test.yml build php-test
	@echo "$(GREEN)Ejecutando tests...$(NC)"
	@docker compose -f docker-compose.test.yml run --rm php-test \
		sh -c "php scripts/apply-db.php --force && php vendor/bin/phpunit --testdox"; \
		EXIT=$$?; \
		docker compose -f docker-compose.test.yml down -v --remove-orphans; \
		exit $$EXIT

test-unit: ## Solo tests unitarios en paralelo (requiere make dev activo)
	docker compose exec -e XDEBUG_MODE=off app vendor/bin/paratest --runner=WrapperRunner --processes=4 --testsuite "Unit Tests" --testdox

test-integration: ## Tests de integración con BD efímera
	@echo "$(GREEN)Construyendo imagen de test...$(NC)"
	docker compose -f docker-compose.test.yml build php-test
	@docker compose -f docker-compose.test.yml run --rm php-test \
		sh -c "php scripts/apply-db.php --force && php vendor/bin/phpunit --testsuite 'Integration Tests' --testdox"; \
		EXIT=$$?; docker compose -f docker-compose.test.yml down -v; exit $$EXIT

test-coverage: ## Tests con cobertura de código (genera HTML + Clover) y verifica umbral 85%
	@echo "$(GREEN)Construyendo imagen de test...$(NC)"
	docker compose -f docker-compose.test.yml build php-test
	@docker compose -f docker-compose.test.yml run --rm php-test \
		sh -c "php scripts/apply-db.php --force && php vendor/bin/phpunit --coverage-text --coverage-clover tests/reports/coverage.xml --coverage-html tests/reports/coverage/ && php scripts/check-coverage.php tests/reports/coverage.xml 85"; \
		EXIT=$$?; docker compose -f docker-compose.test.yml down -v; exit $$EXIT

test-build: ## Construir imagen de test sin ejecutar
	docker compose -f docker-compose.test.yml build php-test

test-clean: ## Eliminar contenedores y volúmenes de test
	docker compose -f docker-compose.test.yml down -v --remove-orphans

phpstan: ## Análisis estático con PHPStan (guarda output; nunca pierde por timeout)
	docker compose exec app sh -c "php vendor/bin/phpstan analyse --memory-limit=1G --no-progress > /tmp/phpstan.txt 2>&1; EXIT=\$$?; cat /tmp/phpstan.txt; exit \$$EXIT"

phpstan-quick: ## PHPStan solo sobre app/ (rápido, sin scripts/ ni tests/)
	docker compose exec app sh -c "php vendor/bin/phpstan analyse app/ --memory-limit=512M --no-progress --allow-unmatched-ignores 2>&1"

coverage-check: ## Verificar umbral 85% sobre XML ya generado (tests/reports/coverage.xml)
	docker compose exec app php scripts/check-coverage.php tests/reports/coverage.xml 85

ci: ## Suite completa de calidad (phpstan + test + cs)
	$(MAKE) phpstan
	$(MAKE) test
	$(MAKE) cs-check


cs-check: ## Verificar estilo de código con PHP CS Fixer
	docker compose exec app composer run check:cs-fixer

cs-fix: ## Aplicar estilo de código con PHP CS Fixer
	docker compose exec app composer run fix:cs

audit: ## Auditar seguridad de dependencias Composer
	docker compose exec app composer audit

sonar-up: ## Arrancar SonarQube (perfil sonar) — primera vez tarda ~60s
	@echo "$(GREEN)Arrancando SonarQube en http://localhost:9000$(NC)"
	@echo "$(YELLOW)Primer arranque: ejecutar 'sysctl -w vm.max_map_count=524288' si falla Elasticsearch$(NC)"
	docker compose --profile sonar up -d sonarqube sonarqube_db
	@echo "$(GREEN)Admin credentials: admin / admin (cambiar en primer login)$(NC)"

sonar-down: ## Detener SonarQube
	docker compose --profile sonar down sonarqube sonarqube_db

analyze: ## Analizar código con SonarQube (requiere make sonar-up + SONAR_TOKEN)
	@if [ -z "$(SONAR_TOKEN)" ]; then \
		echo "$(RED)Error: SONAR_TOKEN no definido. Generar en http://localhost:9000/account/security$(NC)"; \
		exit 1; \
	fi
	docker run --rm \
		--network komorebi_komorebi-net \
		-v "$(PWD):/usr/src" \
		-e SONAR_HOST_URL="http://sonarqube:9000" \
		-e SONAR_TOKEN="$(SONAR_TOKEN)" \
		sonarsource/sonar-scanner-cli:11 \
		/opt/sonar-scanner/bin/sonar-scanner \
		-Dsonar.projectBaseDir=/usr/src
	@echo "$(GREEN)Resultados en http://localhost:9000/dashboard?id=komorebi-cafe$(NC)"

composer-install: ## Instalar dependencias de Composer
	docker compose exec app composer install --no-interaction --prefer-dist

composer-update: ## Actualizar dependencias
	docker compose exec app composer update

ps: ## Ver estado de contenedores
	docker compose ps

stats: ## Ver estadísticas de base de datos
	@echo "$(GREEN)Estadísticas de la Base de Datos:$(NC)"
	@docker compose exec db mysql -u komorebi_user -p komorebi_db -e "\
		SELECT 'Usuarios' as Tabla, COUNT(*) as Total FROM users \
		UNION SELECT 'Cafés', COUNT(*) FROM cafes \
		UNION SELECT 'Roles', COUNT(*) FROM roles \
		UNION SELECT 'Reservas', COUNT(*) FROM reservations \
		UNION SELECT 'Reviews', COUNT(*) FROM reviews \
		UNION SELECT 'Productos', COUNT(*) FROM products;"

build: ## Reconstruir imágenes
	docker compose build --no-cache

rebuild: down build up ## Reconstruir y levantar (sin eliminar volúmenes)

rebuild-clean: down-v build up ## Reconstruir completamente desde cero

e2e: ## Ejecutar tests end-to-end con Playwright
	docker compose exec app npx playwright test

e2e-a11y: ## Ejecutar solo tests de accesibilidad (WCAG 2.1 AA)
	docker compose exec app npx playwright test tests/e2e/accessibility/

lighthouse: ## Auditoría Lighthouse (performance + a11y + best-practices)
	mkdir -p lighthouse-reports
	npx lighthouse http://localhost:8080/ --chrome-flags="--no-sandbox --disable-dev-shm-usage" --output=html --output-path=./lighthouse-reports/home.html --quiet
	npx lighthouse http://localhost:8080/manager/dashboard --chrome-flags="--no-sandbox --disable-dev-shm-usage" --output=html --output-path=./lighthouse-reports/manager-dashboard.html --quiet
	npx lighthouse http://localhost:8080/supervisor/dashboard --chrome-flags="--no-sandbox --disable-dev-shm-usage" --output=html --output-path=./lighthouse-reports/supervisor-dashboard.html --quiet
	npx lighthouse http://localhost:8080/manager/productos --chrome-flags="--no-sandbox --disable-dev-shm-usage" --output=html --output-path=./lighthouse-reports/manager-productos.html --quiet
	@echo "Informes Lighthouse disponibles en ./lighthouse-reports/"

playwright-install: ## Instalar Playwright y sus dependencias de navegadores
	docker compose exec app npx playwright install --with-deps
