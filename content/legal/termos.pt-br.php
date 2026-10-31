<?php
/**
 * Termos de Uso.
 *
 * Escritos a partir do que o sistema realmente faz, não de um modelo
 * genérico. A cláusula que mais importa é a da responsabilidade pela
 * veracidade: quem emite responde pelo conteúdo, e o VerID4U atesta o
 * registro — confundir as duas coisas seria vender uma garantia que não
 * existe.
 */
return [
'titulo' => 'Termos de Uso',
'resumo' => 'Em uma frase: o VerID4U registra certificados e permite que qualquer pessoa confira a autenticidade deles. Quem emite responde pelo conteúdo do certificado; nós respondemos pelo registro.',
'secoes' => [

['h' => '1. Quem somos', 'p' => [
  'O VerID4U é um serviço de registro e verificação de certificados operado por {razao_social}{cnpj_frase}{endereco_frase}.',
  'Ao usar o serviço, você concorda com estes Termos. Se não concordar, não use.',
]],

['h' => '2. O que o serviço faz', 'p' => [
  'O VerID4U atribui a cada certificado um código único e uma página pública de verificação. Ele funciona de dois modos, com significados diferentes:',
]],

['h' => '2.1 Emissão', 'p' => [
  'O certificado é gerado pela plataforma, com código, selo e QR Code. O VerID4U produziu o documento a pedido do emissor.',
]],

['h' => '2.2 Registro', 'p' => [
  'O certificado foi produzido pela instituição no sistema dela e apenas registrado aqui. Neste caso o VerID4U **não produziu** o documento: atesta a data do registro, a integridade do conteúdo declarado e, quando há impressão digital do arquivo, que determinado arquivo é bit a bit o que foi registrado.',
]],

['h' => '3. O que o serviço NÃO faz', 'p' => [
  'Esta seção é tão importante quanto a anterior, e pedimos que seja lida com atenção.',
  'O VerID4U não verifica se o curso aconteceu, se teve a qualidade anunciada ou se a pessoa realmente aprendeu. Não é instituição de ensino, não credencia ninguém e não substitui órgão regulador, conselho de classe ou o Ministério da Educação.',
  'O registro não é assinatura digital no padrão ICP-Brasil. Onde a lei exigir assinatura qualificada, ela continua sendo necessária — oferecemos esse módulo à parte, e ele é opcional.',
  'A verificação não prova que a pessoa que apresenta o certificado é a titular dele. Prova que aquele registro existe, com aquele conteúdo, naquela data.',
]],

['h' => '4. Definições', 'p' => [
  '**Emissor**: a pessoa ou instituição que cria a conta e registra ou emite certificados. **Titular**: a pessoa a quem o certificado se refere. **Verificador**: qualquer pessoa que consulta um código, com ou sem conta.',
]],

['h' => '5. Conta e acesso', 'p' => [
  'A conta é pessoal e intransferível. Você é responsável por manter a senha em segurança e por tudo o que for feito com as suas credenciais.',
  'Avise imediatamente se suspeitar de acesso indevido. Podemos encerrar sessões ativas quando houver indício de comprometimento.',
]],

['h' => '6. Responsabilidade de quem emite', 'p' => [
  'Esta é a cláusula central destes Termos. **O emissor é o único responsável pela veracidade do que registra.** Ao registrar ou emitir um certificado, o emissor declara que o curso ocorreu, que o titular o concluiu nas condições informadas e que tem autorização para tratar os dados pessoais envolvidos.',
  'O VerID4U não tem como conferir isso, e não promete tê-lo conferido. Nossa página de verificação exibe o nível de verificação do emissor justamente para que quem consulta saiba o que foi e o que não foi comprovado.',
  'Registro falso é fraude documental e pode caracterizar crime. Além de encerrar a conta, podemos comunicar as autoridades competentes e preservar as provas.',
]],

['h' => '7. Uso proibido', 'p' => [
  'É proibido: registrar certificado de curso que não aconteceu; registrar dados de pessoa sem autorização; usar o serviço para se passar por instituição que você não representa; tentar enumerar ou raspar a base de códigos; interferir no funcionamento do serviço; e usar a plataforma para qualquer finalidade ilícita.',
  'A verificação pública é gratuita para consulta individual. Consulta automatizada em volume exige a API, com contrato.',
]],

['h' => '8. Níveis de verificação do emissor', 'p' => [
  'Exibimos publicamente quanto cada emissor comprovou sobre si: do nível 0 (apenas e-mail confirmado) ao nível 4 (evidências dos cursos auditadas por amostragem). Os níveis superiores dependem de documentação e podem ser pagos.',
  'Reservamo-nos o direito de rebaixar ou suspender um nível quando a comprovação deixar de ser válida, e de recusar a elevação sem obrigação de justificar em detalhe — embora sempre informemos o motivo geral.',
]],

['h' => '9. Disponibilidade e a promessa de verificação', 'p' => [
  'Trabalhamos para manter o serviço disponível, mas não garantimos funcionamento ininterrupto. Manutenções, falhas de terceiros e incidentes acontecem, e os comunicamos na página de status.',
  '**Compromisso de verificação perpétua**: certificados já registrados continuam verificáveis mesmo que o emissor cancele a assinatura. Encerrar o pagamento interrompe novas emissões, não a consulta às antigas — punir o titular por inadimplência de terceiro destruiria o sentido de um registro.',
  'Se a operação do VerID4U for encerrada, nos comprometemos a manter o índice de verificação publicado em arquivo estático e a disponibilizar a exportação completa em padrão aberto (Open Badges 3.0) com antecedência mínima de 90 dias.',
]],

['h' => '10. Planos e pagamento', 'p' => [
  'Durante a fase atual o serviço é oferecido sem cobrança. Quando houver planos pagos, as condições serão apresentadas antes da contratação e nenhuma cobrança ocorrerá sem aceite expresso.',
  'Certificados registrados na fase gratuita permanecem verificáveis, conforme a seção 9.',
]],

['h' => '11. Propriedade intelectual', 'p' => [
  'A plataforma, a marca VerID4U, o código e o design são da {razao_social}. O conteúdo dos certificados é de quem os emitiu.',
  'Ao registrar um certificado, o emissor nos concede licença limitada para armazenar, processar e exibir publicamente as informações necessárias à verificação — nada além disso.',
]],

['h' => '12. Limitação de responsabilidade', 'p' => [
  'O VerID4U responde pelo funcionamento do registro e da verificação. Não responde por prejuízo decorrente de informação falsa prestada por um emissor, por decisão tomada por terceiro com base em um certificado, nem por indisponibilidade de serviços de terceiros dos quais dependemos.',
  'Nos limites da lei, nossa responsabilidade total fica limitada ao valor pago pelo cliente nos 12 meses anteriores ao evento. Nada aqui afasta direitos que o Código de Defesa do Consumidor assegure a consumidores.',
]],

['h' => '13. Suspensão e encerramento', 'p' => [
  'Podemos suspender ou encerrar contas que violem estes Termos, com aviso prévio sempre que possível e imediatamente quando houver risco a terceiros.',
  'Você pode encerrar sua conta quando quiser. Os certificados já registrados permanecem verificáveis, pelo motivo explicado na seção 9.',
]],

['h' => '14. Alterações', 'p' => [
  'Podemos alterar estes Termos. Mudanças relevantes serão comunicadas por e-mail e anunciadas no site com antecedência de 30 dias. Continuar usando o serviço após a vigência significa aceitar a nova versão.',
]],

['h' => '15. Lei aplicável', 'p' => [
  'Estes Termos são regidos pela lei brasileira. Fica eleito o foro {foro_frase} para dirimir controvérsias, ressalvado o direito do consumidor de demandar no foro de seu domicílio.',
]],

]];
