# VerID4U

O registro digital de certificados. Qualquer pessoa confere a autenticidade e
a validade de um certificado em dez segundos, pelo código ou pelo QR Code.

**Desenvolvimento: Inspire4U** · produção: https://verid4u.com

---

## A ideia que governa o visual

Dois registros, e eles não se misturam.

| Registro | Onde vive | Como se comporta |
| --- | --- | --- |
| **Marca** | Landing, comunicação, materiais de venda | Expressivo. Cor cheia, tipografia display enorme, movimento ao rolar, pílulas. Chama atenção e convence. |
| **Documento** | Página de verificação, certificado, painel | Sóbrio. Fundo claro, tipografia quieta, zero gesto decorativo. Responde e sai do caminho. |

O que vende e o que prova não precisam ter a mesma voz. Quem abre a página de
verificação escaneou um QR Code num papel e quer uma resposta — não uma
campanha. Quem chega pela busca precisa entender o produto em cinco segundos.

A regra prática: **se o elemento aparece dentro de um documento de terceiro
ou ao lado de um veredito, ele pertence ao registro Documento.**

---

## A marca

Um **bloco com uma cunha faltando, e a cunha ao lado**. O olho resolve em
menos de um segundo: aquela peça veio dali, e só ela serve. É a ideia de
chave e fechadura sem desenhar nem chave nem fechadura.

Duas posições, e a diferença entre elas é toda a semântica do produto:

| Posição | Significa | Onde aparece |
| --- | --- | --- |
| **Cunha solta** | O registro existe | Logotipo, tarja do certificado, emissor nível 0, carimbo de revogado |
| **Cunha encaixada** | Foi conferido | Selo do emissor verificado, selo circular, carimbo de conferido |

O logotipo é a forma **solta**. É ela que mostra as duas peças e faz a
pergunta; o encaixe é resposta, e resposta é estado, não identidade.

**A fresta nunca fecha.** No mínimo 4% da altura entre a cunha e o entalhe.
Se fechasse, a marca viraria um bloco só e o olho perderia a prova de que são
duas peças — que é a única coisa que o desenho tem a dizer.

### Como ela é feita

Três paths, em caixa de 100×100, e **nenhuma máscara**:

```
bloco       M8 16H62V24L40 50L62 76V84H8Z
cunha solta M94 24L72 50L94 76Z
cunha encaixada M66 27L44 50L66 73Z
```

Máscara depende de referência a `id`, e referência a `id` é o que quebra
quando o SVG vira favicon, anexo de e-mail, imagem servida para o site de
outra pessoa ou PDF impresso. Sem ela, a marca atravessa tudo isso intacta.

O `viewBox` é colado no desenho (`8 16 86 68` solta, `8 16 58 68` encaixada),
então **a medida que manda é a altura** e a largura acompanha. Encaixada a
marca é mais estreita, porque a cunha entrou — é a própria ideia, não um
defeito de caixa. Nunca trave a largura.

Fonte única: `lib/brand.php`. Nenhum SVG de marca é escrito à mão em view.

| Arquivo | Quando usar |
| --- | --- |
| `verid4u-mark.svg` | Padrão, cunha solta, a partir de 20 px de altura |
| `verid4u-mark-fit.svg` | Cunha encaixada, para peça que afirma conferência |
| `verid4u-mark-solid.svg` | Favicon e usos abaixo de 20 px: quadrado azul com a marca vazada |
| `verid4u-mark-inverse.svg` | Sobre fundo de marca ou foto escura |
| `verid4u-seal.svg` | Selo circular dos certificados emitidos por nós |

**O logotipo é tipografia, não desenho.** «VerID4U» é composto em Inter Tight
700 com tracking `-0.03em`, «VerID» em `ink` e «4U» em `brand`. Não existe
arquivo de wordmark vetorizado de propósito: assim o logotipo herda tema claro
e escuro, e nunca sai desalinhado da interface. Para impressão, converta o
texto em curvas na peça.

Nunca: fechar a fresta, girar a marca, aplicar sombra ou gradiente, usar a
cunha encaixada onde não houve conferência, ou reaplicar o símbolo dentro de
um botão que já diz «VerID4U».

---

## Os quatro selos

| Selo | Onde | Cunha | Mínimo |
| --- | --- | --- | --- |
| 1 — do emissor | site do cliente, via `/selo/{emissor}.svg` | encaixada; solta no nível 0 | 44 px horizontal, 88 px compacto |
| 2 — tarja | certificado que o cliente já produz | solta | 14 mm impresso, 54 px tela |
| 3 — circular | certificados emitidos por nós | encaixada | 26 mm de diâmetro |
| 4 — carimbo | comprovante de autenticação | encaixada; solta em revogado e não encontrado | — |

O **círculo é reservado ao selo 3**. Nenhuma outra peça nossa é redonda, e é
essa a função dele: separar, de longe e sem ler, «emitido aqui» de
«registrado aqui».

**Os mínimos foram medidos, não estimados.** O selo circular estava
especificado em 18 mm no estudo de marca; a linha de baixo do anel tem 31
caracteres dando a volta em meia circunferência, o que a 18 mm põe a altura
de maiúscula em 1,0 mm — ilegível depois de uma fotocópia. 26 mm dão 1,5 mm,
que é o piso para texto que ainda precisa ser lido no papel. Pela mesma conta
o qualificador do selo horizontal subiu de 7,8 para 9,2 unidades.

Abaixo do mínimo o qualificador e a linha de verificação somem, e o que sobra
é uma marca bonita que não afirma nada. Quando não couber, use o selo 2.

---

## Cor

`brand` (#1D5BE0) é um azul cobalto. A escolha foi contra um índigo mais
elétrico, que testamos e descartamos: num produto cujo ativo é a confiança, o
tom precisa parecer institucional sem parecer estatal.

**As quatro cores de estado são reservadas.** `ok`, `warn`, `stale` e `bad`
significam Verificado, Vence em breve, Vencido e Revogado. Não as use por
decoração, em gráfico, em ilustração ou como cor de marca — se elas aparecerem
fora de um veredito, o veredito deixa de significar algo.

Duas regras herdadas da acessibilidade e que não se negociam:

1. **Estado nunca é comunicado só por cor.** Todo selo carrega ícone, palavra
   e cor, nessa ordem de importância. Daltonismo é comum e o público é
   universal.
2. **`ink-subtle` é o piso.** Qualquer cinza mais claro reprova 4,5:1 sobre
   `surface`. Já medimos: #8795A8 dá 3,05:1 e foi descartado por isso.

Sobre fundo de marca, texto de apoio fica em branco a 84–88% de opacidade.
Abaixo disso reprova.

---

## Tipografia

- **Inter Tight** no display, sempre com tracking negativo (`-0.032em`) e
  entrelinha quase travada (1,02). É o que dá o ar de confiança sem gritar.
- **Inter** na interface e no corpo de texto.
- **JetBrains Mono** nos códigos de certificado, obrigatoriamente
  monoespaçada: `0`/`O` e `1`/`I` não podem se confundir na leitura humana.
  Como o alfabeto do código é Base32 de Crockford, `I`, `L`, `O` e `U` nem
  existem — mas a fonte é a segunda linha de defesa.
- **Source Serif 4** aparece **somente no certificado impresso**. A serifa diz
  «documento»; usá-la na interface quebraria o registro.

---

## Movimento

Movimento serve para orientar, nunca para enfeitar.

| Token | Valor | Uso |
| --- | --- | --- |
| `--dur-fast` | 150ms | Resposta de toque: botão afundando. |
| `--dur` | 240ms | Mudança de estado: cor, borda, fundo. |
| `--dur-slow` | 420ms | Entrada de conteúdo ao rolar. |
| `--ease` | `cubic-bezier(.2,.8,.2,1)` | Padrão. |
| `--ease-out` | `cubic-bezier(.16,1,.3,1)` | Entradas. |
| `--rise` | 26px | Deslocamento vertical da entrada. |

Duas travas obrigatórias:

- `prefers-reduced-motion: reduce` zera todas as durações e o deslocamento.
- **O estado escondido da animação de entrada vive atrás da classe `.js`**, e
  um temporizador revela tudo após 2,5 s. Conteúdo invisível por causa de
  animação é o pior defeito possível numa página pública — se o observador
  falhar, o texto aparece do mesmo jeito.

---

## Ícones

Traço, nunca preenchimento. `stroke-width` entre 1,5 e 2,2 conforme o tamanho,
pontas e junções arredondadas, grade de 24. Herdam `currentColor` para seguir
o estado. O único ícone preenchido do sistema é o disco interno do selo.

---

## Voz e tom

Num produto cujo ativo é a confiança, a língua é componente. Um verbo errado
faz o sistema afirmar mais do que ele sabe, e afirmar demais uma vez estraga
todas as outras. As regras abaixo não são preferência de estilo: cada uma
corresponde a uma decisão que já está no código.

### 1. Diga o que foi conferido, não o que é verdade

O sistema sabe que **uma emissão consta no registro**. Ele não sabe que a
pessoa aprendeu, que o curso prestou, nem que o papel na mão de alguém é o
mesmo que foi registrado.

| Não | Sim |
| --- | --- |
| Certificado autêntico | Conferido |
| Documento válido | Comprovação de emissão |
| Garantimos a veracidade | Esta emissão consta no registro |

### 2. Quatro resultados, e nenhum é erro

Vencido não é falso: é autêntico e com habilitação expirada. Por isso é cinza
e não vermelho, e por isso a frase nunca começa por «não».

### 3. Nunca afirme além do estado

Emissor de nível 0 recebe «Registra no VerID4U», jamais «Emissor verificado».
A palavra *verificado* exige documento conferido do outro lado. Sem isso seria
propaganda, e propaganda no selo estraga o selo de todo mundo — inclusive o de
quem passou pela conferência de verdade.

A mesma regra vale para a geometria: a cunha só encaixa onde houve
conferência. A forma é lida antes do texto, então ela não pode afirmar mais
que ele.

### 4. No papel, não existe «ativo»

O selo do site diz «verificação ativa» porque é carregado do nosso domínio a
cada visita. O carimbo do comprovante **não diz**, porque no papel não há
verificação nenhuma: há a data, a hora com fuso e o convite a reconsultar. Um
comprovante que se dissesse ativo mentiria no dia seguinte a uma revogação —
justamente o dia em que ele mais importa.

### 5. Sempre diga o que não prova

Toda tela de veredito carrega a lista do que a verificação **não** garante.
Não é modéstia nem jurídico: é o que impede o produto de ser usado como aval
de qualidade de ensino. Se a lista some de uma tela, a tela está errada.

### 6. Dado pessoal: o mínimo que responde a pergunta

Nome de titular sai sempre abreviado. A página pública do emissor mostra
agregados e **nenhum nome**. Quem precisa conferir um certificado tem o
código dele; quem só tem o nome da escola não tem o que fazer com a lista de
quem estudou lá — e a LGPD chama isso de finalidade.

### 7. Sem euforia, sem primeira pessoa no veredito

Nada de exclamação, de parabéns, de «ótima notícia». Quem está lendo é um RH
conferindo contratação ou um auditor conferindo obrigação legal. O veredito
também não diz «nós»: quem afirma é o registro, não a empresa.

| Não | Sim |
| --- | --- |
| Tudo certo! Certificado verificado ✅ | Conferido |
| Não conseguimos encontrar este código | Este código não consta no registro |
| Ops, algo deu errado | O registro está indisponível. Tente de novo em alguns minutos. |

### 8. Erro diz o que fazer

Toda mensagem de erro termina com a ação seguinte. «Código inválido» sozinho
é inútil; «confira a digitação: são 12 caracteres, com ou sem hífen» resolve.

### 9. Texto que é lido em voz alta

Código de certificado e código de comprovante são ditados ao telefone. Por
isso o alfabeto não tem `I`, `L`, `O` nem `U`, os grupos são de quatro, e o
comprovante leva o prefixo `CP` — sem ele, os dois códigos se confundem na
leitura.

### 10. Qualificador é dado, nunca texto livre

«EMISSOR DE CERTIFICADO DE ENSINO E TREINAMENTO» sai do ramo declarado pelo
emissor, de uma lista fechada de cinco. Não existe campo no painel para
editar o texto de nenhum selo. Selo com texto livre vira banner, e banner não
prova nada.

---

## O que o consumidor fornece

Os componentes abaixo esperam que a página já tenha carregado `tokens.css` e
as famílias tipográficas. Nenhum deles traz fonte, reset ou biblioteca: todos
são HTML e CSS puros, para rodar sem etapa de compilação na hospedagem
compartilhada onde a plataforma vive.
