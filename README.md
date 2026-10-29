# VerID4U

Registro digital de certificados: qualquer pessoa confere autenticidade e
validade de um certificado em 10 segundos, pelo código ou pelo QR Code.

**Desenvolvimento: Inspire4U** · produção: https://verid4u.com

## Esta entrega

Fase 1: landing de conversão, verificação pública em quatro idiomas
(pt-BR, pt-PT, es, en-GB) e área do emissor com banco MySQL — cadastro de
cursos, emissão de certificados com código real, revogação e certificado
imprimível. Os cinco registros semeados são de **demonstração** e aparecem
marcados como tal; tudo que for emitido pelo painel é registro real.

## Stack

PHP 8 sem dependências, sem etapa de build. Roda direto na hospedagem
compartilhada da Hostinger.

| Caminho | O que é |
| --- | --- |
| `index.php` | Controlador frontal: idioma, rotas, API e sitemap |
| `lib/code.php` | Código público: Base32 de Crockford + verificador Luhn mod 32 |
| `lib/store.php` | Acesso aos registros e cálculo de estado |
| `lib/db.php` | PDO, migrações idempotentes e semente |
| `lib/auth.php` | Sessão, senha, CSRF e trilha de auditoria |
| `lib/issuer.php` | Cursos, emissão, revogação e indicadores |
| `lib/panel.php` | Rotas da área do emissor |
| `lib/crypto.php` | AES-256-GCM para contato, HMAC para documento |
| `lib/config.local.php` | Credenciais — **fora do Git**, só no servidor |
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
| `/{idioma}/app/ativar` | Ativação do dono, só enquanto não há conta |
| `/{idioma}/app/entrar` | Entrada |
| `/{idioma}/app/` | Visão geral: vencimentos e verificações recebidas |
| `/{idioma}/app/cursos` | Cadastro de cursos |
| `/{idioma}/app/emitir` | Emissão |
| `/{idioma}/app/certificados` | Lista, revogação e impressão |

Idiomas do lado público: `pt-br`, `pt`, `es`, `en`. O painel sai em pt-BR;
`i18n/app.<locale>.php` já é o lugar das traduções quando houver cliente
estrangeiro.

## Primeira instalação

1. Copie `lib/config.example.php` para `lib/config.local.php` e preencha
   banco, `app_key` (`openssl rand -base64 32`) e `setup_token`.
2. Publique os arquivos. A primeira requisição cria o esquema e semeia os
   registros de demonstração.
3. Abra `/pt-br/app/ativar`, informe o `setup_token` do seu
   `lib/config.local.php` e defina a senha do dono.
4. A partir daí a tela de ativação se fecha sozinha: ela só responde
   enquanto não existe nenhum usuário.

O token de ativação fica num arquivo local de propósito. Enquanto não há
provedor de e-mail transacional configurado, qualquer fluxo por link mágico
dependeria de uma entrega que ainda não é confiável.

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

1. Provedor de e-mail transacional: entrega do certificado ao titular,
   aviso de vencimento e link mágico de entrada
2. Importação em lote (CSV/XLSX) e registro de certificados emitidos fora
3. API autenticada com chaves e escopos, mais webhooks
4. Níveis de verificação do emissor: comprovação de domínio e de CNPJ
5. Âncora criptográfica: árvore de Merkle diária com carimbo de tempo
6. Servidor MCP
