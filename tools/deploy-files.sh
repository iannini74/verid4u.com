#!/usr/bin/env bash
#
# Sobe arquivos avulsos para o site, por TUS.
#
# Por que avulso e nunca um .zip: o deploy por arquivo da Hostinger extrai o
# archive por cima do site, e o que existe no servidor e nao existe aqui some.
# Dois arquivos estao exatamente nessa situacao — lib/config.local.php e
# lib/mail.secret — e sao justamente os que guardam as credenciais. Um deploy
# por archive derruba o banco e o e-mail no mesmo segundo.
#
# Uso:
#   export VERID_URL=...        # 'url' de hosting_files_generate-upload-url
#   export VERID_AUTH=...       # auth_key
#   export VERID_AUTH_REST=...  # rest_auth_key
#   tools/deploy-files.sh arquivo [arquivo...]
#   tools/deploy-files.sh --do-commit HEAD      # os arquivos de um commit
#
# As chaves ficam so no ambiente desta execucao: nunca em arquivo, nunca no Git.

set -euo pipefail

: "${VERID_URL:?defina VERID_URL}"
: "${VERID_AUTH:?defina VERID_AUTH}"
: "${VERID_AUTH_REST:?defina VERID_AUTH_REST}"

raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$raiz"

if [ "${1:-}" = "--do-commit" ]; then
  mapfile -t arquivos < <(git show --stat --name-only --format= "${2:-HEAD}" | grep -v '^$')
else
  arquivos=("$@")
fi

[ "${#arquivos[@]}" -gt 0 ] || { echo "nada a subir"; exit 1; }

ok=0; falhou=0
for rel in "${arquivos[@]}"; do
  if [ ! -f "$rel" ]; then
    printf '  — %-44s (nao existe mais aqui; apague no servidor a mao)\n' "$rel"
    continue
  fi

  tamanho=$(wc -c < "$rel" | tr -d ' ')

  criado=$(curl -sS -o /dev/null -w '%{http_code}' -X POST "$VERID_URL/$rel?override=true" \
    -H "X-Auth: $VERID_AUTH" -H "X-Auth-Rest: $VERID_AUTH_REST" \
    -H 'Tus-Resumable: 1.0.0' -H "Upload-Length: $tamanho" -H 'Upload-Offset: 0')

  enviado=$(curl -sS -o /dev/null -w '%{http_code}' -X PATCH "$VERID_URL/$rel?override=true" \
    -H "X-Auth: $VERID_AUTH" -H "X-Auth-Rest: $VERID_AUTH_REST" \
    -H 'Tus-Resumable: 1.0.0' -H 'Content-Type: application/offset+octet-stream' \
    -H 'Upload-Offset: 0' --data-binary "@$rel")

  if [ "$criado" = "201" ] && [ "$enviado" = "204" ]; then
    printf '  ok %-44s %7s B\n' "$rel" "$tamanho"; ok=$((ok+1))
  else
    printf '  ERRO %-42s POST %s / PATCH %s\n' "$rel" "$criado" "$enviado"; falhou=$((falhou+1))
  fi
done

echo
echo "subiram: $ok   falharam: $falhou"
[ "$falhou" -eq 0 ]
