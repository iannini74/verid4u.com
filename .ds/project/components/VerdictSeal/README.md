O selo de veredito é o componente mais importante do sistema: é ele que
aparece na única tela que o mundo vê, depois de escanear um QR Code.

Quatro estados, e **nenhum deles é um erro** — essa é a distinção que separa
este produto dos concorrentes:

| Estado | Token | Cunha | O que significa |
| --- | --- | --- | --- |
| Verificado | `ok` | encaixada | Documento autêntico, habilitação vigente. |
| Vence em breve | `warn` | encaixada | Autêntico, mas dentro dos últimos 90 dias de validade. |
| Vencido | `stale` | encaixada | Autêntico, habilitação expirada. Cinza, não vermelho: vencido não é falso. |
| Revogado | `bad` | solta | Cancelado pelo próprio emissor. |

A cunha encaixa nos três estados em que a emissão **bate** com o registro, e
fica solta onde nada bateu — revogado, não encontrado, indisponível. É a
mesma geometria da marca, e ela carrega a mesma distinção que o texto.

Repare no que a forma **não** tenta fazer: distinguir vigente de vencido. Esse
é trabalho da cor e da palavra. Uma geometria que tentasse exprimir quatro
resultados com uma peça só acabaria exprimindo nenhum — a cunha responde à
pergunta «bate?», e só a essa.

Regra que não se negocia: **forma + palavra + cor, sempre as três**. Um selo
que comunica só por cor é inútil para quem tem daltonismo e ilegível em
impressão monocromática.

Herda a cor do contexto via `currentColor`. Vem de `Brand::mark()`, nunca
escrito à mão: o `viewBox` muda com o estado, porque encaixada a marca é mais
estreita. Dimensione **por altura**, com largura automática.
