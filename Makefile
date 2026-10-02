# ==============================================================================
# Makefile — atajos de desarrollo para el Gold Scaffold
# ==============================================================================
# Uso: make <objetivo>.  `make help` lista todo.
# La mayoría de los objetivos corren dentro del contenedor `app`.
# ==============================================================================
DC      := docker compose
EXEC    := $(DC) exec app
# Flags extra para el `docker compose exec` de `test`. Vacío en la terminal;
# el hook pre-push lo fija a -T porque ahí stdin es la tubería de refs de git,
# no una TTY, y sin -T `docker compose exec` se niega a arrancar.
DC_EXEC_FLAGS ?=
ARTISAN := $(EXEC) php artisan

.DEFAULT_GOAL := help
.PHONY: help init up down restart rebuild logs ps shell tinker \
        migrate fresh seed doctor test e2e manual pint pint-test new \
        demo-preparar demo-grabar demo-curso demo-auditar

help: ## Muestra esta ayuda
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[1m%-12s\033[0m %s\n", $$1, $$2}'

## ── Infraestructura ──────────────────────────────────────────────────────────
init: ## Construye y levanta todo de cero (migra y siembra vía entrypoint)
	$(DC) up -d --build

up: ## Levanta los contenedores
	$(DC) up -d

down: ## Detiene y elimina los contenedores
	$(DC) down

restart: ## Reinicia el contenedor de la app
	$(DC) restart app

rebuild: ## Reconstruye las imágenes sin caché
	$(DC) build --no-cache

logs: ## Sigue los logs de la app (LOGS=worker para el worker)
	$(DC) logs -f $(or $(LOGS),app)

ps: ## Estado de los contenedores
	$(DC) ps

shell: ## Abre una shell dentro del contenedor app
	$(EXEC) sh

tinker: ## Abre Laravel Tinker
	$(ARTISAN) tinker

## ── Base de datos ────────────────────────────────────────────────────────────
migrate: ## Ejecuta migraciones pendientes
	$(ARTISAN) migrate

fresh: ## Recrea la BD desde cero y siembra
	$(ARTISAN) migrate:fresh --seed

seed: ## Ejecuta los seeders
	$(ARTISAN) db:seed

doctor: ## Diagnóstico del entorno (extensiones, BD, Redis, storage…)
	$(ARTISAN) app:doctor

## ── Calidad ──────────────────────────────────────────────────────────────────
test: ## Ejecuta la suite Pest (BD aislada sistema_test; NO toca la BD real)
	$(DC) exec $(DC_EXEC_FLAGS) -e DB_DATABASE=sistema_test app php -d memory_limit=1G ./vendor/bin/pest

.PHONY: typecheck
typecheck: ## Comprueba los tipos de los specs E2E (tsc --noEmit; no levanta nada)
	cd e2e && npm run typecheck

e2e: ## Ejecuta las pruebas E2E de Playwright
	cd e2e && npm test

manual: ## Regenera capturas + manual (Playwright)
	cd e2e && npm run manual

## ── Videos Tutorial (demo-engine) ──────────────────────────────────────────────
demo-preparar: ## Prepara sesiones autenticadas para grabar (demo-engine)
	npx demo preparar

demo-grabar: ## Graba un guión: make demo-grabar guion=recorrido
	@test -n "$(guion)" || { echo "Uso: make demo-grabar guion=recorrido"; exit 1; }
	npx demo grabar $(guion)

demo-curso: ## Graba y monta el curso completo (demo-engine)
	npx demo curso

demo-auditar: ## Audita un video para detectar fugas de privacidad
	@test -n "$(video)" || { echo "Uso: make demo-auditar video=recorrido"; exit 1; }
	npx demo auditar $(video)

pint: ## Formatea el código con Pint
	$(EXEC) ./vendor/bin/pint

pint-test: ## Verifica el formato sin modificar (para CI)
	$(EXEC) ./vendor/bin/pint --test

auditar: ## Auditoría rápida de seguridad/higiene/calidad de este proyecto
	./tools/auditar.sh .

## ── Generador ────────────────────────────────────────────────────────────────
new: ## Genera un proyecto: make new name="Mi Sistema" shared=muni [frontend=…] [port=…]
	@# `shared` se pide acá y no se delega al generador porque este target nunca
	@# funcionó: pasaba sólo el nombre, y `./scaffold new` muere si falta
	@# --shared. El error salía envuelto en un «make: *** [new] Error 1» que
	@# escondía el mensaje bueno.
	@test -x ./scaffold || { echo "Este proyecto no es el scaffold: make new sólo corre en el checkout del generador."; exit 1; }
	@test -n "$(name)" || { echo "Falta name. Uso: make new name=\"Mi Sistema\" shared=muni|kraftdo [frontend=livewire|inertia-react] [port=8001]"; exit 1; }
	@test -n "$(shared)" || { echo "Falta shared: hay que decir a qué entidad pertenece el proyecto. Uso: make new name=\"$(name)\" shared=muni  (o shared=kraftdo)"; exit 1; }
	./scaffold new "$(name)" --shared=$(shared) $(if $(frontend),--frontend=$(frontend),) $(if $(port),--port=$(port),)

demo-engine-update: ## Sube el pin de demo-engine al tag indicado (uso: make demo-engine-update V=v1.12.0)
	@test -n "$(V)" || { echo "Falta V. Uso: make demo-engine-update V=v1.12.0"; exit 1; }
	sed -i "s#tags/v[^/]*\.tar\.gz#tags/$(V).tar.gz#" package.json
	@echo "Pin de demo-engine -> $(V). Corre npm install para aplicar."
cobertura: ## Mide qué parte del código tocan las pruebas (necesita pcov)
	@# Base de datos PROPIA, distinta de la de «make test».
	@#
	@# Compartirla parecía inofensivo hasta que se corrieron las dos cosas a la
	@# vez: cada una borra y rehace las tablas de la otra a mitad de camino, y el
	@# resultado son decenas de fallos que no existen y que además CAMBIAN de una
	@# corrida a otra. Se pierde media hora buscando un defecto que no está.
	$(DC) exec -e DB_DATABASE=sistema_cobertura_test app php -d pcov.enabled=1 -d memory_limit=1G \
		./vendor/bin/pest --coverage --min=0

mutacion: ## Comprueba si las pruebas DETECTAN cambios en el código
	@# Pest cambia el código a propósito —invierte una condición, borra una
	@# línea— y comprueba si alguna prueba se pone roja. Una prueba que sigue en
	@# verde con el código alterado no está comprobando lo que dice comprobar.
	@# En esta sesión aparecieron tres casos así encontrados a mano; esto los
	@# encuentra solos.
	@#
	@# Se acota por dos razones. La primera es el precio: la mutación corre la
	@# batería entera por CADA línea que cambia, y sobre cuatro carpetas tardaba
	@# más de quince minutos. A ese precio nadie la corre, y una herramienta que
	@# nadie corre no protege nada.
	@#
	@# La segunda se descubrió midiendo: sobre «App\\Policies» genera CERO
	@# mutaciones, y no es un fallo. Una policy es «return $$user->can('x')»: no
	@# tiene condiciones, comparaciones ni aritmética, o sea nada que mutar. Ahí
	@# la cobertura y los tests de permiso son la protección; la mutación no
	@# aporta.
	@#
	@# Se apunta a donde sí hay lógica que pueda estar mal. Para otra zona:
	@#   make mutacion CLASE='App\\Http\\Middleware'
	@# Base de datos propia, por lo mismo que «cobertura»: la mutación lanza
	@# muchas corridas de pruebas en paralelo y arrasaría con la base de «test».
	$(DC) exec -e DB_DATABASE=sistema_mutacion_test app php -d pcov.enabled=1 -d memory_limit=2G \
		./vendor/bin/pest --mutate --covered-only \
		--class='$(or $(CLASE),App\\Support)'

skills-sync: ## Actualiza las skills del repo desde las del equipo (~/.claude/skills)
	@# Las skills viajan DENTRO del repo para que un proyecto generado funcione
	@# en cualquier equipo —otro municipio, otra persona— aunque ahí no exista
	@# ~/.claude/skills. El precio es que hay dos copias, y dos copias divergen.
	@#
	@# Esto sincroniza la del repo desde la del equipo, que es la que se edita.
	@# El sentido contrario NUNCA: si alguien mejora la copia del repo, esa
	@# mejora se pierde en la próxima sincronización, y por eso el test avisa
	@# cuando difieren en vez de dejarlo pasar.
	@for s in blade-livewire-design auditoria-seguridad seguridad-web web-perf mapa-funcional catalogo-funcional; do \
		if [ -d "$$HOME/.claude/skills/$$s" ]; then \
			rm -rf ".claude/skills/$$s" && cp -r "$$HOME/.claude/skills/$$s" .claude/skills/ && echo "  $$s actualizada"; \
		else \
			echo "  $$s no está en este equipo: se conserva la del repo"; \
		fi; \
	done

skills-check: ## Avisa si las skills del repo se separaron de las del equipo
	@# Va acá y no en la batería porque las pruebas corren DENTRO del
	@# contenedor, donde ~/.claude/skills no existe: allí la comparación se
	@# saltaba sola y pasaba en verde aunque las copias difirieran.
	@salida=0; \
	for s in blade-livewire-design auditoria-seguridad seguridad-web web-perf mapa-funcional catalogo-funcional; do \
		if [ -d "$$HOME/.claude/skills/$$s" ]; then \
			if ! diff -rq "$$HOME/.claude/skills/$$s" ".claude/skills/$$s" >/dev/null 2>&1; then \
				echo "  ✗ $$s difiere de la del equipo"; salida=1; \
			else echo "  ✓ $$s al día"; fi; \
		else echo "  – $$s no está en este equipo: la del repo es la única"; fi; \
	done; \
	if [ $$salida -ne 0 ]; then echo "\nSincroniza con: make skills-sync (la del equipo manda)"; fi; \
	exit $$salida
