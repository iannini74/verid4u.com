<?php
/**
 * Selo de estado.
 *
 * Quatro resultados, uma só geometria: a cunha encaixa quando bate e fica
 * solta quando não bate ou quando não há o que bater. Cor e texto fazem o
 * resto da distinção — a forma não finge saber mais do que sabe.
 *
 * @var ?string $sealClass
 * @var ?string $sealGlyph  estado herdado das chamadas antigas
 * @var ?string $sealState  'fit' | 'open'
 */
$state = $sealState ?? ((($sealGlyph ?? 'check') === 'check') ? 'fit' : 'open');
echo Brand::mark($state, $sealClass ?? 'verdict-seal');
