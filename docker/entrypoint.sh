#!/usr/bin/env sh
set -e

cd /app

log() { echo "[entrypoint] $*"; }

# 1) Instalar dependências se necessário
if [ ! -f vendor/autoload.php ]; then
  log "vendor/autoload.php não encontrado. Executando composer install..."
  export COMPOSER_ALLOW_SUPERUSER=1
  composer install --no-interaction --prefer-dist --optimize-autoloader
else
  log "Dependências já instaladas. Pulando composer install."
fi

# 2) Se recebeu argumentos, executa-os (modo interativo)
if [ "$#" -gt 0 ]; then
  log "Executando comando custom: $*"
  exec "$@"
fi

# 3) Start padrão do Hyperf
log "Iniciando servidor Hyperf..."
exec php bin/hyperf.php start
