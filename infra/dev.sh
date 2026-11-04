#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

usage() {
    cat <<EOF
Uso: ./dev.sh <comando>

Comandos:
  up        Sobe backend, horizon, scheduler, mysql, redis e mailpit (build se precisar)
  down      Para os containers
  reset     Para os containers e apaga os volumes (mysql e redis do zero)
  shell     Abre um shell no container do backend
  logs      Acompanha os logs de todos os serviços
EOF
}

ensure_env() {
    if [ ! -f .env ]; then
        cp .env.example .env
        echo "Criei infra/.env a partir do .env.example"
    fi
}

case "${1:-}" in
    up)
        ensure_env
        docker compose up -d --build
        ;;
    down)
        docker compose down
        ;;
    reset)
        docker compose down -v
        ;;
    shell)
        docker compose exec backend bash
        ;;
    logs)
        docker compose logs -f
        ;;
    *)
        usage
        exit 1
        ;;
esac
