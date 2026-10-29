# VerID4U

Registro digital de certificados: qualquer pessoa confere autenticidade e
validade de um certificado em 10 segundos, pelo código ou pelo QR Code.

**Desenvolvimento: Inspire4U** · produção: https://verid4u.com

## Esta entrega

Fase 1, primeira fatia: landing de conversão + verificação pública, em
quatro idiomas (pt-BR, pt-PT, es, en-GB). Os registros ainda são de
**demonstração** e vivem em memória — a interface de dados (`Store`) já é a
que a camada MySQL vai implementar.

## Stack

PHP 8 sem dependências, sem etapa de build. Roda direto na hospedagem
compartilhada da Hostinger.

| Caminho | O que é |
| --- | --- |
| `index.php` | Controlador frontal: idioma, rotas, API e sitemap |
| `lib/code.php` | Código público: Base32 de Crockford + verificador Luhn mod 32 |
| `lib/store.php` | Acesso aos registros e cálculo de estado |
| `lib/i18n.php` | Idioma por URL ou `Accept-Language` |
| `lib/bootstrap.php` | Cabeçalhos de segurança, limite de taxa, helpers |
| `views/` | Layout, landing e página de verificação |
| `i18n/` | Textos por idioma (109 chaves cada) |
| `assets/` | CSS, JS e marca |

## Rotas

| Rota | O que faz |
| --- | --- |
| `/` | Redireciona para o idioma detectado |
| `/{idioma}/` | Landing com o verificador |
| `/{idioma}/v/{codigo}` | Resultado da verificação (`noindex`) |
| `/api/v1/verify/{codigo}` | Verificação em JSON |
| `/sitemap.xml` | Gerado em tempo de execução |

Idiomas: `pt-br`, `pt`, `es`, `en`.

## O código do certificado

12 caracteres em Base32 de Crockford, em três grupos de quatro:
`K7M2-93FQ-5XB1`. O alfabeto exclui `I`, `L`, `O` e `U`; a entrada é
tolerante (minúsculas, sem hífen, `I`/`L` lidos como `1`, `O` como `0`).
Os 11 primeiros caracteres são aleatórios e o último é um dígito
verificador Luhn mod 32, que rejeita erro de digitação antes de tocar o
banco e torna inútil a força bruta de baixo volume.

## Decisões que valem lembrar

- **Páginas de verificação não são indexadas.** Carregam dados pessoais e
  são quase idênticas entre si; indexá-las seria risco de LGPD e perda de
  autoridade de domínio.
- **Minimização.** A página pública nunca mostra documento, e-mail ou
  telefone; o nome sai parcial por padrão.
- **Estado nunca é gravado.** Vencido e a vencer são derivados da data a
  cada consulta.
- **Estado nunca é só cor.** Todo selo traz ícone, palavra e cor.

## Próximos passos

1. Banco MySQL e migração do `Store` para persistência real
2. Login do emissor e painel de emissão
3. Geração do PDF com a tarja de verificação
4. Importação em lote (CSV/XLSX) e registro de certificados de terceiros
5. API autenticada, webhooks e servidor MCP
