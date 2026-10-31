<?php
/**
 * A marca, embutida para herdar a cor do contexto.
 *
 * $markState: 'open' (cunha solta) ou 'fit' (encaixada). O logotipo é a forma
 * solta — é ela que mostra as duas peças e faz a pergunta. O encaixe é
 * resposta, e resposta é estado, não identidade.
 *
 * @var ?string $markState
 * @var ?string $markClass
 */
echo Brand::mark($markState ?? 'open', $markClass ?? 'brand-mark');
