#!/usr/bin/env python3
"""Confere balanceamento de (), [], {} em PHP, ignorando string e comentario.

Nao substitui `php -l`, e isso importa: nesta maquina nao ha PHP, e ja houve
um caso em que este conferidor passou e o arquivo estava quebrado — uma aspa
simples dentro de uma string de aspas simples (`IN ('active')` dentro de um
SQL embutido) fecha a string e os parenteses continuam batendo. Entao ele
pega a classe de erro mais comum, nao todas. Leia as aspas do SQL embutido
com os proprios olhos.
"""
import sys

PARES = {')': '(', ']': '[', '}': '{'}


def confere(caminho):
    s = open(caminho, encoding='utf-8').read()
    i, n, linha = 0, len(s), 1
    pilha = []
    while i < n:
        c = s[i]
        if c == '\n':
            linha += 1; i += 1; continue
        if s.startswith('//', i) or c == '#':
            while i < n and s[i] != '\n':
                i += 1
            continue
        if s.startswith('/*', i):
            j = s.find('*/', i + 2)
            if j < 0:
                return ['%s: comentario /* sem fechar na linha %d' % (caminho, linha)]
            linha += s.count('\n', i, j); i = j + 2; continue
        if s.startswith('<<<', i):
            j = i + 3
            while j < n and s[j] in ' \t':
                j += 1
            aspa = ''
            if j < n and s[j] in '"\'':
                aspa = s[j]; j += 1
            k = j
            while k < n and (s[k].isalnum() or s[k] == '_'):
                k += 1
            marca = s[j:k]
            if aspa:
                k += 1
            fim = s.find('\n', k)
            if fim < 0 or not marca:
                return ['%s: heredoc malformado na linha %d' % (caminho, linha)]
            pos = fim
            while True:
                nl = s.find('\n', pos + 1)
                trecho = s[pos + 1: nl if nl >= 0 else n]
                if trecho.strip().rstrip(';,').strip() == marca:
                    linha += s.count('\n', i, nl if nl >= 0 else n)
                    i = nl if nl >= 0 else n
                    break
                if nl < 0:
                    return ['%s: heredoc %s sem fechar (linha %d)' % (caminho, marca, linha)]
                pos = nl
            continue
        if c in '"\'':
            j = i + 1
            while j < n:
                if s[j] == '\\':
                    j += 2; continue
                if s[j] == c:
                    break
                j += 1
            if j >= n:
                return ['%s: string sem fechar na linha %d' % (caminho, linha)]
            linha += s.count('\n', i, j); i = j + 1; continue
        if c in '([{':
            pilha.append((c, linha)); i += 1; continue
        if c in ')]}':
            if not pilha:
                return ['%s:%d: "%s" sem abertura' % (caminho, linha, c)]
            abre, ln = pilha.pop()
            if abre != PARES[c]:
                return ['%s:%d: "%s" fecha "%s" aberto na linha %d' % (caminho, linha, c, abre, ln)]
            i += 1; continue
        i += 1
    if pilha:
        abre, ln = pilha[-1]
        return ['%s: "%s" da linha %d nunca fecha' % (caminho, abre, ln)]
    return []


problemas = []
for caminho in sys.argv[1:]:
    problemas += confere(caminho)
print('\n'.join(problemas) if problemas else 'balanceamento ok em %d arquivo(s)' % len(sys.argv[1:]))
sys.exit(1 if problemas else 0)
