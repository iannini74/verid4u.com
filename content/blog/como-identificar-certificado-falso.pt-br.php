<?php
return [
'titulo' => 'Como identificar um certificado falso: sete sinais e o que nenhum deles prova',
'resumo' => 'O que olhar num certificado antes de aceitá-lo, por que os sinais visuais já não bastam e qual é a única pergunta que resolve.',
'pilar' => 'Fraude e verificação',
'publicado_em' => '2026-10-30',
'verificado_em' => '2026-10-30',
'autor' => 'Equipe VerID4U',
'leitura' => 6,
'corpo' => [
['t'=>'p','v'=>'Em 2025, **64,2% dos adultos** declararam já ter mentido em um currículo — eram 55% em 2022 ([StandOut-CV](https://standout-cv.com/usa/stats-usa/study-fake-job-references-resume-lies)). Entre os profissionais de RH, **71% já receberam informação fabricada** e apenas **20% confiam em conseguir detectá-la** ([via Avvanz](https://www.avvanz.com/blog/resume-fraud-2026-employer-prevention)). A distância entre esses dois números é o problema inteiro.'],

['t'=>'h','v'=>'Os sete sinais'],
['t'=>'lista','itens'=>[
  '**Instituição que não existe fora do certificado.** Procure o nome no cadastro de CNPJ e no site oficial. Escola séria tem rastro.',
  '**Ausência de carga horária ou de data de conclusão.** Certificado sem esses dois campos não serve para comprovar nada, e quem emite de verdade sabe disso.',
  '**Nenhuma menção à modalidade.** Para treinamento normativo, presencial e EAD têm valor legal diferente. A omissão costuma ser deliberada.',
  '**Assinatura sem nome e sem registro profissional.** Em treinamento de NR, o instrutor responsável é identificável — e responsável.',
  '**Erro de ortografia no nome do curso ou da norma.** Parece banal, mas «NR 35 - Trabalho em Autura» é mais comum do que deveria.',
  '**Código de verificação que não leva a lugar nenhum**, ou que leva a uma página que só repete o que o PDF já dizia.',
  '**Prazo impossível.** Um certificado de NR-10 básico com 8 horas não existe: a norma exige 40.',
]],

['t'=>'h','v'=>'Por que os sinais visuais já não bastam'],
['t'=>'p','v'=>'Todos os sete acima pressupõem um falsificador desleixado. O problema é que falsificar bem ficou trivial: qualquer editor de PDF reproduz um layout, e ferramentas de geração de imagem produzem selos e assinaturas convincentes em segundos.'],
['t'=>'p','v'=>'O Gartner projeta que, até 2028, **cerca de um em cada quatro perfis de candidato no mundo** pode ser fraudulento, parte deles usando áudio e vídeo gerados por IA ([via Sertifier](https://sertifier.com/blog/credential-fraud-2026/)). Conferir aparência contra uma falsificação assistida por IA é uma corrida perdida.'],
['t'=>'nota','v'=>'Um QR Code no certificado **não é prova**. Ele prova, no máximo, que o arquivo saiu de determinada ferramenta — não que a instituição existe, que o curso aconteceu ou que a pessoa concluiu. Um falsificador também consegue pôr um QR bonito.'],

['t'=>'h','v'=>'A única pergunta que resolve'],
['t'=>'p','v'=>'Não é «este documento parece legítimo?». É: **existe um registro independente deste certificado, feito por quem o emitiu, que eu possa consultar sem depender de quem me entregou o papel?**'],
['t'=>'p','v'=>'A diferença é de direção. Olhar o documento é confiar no que o portador trouxe. Consultar um registro é perguntar à fonte. Só a segunda resiste a um falsificador competente.'],

['t'=>'h','v'=>'O que um registro bem feito mostra'],
['t'=>'lista','itens'=>[
  'Se o certificado é autêntico — e se continua **válido**, que são coisas diferentes.',
  'Quem emitiu, e **quanto essa instituição comprovou sobre si mesma**.',
  'Carga horária, modalidade e se houve avaliação.',
  'Se foi **revogado** pelo próprio emissor, e por quê.',
  'O que o registro **não** prova. Um serviço que não declara seus limites está vendendo confiança que não tem.',
]],
['t'=>'p','v'=>'Esse último item é o mais revelador. Nenhum registro consegue provar que a pessoa à sua frente é a titular do certificado, nem atestar a qualidade do curso. Quem promete isso está exagerando — e exagero, num serviço de verificação, é o mesmo problema que a fraude que ele diz combater.'],
],
];
