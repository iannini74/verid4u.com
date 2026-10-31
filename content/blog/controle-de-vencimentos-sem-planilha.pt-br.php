<?php
return [
'titulo' => 'Controle de vencimentos: por que a planilha falha e o que colocar no lugar',
'resumo' => 'A maior parte das não conformidades em fiscalização não é treinamento inexistente — é treinamento vencido. A diferença está em quem precisa lembrar.',
'pilar' => 'Gestão de treinamento',
'publicado_em' => '2026-11-28',
'verificado_em' => '2026-10-30',
'autor' => 'Equipe VerID4U',
'leitura' => 5,
'corpo' => [
['t'=>'p','v'=>'Pergunte a qualquer responsável por SST quantos treinamentos da equipe vencem no mês que vem. Se a resposta depende de abrir uma planilha e conferir linha a linha, a resposta verdadeira é: ninguém sabe.'],

['t'=>'h','v'=>'A conta que a planilha não aguenta'],
['t'=>'p','v'=>'Uma empresa com 200 pessoas e quatro normas aplicáveis tem **800 datas de vencimento** em circulação. Cada norma tem periodicidade própria — a NR-33 recicla todo ano, a NR-10 e a NR-35 a cada dois. Cada admissão, desligamento ou mudança de função embaralha a conta.'],
['t'=>'p','v'=>'A planilha não falha por ser planilha. Falha porque transfere para uma pessoa a obrigação de lembrar — e lembrar não escala.'],

['t'=>'h','v'=>'Os quatro modos de falhar'],
['t'=>'lista','itens'=>[
  '**O vencimento silencioso.** Nada avisa. A descoberta acontece na auditoria.',
  '**A data única.** Registra-se quando o curso foi feito, mas não até quando vale — e aí alguém precisa saber de cabeça a periodicidade de cada norma.',
  '**A modalidade ausente.** Desde julho de 2026, NR-35 em EAD não vale. Quem não registrou a modalidade não consegue provar a validade.',
  '**O terceirizado invisível.** A planilha cobre os próprios funcionários. Quem entra na obra pela empreiteira não aparece em lugar nenhum.',
]],

['t'=>'h','v'=>'O que colocar no lugar'],
['t'=>'p','v'=>'Não é um sistema maior. É uma inversão: em vez de alguém consultar o controle, **o controle avisa**.'],
['t'=>'lista','itens'=>[
  'Validade calculada a partir da norma, não digitada à mão.',
  'Aviso automático em marcos — 90, 30 e 7 dias — para o titular **e** para o gestor.',
  'Modalidade e instrutor gravados em cada certificado, porque são dado de conformidade.',
  'Terceirizados no mesmo painel que os próprios, já que o fiscal não distingue.',
  'Prova verificável por quem não é da casa, sem telefonema.',
]],
['t'=>'nota','v'=>'Uma consequência comercial que costuma passar batido: quando o aviso de reciclagem sai automaticamente, a renovação volta para quem emitiu o treinamento original. Sem aviso, ela vai para quem ligar primeiro — normalmente o concorrente.'],

['t'=>'h','v'=>'Comece pelo que dói'],
['t'=>'p','v'=>'Não é preciso migrar tudo de uma vez. Comece pelas normas de reciclagem anual, que são as que vencem primeiro e as que mais aparecem em autuação. Importe o histórico, deixe o sistema calcular os vencimentos e veja quantos já passaram sem ninguém notar.'],
['t'=>'p','v'=>'Esse primeiro número costuma ser o argumento mais convincente que existe.'],
],
];
