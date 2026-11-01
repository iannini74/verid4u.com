O campo onde se digita o código do certificado. É o primeiro elemento da
landing, acima de qualquer texto de venda, porque a maioria das visitas vem de
alguém que acabou de escanear um QR Code e quer uma resposta.

Decisões que parecem detalhe e não são:

- **Monoespaçada e em caixa alta**, com `letter-spacing: .07em`. O código é
  lido de um papel e digitado por uma pessoa com pressa.
- **A máscara aplica-se enquanto se digita** (`K7M2-93FQ-5XB0`), e a entrada
  aceita minúsculas, sem hífen, e lê `I`/`L` como `1` e `O` como `0`. O
  alfabeto Base32 de Crockford já exclui esses caracteres; a tolerância existe
  para quem não sabe disso.
- **O placeholder é um código válido de verdade.** Um exemplo que falha na
  validação ensina ao usuário que o produto não funciona.
- Sobre fundo de marca, o campo é translúcido com borda branca; sobre fundo
  claro, usa `surface-sunken` com borda `line`.

O erro aparece abaixo do campo, com ícone e `role="alert"`, nunca só com a
borda vermelha.
