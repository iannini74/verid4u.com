<?php
/**
 * Política de Privacidade e Cookies.
 *
 * Descreve o sistema como ele é, não como seria conveniente. Cada afirmação
 * aqui corresponde a algo verificável no código: a cifragem é AES-256-GCM,
 * o documento é guardado como HMAC, a senha usa Argon2id, a página pública
 * nunca recebe contato. Política que promete mais do que o código entrega é
 * passivo, não proteção.
 */
return [
'titulo' => 'Política de Privacidade e Cookies',
'resumo' => 'Em uma frase: tratamos o mínimo necessário para que um certificado possa ser conferido, a página pública nunca mostra documento, e-mail ou telefone, e não usamos nenhum cookie de rastreamento.',
'secoes' => [

['h' => '1. Em resumo', 'p' => [
  'Esta política explica o que fazemos com dados pessoais. Se você só quer o essencial:',
  '**O que aparece em público**: nome do titular (parcial por padrão), curso, carga horária, modalidade, datas e o emissor. **O que nunca aparece**: CPF ou qualquer documento, e-mail, telefone, endereço.',
  '**Cookies**: usamos dois, ambos estritamente necessários (sessão e proteção contra CSRF). Nenhum de rastreamento, publicidade ou perfilamento. Por isso não há banner de consentimento — não haveria o que consentir.',
  '**Indexação**: as páginas de verificação não são indexadas por buscadores. Carregam dados pessoais e são quase idênticas entre si; deixá-las indexáveis permitiria montar, por busca, a lista de certificados de uma pessoa.',
]],

['h' => '2. Quem é responsável pelo quê', 'p' => [
  'Esta separação determina a quem você deve pedir cada coisa.',
  '**O emissor é o controlador** dos dados do titular. Foi ele quem decidiu emitir o certificado, quem definiu o que consta nele e quem responde pela veracidade.',
  '**O VerID4U é operador** quando registra e emite em nome do emissor: tratamos os dados conforme a instrução dele.',
  '**O VerID4U é controlador** de um conjunto próprio e menor: contas de usuário da plataforma, registros de verificação, dados de cobrança e a carteira voluntária do titular.',
]],

['h' => '3. Dados que tratamos', 'p' => [
  '**Do titular do certificado**: nome, e-mail (quando o emissor o informa) e, opcionalmente, um documento — este último nunca em texto claro, apenas como código irreversível (HMAC-SHA256) que permite conferir sem exibir.',
  '**Do usuário da plataforma**: nome, e-mail, senha (guardada como hash Argon2id, nunca recuperável) e registro de acessos.',
  '**De quem verifica**: endereço IP em forma reduzida e com hash, descartado em 90 dias. Não exigimos cadastro para consultar e não criamos perfil de quem consulta.',
  '**Técnicos**: registros de erro e de entrega de e-mail, necessários para operar o serviço.',
]],

['h' => '4. Por que tratamos (bases legais da LGPD)', 'p' => [
  '**Emitir e registrar o certificado**: execução de contrato entre emissor e titular; e cumprimento de obrigação legal, nos treinamentos obrigatórios de segurança do trabalho.',
  '**Manter a verificação pública**: legítimo interesse, com minimização rigorosa — é a finalidade que dá sentido ao serviço, e sem ela o certificado volta a ser um arquivo sem prova.',
  '**Carteira e perfil público do titular**: consentimento específico, revogável a qualquer momento.',
  '**Avisos de vencimento e mensagens sobre o certificado**: execução de contrato e legítimo interesse do emissor. **Marketing**: consentimento separado, nunca embutido no aceite dos termos.',
  '**Registros de verificação**: legítimo interesse em segurança e prevenção a fraude.',
]],

['h' => '5. A página pública de verificação', 'p' => [
  'É a parte mais sensível do serviço, e por isso tem regras próprias.',
  'Nunca exibimos CPF, RG, passaporte, e-mail, telefone ou endereço — nem parcialmente, nem "apenas os últimos dígitos".',
  'O nome do titular aparece de forma parcial por padrão (por exemplo, «Maria S. L.»). O emissor pode configurar exibição completa ou oculta, e o titular pode pedir a alteração a qualquer momento.',
  'Quando for necessário confirmar identidade, usamos prova de conhecimento: quem verifica digita os últimos dígitos do documento e o sistema responde apenas «confere» ou «não confere», sem nunca exibir o dado.',
]],

['h' => '6. Cookies', 'p' => [
  'Usamos exatamente dois, ambos essenciais ao funcionamento e nenhum deles de rastreamento:',
  '**v4u_session** — mantém você conectado à área do emissor. Dura 14 dias, é httponly e secure, e guarda apenas um identificador aleatório; no servidor fica somente o hash dele.',
  '**v4u_csrf** — protege formulários contra envio forjado a partir de outro site. Dura 24 horas.',
  'Quem apenas consulta um certificado, sem entrar na área do emissor, pode receber o cookie de proteção de formulário e nada além disso. Não usamos Google Analytics, pixel de rede social, cookie de publicidade ou qualquer rastreador de terceiros.',
]],

['h' => '7. Terceiros que participam do serviço', 'p' => [
  '**Hostinger** — hospedagem, banco de dados e CDN. Os dados ficam na infraestrutura dela.',
  '**Resend** — envio de e-mail transacional, na região de São Paulo. Recebe o endereço do destinatário e o conteúdo da mensagem.',
  '**Google Fonts e Cloudflare (cdnjs)** — fornecem as fontes tipográficas e a biblioteca que desenha o QR Code. O navegador de quem visita faz uma requisição a esses serviços, o que expõe o endereço IP a eles. Estamos avaliando passar a servir esses arquivos do nosso próprio domínio para eliminar essa exposição.',
  'Não vendemos, alugamos nem cedemos dados pessoais a ninguém.',
]],

['h' => '8. Transferência internacional', 'p' => [
  'O envio de e-mail e as fontes tipográficas podem envolver tratamento fora do Brasil. Nesses casos, a transferência se apoia nas cláusulas contratuais dos fornecedores e nas hipóteses do artigo 33 da LGPD.',
]],

['h' => '9. Segurança', 'p' => [
  'Medidas concretas, não promessas genéricas:',
  'Tráfego sempre em TLS. E-mail do titular cifrado em repouso com AES-256-GCM. Documento guardado apenas como HMAC-SHA256, nunca em claro. Senha com Argon2id. Sessões com token aleatório, do qual o servidor guarda só o hash. Proteção contra envio forjado em todos os formulários. Limite de consultas por origem, contra tentativa de enumerar a base. Trilha de auditoria imutável de tudo que altera estado. Código da aplicação e arquivos de configuração inacessíveis pela web.',
  'Nenhum sistema é inviolável. Se algo acontecer, a seção 12 explica o que fazemos.',
]],

['h' => '10. Por quanto tempo guardamos', 'p' => [
  '**Registros de certificado**: enquanto o registro existir — é a finalidade do serviço, e vários são prova de obrigação legal trabalhista.',
  '**Registros de verificação**: 90 dias de forma identificável; depois disso, apenas números agregados.',
  '**Conta de usuário**: enquanto a conta existir, mais o prazo legal de guarda de registros de acesso.',
  '**Mensagens de e-mail**: metadados por 12 meses, para diagnóstico de entrega.',
]],

['h' => '11. Seus direitos, e como exercê-los', 'p' => [
  'A LGPD garante a você: confirmação de tratamento, acesso, correção, anonimização, portabilidade, informação sobre compartilhamento e revogação de consentimento.',
  'Escreva para {email_dpo}. Respondemos em até 15 dias.',
  '**Sobre a exclusão, com honestidade.** Você pode, a qualquer momento e sem justificar, pedir que seu nome deixe de aparecer na página pública — isso é imediato. Já a existência do registro (curso, emissor, data e impressão digital) permanece, porque nessa parte o controlador é o emissor e, em treinamentos obrigatórios, há dever legal de guarda: um certificado de NR precisa existir para fins de fiscalização, independentemente da vontade de quem o recebeu. Pedidos de exclusão total são encaminhados ao emissor, que é quem pode decidir, e você acompanha o andamento conosco.',
  'A âncora criptográfica que comprova a data contém apenas o resumo matemático do registro — nunca dado pessoal. Por isso ela pode permanecer publicada sem conflitar com o seu direito.',
]],

['h' => '12. Incidentes de segurança', 'p' => [
  'Se ocorrer incidente que possa gerar risco relevante, comunicamos a Autoridade Nacional de Proteção de Dados e os titulares afetados em até três dias úteis, conforme a Resolução CD/ANPD nº 15/2024, com as informações complementares no prazo regulamentar.',
  'Aos emissores de quem somos operadores, avisamos em até 24 horas.',
]],

['h' => '13. Encarregado pelo tratamento de dados', 'p' => [
  'Contato: {email_dpo}.',
]],

['h' => '14. Alterações desta política', 'p' => [
  'Mudanças relevantes são comunicadas por e-mail e anunciadas no site com 30 dias de antecedência. As versões anteriores ficam disponíveis mediante pedido.',
]],

]];
