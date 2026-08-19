#!/bin/bash
# Actualiza el ?v= de style.css, config.js y script.js en index.html
# usando la fecha/hora actual, para que WhatsApp y otros navegadores
# nunca se queden con una versión vieja en caché.
#
# Uso: correr este script antes de cada git push.
#   ./bump-version.sh

set -e
cd "$(dirname "$0")"

VERSION=$(date +%Y%m%d%H%M%S)

sed -i '' -E \
  -e "s/style\.css\?v=[0-9]+/style.css?v=${VERSION}/" \
  -e "s/config\.js\?v=[0-9]+/config.js?v=${VERSION}/" \
  -e "s/script\.js\?v=[0-9]+/script.js?v=${VERSION}/" \
  -e "s/favicon\.ico\?v=[0-9]+/favicon.ico?v=${VERSION}/" \
  -e "s/favicon-32\.png\?v=[0-9]+/favicon-32.png?v=${VERSION}/" \
  -e "s/apple-touch-icon\.png\?v=[0-9]+/apple-touch-icon.png?v=${VERSION}/" \
  index.html

echo "Versión actualizada a v=${VERSION} en index.html"
