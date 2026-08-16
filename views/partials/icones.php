<?php
/**
 * Icônes SVG inline (tracés maison).
 * @var string $nom
 */
$icones = [
  'nature' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M20 34V16M20 16l-7 8M20 16l7 8M20 6c-5 4-8 8-8 13 0 5 3 9 8 9s8-4 8-9c0-5-3-9-8-13z"/></svg>',
  'hebergement' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 20L20 8l14 12M10 17v15h20V17M17 32v-8h6v8"/></svg>',
  'peche' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 24c4-6 10-9 16-9 5 0 9 2 12 5-3 3-7 5-12 5-6 0-12-1-16-1zM28 17l6-5v14l-6-5M4 32c3 1.6 6 1.6 9 0s6-1.6 9 0 6 1.6 9 0"/><circle cx="25" cy="19.5" r=".9" fill="currentColor"/></svg>',
];
echo $icones[$nom] ?? '';
