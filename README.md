# VerID4U

Registro digital de certificados: qualquer pessoa confere autenticidade e
validade de um certificado em 10 segundos, pelo código ou pelo QR Code.

**Desenvolvimento: Inspire4U** · produção: https://verid4u.com

## Esta entrega

Fase 1: landing de conversão, verificação pública em quatro idiomas
(pt-BR, pt-PT, es, en-GB) e área do emissor com banco MySQL — cadastro de
cursos, emissão de certificados com código real, **registro de certificados
emitidos fora da plataforma**, revogação e certificado imprimível. Os seis
registros semeados são de **demonstração** e aparecem marcados como tal;
tudo que for emitido ou registrado pelo painel é registro real.

### Emitir ou registrar

A distinção é de honestidade, não de tecnologia, e a página de verificação
a declara com todas as letras:

| Origem | O que o VerID4U afirma |
| --- | --- |
| **Emitido** | O documento foi gerado aqui, com código, selo e QR Code. |
| **Registrado** | A instituição produziu o documento no sistema dela. O VerID4U atesta a data do registro, a integridade do conteúdo e — quando há impressão digital do arquivo — que o PDF em mãos é bit a bit o registrado. Não produziu o certificado. |

### A impressão digital do arquivo

O arquivo do certificado **nunca é enviado**. O navegador calcula o SHA-256
localmente e manda só o resumo. Isso dá três coisas de uma vez:

1. **Registrar sem subir o documento** — nenhum PDF de terceiro no servidor.
2. **Conferir o arquivo em mãos** — a página de verificação diz se aquele PDF
   é exatamente o registrado, ou se foi alterado.
3. **Verificação reversa** — quem tem o arquivo e não tem o código acha o
   registro pelo próprio arquivo, direto da home.

Para ver funcionando sem criar conta: baixe
[`/assets/demo/certificado-exemplo.pdf`](https://verid4u.com/assets/demo/certificado-exemplo.pdf)
e solte-o em «Não tem o código?» na home. Ele leva ao registro
`T3R9-M2XK-8PVR`.

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
| `/api/v1/lookup` | `POST sha256=…` — acha o registro pela impressão digital do arquivo |
| `/api/v1/health` | Estado do serviço e versão do esquema. Não devolve dado nenhum |
| `/api/v1/cron/mail?key=…` | Despacha a fila de e-mail. Protegida por segredo do config |
| `/api/v1/cron/expiry?key=…` | Enfileira os avisos de vencimento de 90, 30 e 7 dias |
| `/sitemap.xml` | Gerado em tempo de execução |
| `/{idioma}/app/ativar` | Ativação do dono, só enquanto não há conta |
| `/{idioma}/app/entrar` | Entrada |
| `/{idioma}/app/` | Visão geral: vencimentos e verificações recebidas |
| `/{idioma}/app/cursos` | Cadastro de cursos |
| `/{idioma}/app/emitir` | Emissão |
| `/{idioma}/app/registrar` | Registro de certificado emitido fora |
| `/{idioma}/app/importar` | Importação em lote, em três passos |
| `/{idioma}/app/email` | Estado do envio, teste e lista de supressão |
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

## Importação em lote

Três passos, e o do meio existe para que ninguém grave nada sem ver antes:

1. **Enviar** — escolha entre importar certificados já emitidos (registro) ou
   emitir novos a partir de um curso cadastrado, e mande o CSV.
2. **Mapear e simular** — o sistema adivinha de que coluna vem cada campo
   pelos nomes do cabeçalho; você corrige e vê, linha a linha, o que será
   criado, pulado ou recusado. Nada foi escrito ainda.
3. **Gravar** — e baixar, em CSV, as linhas que não entraram, com o motivo
   de cada uma.

O que faz isto funcionar no Brasil não é ler CSV, é ler **o CSV que o Excel
brasileiro salva**: ponto e vírgula como separador, Windows-1252, BOM, e
datas em `dd/mm/aaaa`. Tudo isso é detectado sozinho. Modalidade também é
interpretada («à distância», «online», «remoto» → EAD).

A simulação e a gravação chamam **a mesma função de avaliação** — é o que
garante que o que você viu na prévia é o que acontece. Duas implementações
paralelas divergiriam no primeiro ajuste.

Limites por lote: 5 MB e 2.000 linhas.

## E-mail

O motor está pronto e desligado: `mail.driver` começa em `log`, que registra
e não envia. Ele vira `smtp` assim que houver provedor.

**Por que SMTP e não o SDK de um provedor.** Resend, Amazon SES, Postmark,
Brevo e ZeptoMail falam SMTP. Com um cliente SMTP próprio — 136 linhas, sem
dependência — trocar de provedor é trocar host, usuário e senha no
`config.local.php`. O documento de estratégia pede um segundo provedor
sempre pronto, porque e-mail é ponto único de falha do negócio; isso só é
barato se a troca for de configuração.

**Nada sai de uma requisição web.** Tudo entra em `mail_messages` e o cron
despacha. Emitir mil certificados não pode esperar mil conexões SMTP.

**A supressão é consultada duas vezes**, ao enfileirar e ao enviar, porque
entre uma coisa e outra pode ter chegado uma recusa definitiva. Recusa
permanente (5xx com «user unknown» e parecidos) suprime o endereço na hora;
falha temporária volta para a fila com espera crescente de 5 min, 25 min e
2 h, até quatro tentativas.

Mensagens prontas, nos quatro idiomas: certificado emitido, certificado
registrado, aviso de vencimento (90, 30 e 7 dias) e revogação. O modelo
guarda o **nome** do template e os dados, não o texto pronto — corrigir um
texto conserta o que ainda não saiu.

### Ligar o envio

1. No Resend, adicione o domínio `verid4u.com` na região **São Paulo
   (sa-east-1)** e publique os registros que ele mostrar.
2. Crie uma chave de API e preencha em `lib/config.local.php`:
   `driver` = `smtp`, `host` = `smtp.resend.com`, `port` = `587`,
   `secure` = `tls`, `user` = `resend`, `pass` = a chave.
3. Confirme em `/pt-br/app/email` — a tela tem envio de teste.

DMARC já está publicado em `p=none` (modo observação). Depois de três
semanas sem surpresa nos relatórios, apertar para:
`v=DMARC1; p=reject; adkim=s; aspf=s; rua=mailto:rua@dmarc.brevo.com`

### Cron a configurar na Hostinger

```
*/5 * * * *  curl -s "https://verid4u.com/api/v1/cron/mail?key=SEGREDO" > /dev/null
15  6 * * *  curl -s "https://verid4u.com/api/v1/cron/expiry?key=SEGREDO" > /dev/null
```

O segredo está em `mail.cron_key`, no `lib/config.local.php`.

## Próximos passos

1. Escolher o provedor de e-mail e publicar SPF, DKIM e DMARC: entrega do certificado ao titular,
   aviso de vencimento e link mágico de entrada
3. API autenticada com chaves e escopos, mais webhooks
4. Níveis de verificação do emissor: comprovação de domínio e de CNPJ
5. Âncora criptográfica: árvore de Merkle diária com carimbo de tempo
6. Servidor MCP
