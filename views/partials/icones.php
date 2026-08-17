<?php
/**
 * Icônes SVG en ligne (tracés maison, aucune bibliothèque externe).
 *
 * Dessinées sur une grille de 40 et sans remplissage : elles prennent la
 * couleur du texte qui les entoure et restent nettes à toute taille.
 *
 * @var string $nom
 */
$icones = [
    // --- services ---------------------------------------------------------
    'comptabilite' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="5" width="22" height="30" rx="2.5"/><path d="M14 12h12M14 19h5M14 25h5M14 31h5"/><rect x="24" y="19" width="7" height="12" rx="1.5"/></svg>',
    'audit' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="11"/><path d="m26 26 8 8"/><path d="m13.5 18 3.2 3.4 6.6-6.9"/></svg>',
    'social' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="15" cy="13" r="5"/><path d="M6 32c0-5 4-8.5 9-8.5s9 3.5 9 8.5"/><circle cx="28" cy="15" r="4"/><path d="M26 24c4.5 0 8 3 8 8"/></svg>',
    'conseil' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 5a10 10 0 0 0-6 18v3.5h12V23a10 10 0 0 0-6-18z"/><path d="M16.5 31h7M17.5 35h5"/></svg>',

    // --- types de clients -------------------------------------------------
    'liberal' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 8 11v8c0 7 5 12.5 12 15 7-2.5 12-8 12-15v-8L20 6z"/><path d="m15 20 3.5 3.5L26 16"/></svg>',
    'commerce' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 14h26l-2 4H9l-2-4zM8 6h24M10 18v16h20V18"/><path d="M17 34v-9h6v9"/></svg>',
    'association' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 33S8 26 8 17.5A6.5 6.5 0 0 1 20 14a6.5 6.5 0 0 1 12 3.5C32 26 20 33 20 33z"/></svg>',
    'artisan' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m10 30 12-12M8 28l4 4M25 6l-4 4 8 8 4-4-8-8zM21 10l-3.5 3.5 8 8L29 18"/></svg>',
    'industrie' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 34V17l8 5v-5l8 5v-5l8 5V34H6z"/><path d="M28 17V8h5v9"/></svg>',

    // --- éléments d'interface --------------------------------------------
    'telephone' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 6h-.5A5.5 5.5 0 0 0 7 11.5C7 24 16 33 28.5 33a5.5 5.5 0 0 0 5.5-5.5V27l-7-3-3 4c-4-2-7.5-5.5-9.5-9.5l4-3-5.5-9.5z"/></svg>',
    'adresse' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 35s11-10 11-18a11 11 0 1 0-22 0c0 8 11 18 11 18z"/><circle cx="20" cy="17" r="4"/></svg>',
    'horaires' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="20" cy="20" r="14"/><path d="M20 11v9.5l6 4"/></svg>',
    'courriel' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="9" width="30" height="22" rx="2.5"/><path d="m6 11 14 10 14-10"/></svg>',
    'etoile' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z"/></svg>',
];

echo $icones[$nom] ?? '';
