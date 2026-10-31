<?php
/**
 * Variações da primeira dobra.
 *
 * Ficam em arquivo versionado, não no banco: copy é conteúdo, não dado.
 * Assim cada mudança de texto passa por commit, dá para ver o histórico e
 * voltar atrás — e não é preciso construir um CRUD para editar frase.
 *
 * A rotação é aritmética de data: a cada 15 dias a lista avança uma posição.
 * Sem cron, sem estado no banco, sem nada para quebrar. Para fixar uma
 * variação manualmente, defina 'hero_pin' no config.local.php com o slug.
 *
 * Cada variação ataca um ângulo diferente e fala com um público diferente.
 * O campo de verificação está sempre presente — muda a moldura, não a função.
 */
return [
    [
        'slug'   => 'confira',
        'alvo'   => 'quem verifica',
        'title'  => 'Confira se um certificado é verdadeiro',
        'sub'    => 'Digite o código impresso no documento. É gratuito, não precisa de cadastro e leva dez segundos.',
    ],
    [
        'slug'   => 'pdf',
        'alvo'   => 'RH e compliance',
        'kicker' => 'Para quem contrata',
        'title'  => 'Pare de acreditar em PDF.',
        'sub'    => '71% dos profissionais de RH já receberam informação fabricada num currículo. Só 20% confiam em conseguir detectá-la. Conferir leva dez segundos.',
    ],
    [
        'slug'   => 'vale-o-quanto',
        'alvo'   => 'quem emite',
        'kicker' => 'Para quem emite',
        'title'  => 'Seu certificado vale o quanto ele pode ser conferido.',
        'sub'    => 'Enquanto for só um arquivo, ele compete em pé de igualdade com o que alguém imprimiu em casa. Registre o seu e mostre a diferença.',
    ],
    [
        'slug'   => 'fiscal',
        'alvo'   => 'SST e NR',
        'kicker' => 'Segurança do trabalho',
        'title'  => 'O fiscal chegou. Quanto tempo você leva para provar o treinamento?',
        'sub'    => 'Desde agosto de 2026 o auditor não orienta mais antes de autuar. Com registro, a prova de cada NR é uma consulta — não uma tarde procurando em pasta.',
    ],
    [
        'slug'   => 'isbn',
        'alvo'   => 'geral',
        'kicker' => 'A lacuna',
        'title'  => 'Um livro tem ISBN. Um certificado tem nome de arquivo.',
        'sub'    => 'Nunca existiu um identificador universal para um certificado de curso. É isso que o VerID4U dá a cada um deles — de graça para quem consulta, para sempre.',
    ],
    [
        'slug'   => 'voce-estudou',
        'alvo'   => 'titular',
        'kicker' => 'Para quem estudou',
        'title'  => 'Você estudou de verdade. Agora prove em dez segundos.',
        'sub'    => 'Como o mercado aceita qualquer certificado, nenhum vale muito. Quem tem registro verificável deixa de pagar pela desconfiança que os outros criaram.',
    ],
    [
        'slug'   => 'sem-curso',
        'alvo'   => 'escolas',
        'kicker' => 'Para escolas e centros de treinamento',
        'title'  => 'Tem gente vendendo certificado sem curso. Mostre que o seu não é assim.',
        'sub'    => 'Enquanto o seu documento for um PDF como qualquer outro, o comprador decide pelo preço. Um registro público é a diferença que aparece sem você falar mal de ninguém.',
    ],
    [
        'slug'   => 'venceu',
        'alvo'   => 'gestores',
        'kicker' => 'Controle de validade',
        'title'  => 'Quantos treinamentos da sua equipe venceram esta semana?',
        'sub'    => 'Se a resposta depende de uma planilha mantida à mão, é porque ninguém sabe. O registro avisa o titular e o gestor antes de vencer.',
    ],
    [
        'slug'   => 'curriculo',
        'alvo'   => 'geral',
        'title'  => 'Currículo aceita qualquer coisa. Certificado também. Até agora.',
        'sub'    => '64,2% dos adultos já mentiram num currículo. O problema não é a mentira — é que ninguém consegue conferir.',
    ],
    [
        'slug'   => 'dez-segundos',
        'alvo'   => 'quem verifica',
        'title'  => 'Autêntico, vencido ou cancelado? Dez segundos.',
        'sub'    => 'Quatro respostas possíveis e nunca um talvez. Sem cadastro, sem custo, sem ligar para a escola.',
    ],
    [
        'slug'   => 'secretaria',
        'alvo'   => 'instituições',
        'kicker' => 'Para instituições',
        'title'  => 'Sua secretaria ainda confirma certificado por telefone?',
        'sub'    => 'Cada pedido de «esse documento é de vocês mesmo?» vira uma consulta que a própria empresa faz sozinha, sem passar por você.',
    ],
    [
        'slug'   => 'no-escuro',
        'alvo'   => 'geral',
        'kicker' => 'Por que existimos',
        'title'  => 'Que nenhum aprendizado honesto precise ser aceito no escuro.',
        'sub'    => 'Todo certificado merece uma prova. Registre o seu — qualquer pessoa confere em dez segundos.',
    ],
];
