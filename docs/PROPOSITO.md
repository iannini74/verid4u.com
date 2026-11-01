# Finalidade do VerID4U

Documento canônico. Quando o código e este texto divergirem, é o código que
está errado. Revisto em 2026-11-01 com Tulio Iannini (Inspire4U).

---

## O que a plataforma é

Um **registro de certificados** que qualquer pessoa consulta de graça, e que
qualquer emissor alimenta — da autônoma que dá um curso por mês à
universidade que emite por lote.

Emissor é **empresa, escola, faculdade, universidade, plataforma de cursos
online ou profissional autônomo**. O tamanho muda o plano, nunca a garantia:
o certificado da autônoma é conferido com o mesmo rigor que o da
universidade.

## Os dois caminhos de entrada

| Caminho | Para quem | O que o VerID4U faz |
| --- | --- | --- |
| **Emitir aqui** | Quem não tem sistema próprio | Gera o certificado já registrado, com código, QR Code e selo |
| **Registrar o que já existe** | Quem já emite e não quer trocar de sistema | Registra o certificado alheio e devolve o código de verificação |

O segundo caminho é o que abre o mercado. Exigir que o cliente troque o
sistema dele é exigir uma decisão que ele não vai tomar por um fornecedor
novo; registrar o que ele já faz não exige decisão nenhuma.

## As três portas de integração

Toda porta faz o mesmo ciclo: **ler o certificado, validar, registrar e
devolver o código de registro.** O que muda é quem bate nela.

| Porta | Para quem | Estado |
| --- | --- | --- |
| **Importação de dados** | Quem tem planilha e não tem programador | Pronta (CSV, com ensaio antes de gravar) |
| **API** | Plataforma de cursos que quer registrar ao final da emissão | Pronta: chaves, idempotência, erros em problem+json |
| **MCP** | Agente de IA que precisa registrar ou conferir dentro de uma conversa | Pronto. Conferir não pede chave |

Integrar nunca pode ser condição. Quem não quiser integrar usa a importação
ou digita na mão, e recebe exatamente a mesma garantia.

## Os dois relógios

Um certificado tem **duas validades independentes**, e confundi-las é o erro
mais comum do mercado:

- **validade do certificado** — o documento é autêntico, foi emitido, não foi
  revogado
- **validade da certificação** — a habilitação que ele atesta ainda está
  vigente

Um certificado de NR-35 de 2019 é autêntico para sempre e vencido desde 2021.
As duas coisas são verdade ao mesmo tempo, e o veredito precisa dizer as
duas. Estado derivado de data nunca é gravado: é sempre calculado.

## Multi-tenancy e marca do cliente

Vários clientes na mesma plataforma, com isolamento por `tenant_id` em toda
consulta.

Acima de um volume mensal, o cliente ganha **a página de verificação com a
marca e a paleta dele** — é o que a pessoa vê ao ler o código ou escanear o
QR Code do certificado daquele cliente.

Três travas que não se negociam nessa personalização:

1. **A marca do cliente é pele, nunca substituição.** «Registrado no VerID4U»
   e a nossa marca continuam visíveis e não removíveis. Uma página de
   verificação que parecesse só do emissor deixaria o emissor atestando a si
   mesmo, que é exatamente o problema que viemos resolver.
2. **As quatro cores de estado são reservadas.** A paleta do cliente muda
   fundo e destaque; nunca o verde, o âmbar, o cinza e o vermelho do
   veredito.
3. **Contraste é verificado, não confiado.** Paleta que reprova 4,5:1 é
   recusada no momento em que o cliente salva, com a medida na tela.

## Números decididos

Fechados com o Tulio em 2026-11-01. Vivem em `lib/plan.php` enquanto só
existir um plano — tabela de planos com uma linha só é arquitetura fingida.

| | |
| --- | --- |
| Faixa gratuita | **50 emissões por competência** (mês civil) |
| Marca do cliente na verificação | a partir de **500 emissões por mês** |
| Janela do limiar | basta um dos últimos **3 meses** ter passado de 500 |
| Adquirente | **Stripe** — a Inspire4U já tem conta |

Duas consequências que vão no código antes de irem na tabela de preço:

- **A faixa limita emitir, nunca verificar.** Esgotada a faixa, o que já está
  no registro continua lá e continua conferível. Verificação é grátis e
  ilimitada para sempre, para qualquer um.
- **Quem já provou volume não perde a marca por um mês fraco.** O limiar olha
  três meses para trás porque tirar a marca do ar quebraria o selo já
  instalado no site do cliente, por um motivo que ele não controla.

## O que não se negocia

- **Verificar é sempre grátis e sem cadastro.** No dia em que custar, o
  registro deixa de valer: ninguém confere o que dá trabalho conferir.
- **Nenhum dado pessoal além do necessário.** Nome do titular sai abreviado;
  a página pública do emissor não lista titular nenhum.
- **O registro diz o que foi conferido, nunca o que é verdade.** Ver `Voz e
  tom` no design system.

## O que ainda falta para a finalidade estar cumprida

Lista honesta, em 2026-11-01:

- Marca e paleta do cliente na página de verificação
- Planos, preço cheio e promocional, cupons (percentual, valor, janela de
  validade) e cobrança pela Stripe — a faixa gratuita de 50 já está medida e
  aplicada
- Área administrativa da plataforma
- Logo do emissor no certificado — a cópia já promete «com a sua marca»
- Cabeçalho mobile e tradução de pt/es/en

## Quem faz

Desenvolvimento: **Inspire4U** — INSPIRE4U GOVERNANÇA CORPORATIVA LTDA,
CNPJ 33.922.313/0001-56.
