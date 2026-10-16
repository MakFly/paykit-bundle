.DEFAULT_GOAL := help

PROJECT_DIR := $(dir $(abspath $(lastword $(MAKEFILE_LIST))))
EXAMPLE_DIR := $(PROJECT_DIR)examples/fullstack
DOCS_DIR := $(PROJECT_DIR)docs
WEB_PORT ?= 5184
API_PORT ?= 8087
DOCS_PORT ?= 4387

C_SECTION := \033[1;33m
C_TARGET := \033[36m
C_OK := \033[32m
C_WARN := \033[33m
C_ERR := \033[31m
C_RESET := \033[0m
export WEB_PORT API_PORT C_OK C_WARN C_ERR C_RESET

.PHONY: help install dev stop status ports logs check docs-install docs docs-stop docs-check

# ─── Install / Setup ──────────────────────────────────────────────────────────
install: ## Installer les dependances et preparer la base de la demo
	@cd "$(EXAMPLE_DIR)" && bun install && bun run setup:api
	@printf '$(C_OK)[make] Exemple installe.$(C_RESET)\n'

# ─── Dev servers ──────────────────────────────────────────────────────────────
dev: ## Demarrer React et Symfony en arriere-plan
	@sh "$(EXAMPLE_DIR)/scripts/dev.sh" start

stop: ## Arreter les serveurs HTTP appartenant a cet exemple
	@sh "$(EXAMPLE_DIR)/scripts/dev.sh" stop

status: ## Afficher les serveurs du projet et leurs ports
	@sh "$(EXAMPLE_DIR)/scripts/dev.sh" status

ports: ## Verifier la disponibilite des ports choisis
	@sh "$(EXAMPLE_DIR)/scripts/dev.sh" ports

logs: ## Suivre les logs des deux serveurs (Ctrl+C quitte le suivi)
	@mkdir -p "$(EXAMPLE_DIR)/.dev"
	@touch "$(EXAMPLE_DIR)/.dev/api.log" "$(EXAMPLE_DIR)/.dev/web.log"
	@tail -n 30 -f "$(EXAMPLE_DIR)/.dev/api.log" "$(EXAMPLE_DIR)/.dev/web.log"

# ─── Build / Check ────────────────────────────────────────────────────────────
check: ## Verifier TypeScript, le conteneur, le schema et les tests API
	@cd "$(EXAMPLE_DIR)" && bun run typecheck && bun run check:api && bun run test:api
	@printf '$(C_OK)[make] Verifications terminees.$(C_RESET)\n'

# ─── Docs ─────────────────────────────────────────────────────────────────────
docs-install: ## Installer les dependances du site de documentation
	@cd "$(DOCS_DIR)" && bun install
	@printf '$(C_OK)[make] Dependances de la doc installees.$(C_RESET)\n'

docs: ## Lancer la doc Astro en local (Ctrl+C arrete le serveur)
	@if [ ! -d "$(DOCS_DIR)/node_modules" ]; then $(MAKE) --no-print-directory docs-install; fi
	@running=$$(cd "$(DOCS_DIR)" && bunx astro dev status 2>&1 | grep -o 'http://[^ ]*' | head -n 1); \
	if [ -n "$$running" ]; then \
		printf '$(C_WARN)[make] La doc tourne deja sur %s. Arrete-la avec : make docs-stop$(C_RESET)\n' "$$running"; \
	elif lsof -iTCP:$(DOCS_PORT) -sTCP:LISTEN >/dev/null 2>&1; then \
		printf '$(C_ERR)[make] Port $(DOCS_PORT) deja occupe par un autre service. Essaie : make docs DOCS_PORT=4388$(C_RESET)\n'; exit 1; \
	else \
		printf '$(C_OK)[make] Doc sur http://127.0.0.1:$(DOCS_PORT)$(C_RESET)\n'; \
		cd "$(DOCS_DIR)" && bun run dev --host 127.0.0.1 --port $(DOCS_PORT); \
	fi

docs-stop: ## Arreter la doc si Astro l'a lancee en arriere-plan
	@cd "$(DOCS_DIR)" && bunx astro dev stop

docs-check: ## Verifier les types et le contenu de la doc
	@cd "$(DOCS_DIR)" && bun run check
	@printf '$(C_OK)[make] Doc verifiee.$(C_RESET)\n'

# ─── Help ─────────────────────────────────────────────────────────────────────
help: ## Afficher les commandes par section
	@awk -v section='$(C_SECTION)' -v target='$(C_TARGET)' -v reset='$(C_RESET)' \
		'/^# ─── / { title = $$0; sub(/^# ─── /, "", title); sub(/ ─+$$/, "", title); printf "\n%s%s%s\n", section, title, reset } \
		/^[a-zA-Z_-]+:.*## / { split($$0, parts, "## "); name = $$1; sub(/:$$/, "", name); printf "  %s%-12s%s %s\n", target, name, reset, parts[2] }' "$(PROJECT_DIR)Makefile"
	@printf '\nPorts : React $(WEB_PORT), Symfony $(API_PORT). Override : make dev WEB_PORT=5185 API_PORT=8088\n'
	@printf 'Doc : $(DOCS_PORT). Override : make docs DOCS_PORT=4388\n'
