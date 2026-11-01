#!/usr/bin/env bash
#
# Sobe a versão dos arquivos estáticos, uma linha só, e confere que subiu.
#
# Existe porque eu fiz três vezes o mesmo erro: trocar a string à mão com
# o número antigo errado, a troca não casar, e falhar em silêncio. O CSS novo
# ia para o servidor e o navegador continuava servindo o velho por sete dias.
# Script que confere o resultado não tem como falhar calado.
set -euo pipefail
raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
arq="$raiz/lib/bootstrap.php"

atual=$(grep -oE "ASSET_VERSION = '[0-9]+'" "$arq" | grep -oE '[0-9]+')
[ -n "$atual" ] || { echo "não achei ASSET_VERSION em $arq"; exit 1; }
novo=$((atual + 1))

perl -pi -e "s/ASSET_VERSION = '\\d+'/ASSET_VERSION = '$novo'/" "$arq"

conferido=$(grep -oE "ASSET_VERSION = '[0-9]+'" "$arq" | grep -oE '[0-9]+')
[ "$conferido" = "$novo" ] || { echo "a troca não pegou: continua $conferido"; exit 1; }
echo "assets: $atual -> $novo"
