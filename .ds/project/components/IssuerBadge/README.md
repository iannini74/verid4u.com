O selo que o emissor instala no site dele. Primeiro componente nosso que roda
fora do nosso site, e por isso o que tem mais regra.

**Não é entregue como arquivo.** É servido de `/selo/{emissor}.svg`, com cinco
minutos de cache. Um PNG que qualquer um baixa provaria exatamente nada;
vindo daqui, o selo só existe enquanto o emissor existir no registro — e é
essa carga, a cada visita, que dá direito à linha «verificação ativa».

Pelo mesmo motivo `image/svg+xml` saiu do `ExpiresByType` no `.htaccess`:
trinta dias de cache fariam o selo de um emissor que saiu do registro
continuar afirmando «verificado» por um mês no site dele.

### Emissor desconhecido responde 200, não 404

Um 404 devolve imagem quebrada, e imagem quebrada não informa quem está
olhando a página: some do jeito que um selo falso gostaria de sumir. O
endereço responde com um selo cinza, de cunha solta, dizendo «selo não
reconhecido». O status HTTP serve à máquina; o desenho serve à pessoa.

### Texto que o selo nunca carrega

Nada escrito pelo emissor. Três linhas, e as três saem do estado dele no
registro:

1. «Emissor verificado», ou «Registra no VerID4U» quando o nível é 0
2. O qualificador, vindo do ramo declarado — lista fechada de cinco
3. «Verificação ativa · verid4u.com», com o ponto verde

### Por que o texto sai com `textLength`

O selo é servido como imagem: não há como carregar nossa fonte, então o texto
sai na fonte do sistema de quem olha, e a largura varia por plataforma.
`Brand::textWidth()` estima e o `<text>` sai com `textLength` fixo e
`lengthAdjust="spacingAndGlyphs"`, o que obriga o desenho a caber na caixa em
qualquer máquina. Erro de alguns por cento vira um aperto imperceptível; sem
isso, o selo estouraria a borda em metade dos sistemas.

### Tamanho

44 px de altura no mínimo, 48 no trecho que entregamos pronto para colar. O
compacto precisa de 88 px e não leva o qualificador: não há largura para ele
sem descer a letra abaixo do legível. Medido, não estimado — a 44 px, o
qualificador na medida antiga (7,8 unidades) dava 5,4 px de maiúscula.
