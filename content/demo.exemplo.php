<?php
/**
 * Para trocar a demonstração interativa por um vídeo:
 * copie este arquivo para demo.pt-br.php (ou .pt / .es / .en) e preencha
 * 'video' com a URL de incorporação. A demonstração sai e o vídeo entra,
 * sem mexer em código.
 *
 * Use sempre a URL de incorporação, não a de compartilhamento:
 *   YouTube : https://www.youtube-nocookie.com/embed/ID
 *   Vimeo   : https://player.vimeo.com/video/ID
 *
 * Atenção: incorporar vídeo de terceiro reintroduz a dependência externa
 * que acabamos de eliminar, e exige ajustar a CSP (frame-src) e a seção 7
 * da política de privacidade. O domínio -nocookie do YouTube reduz, mas não
 * elimina, o rastreamento.
 */
return [
    'video' => '',
];
