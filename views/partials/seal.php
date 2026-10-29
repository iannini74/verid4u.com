<?php /* Selo de estado. $sealClass define o tamanho; a cor vem do contexto. */ ?>
<svg class="<?= e($sealClass ?? 'verdict-seal') ?>" viewBox="0 0 96 96" aria-hidden="true" focusable="false">
  <circle cx="48" cy="48" r="45" fill="none" stroke="currentColor" stroke-width="2"
          stroke-dasharray="3.2 5.72" stroke-linecap="round" opacity=".5"/>
  <circle cx="48" cy="48" r="37" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".3"/>
  <circle cx="48" cy="48" r="30" fill="currentColor"/>
  <?php if (($sealGlyph ?? 'check') === 'check'): ?>
    <path d="M34 48.6 43.4 58 62 38.4" fill="none" stroke="var(--surface)" stroke-width="5.4" stroke-linecap="round" stroke-linejoin="round"/>
  <?php elseif ($sealGlyph === 'clock'): ?>
    <path d="M48 33v16l10 6" fill="none" stroke="var(--surface)" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
  <?php elseif ($sealGlyph === 'cross'): ?>
    <path d="M38 38l20 20M58 38L38 58" fill="none" stroke="var(--surface)" stroke-width="5.4" stroke-linecap="round"/>
  <?php else: ?>
    <path d="M41.5 42.4a6.8 6.8 0 1 1 7.6 8v3.2" fill="none" stroke="var(--surface)" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="49" cy="61.5" r="3" fill="var(--surface)"/>
  <?php endif; ?>
</svg>
