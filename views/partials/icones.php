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
    // --- gammes -----------------------------------------------------------
    'pergola-bioclimatique' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13h30M8 13v22M32 13v22"/><path d="M9 8.5h22M9 11.5h22" stroke-width="2.2"/><path d="M11 17.5h18M11 21.5h18" opacity=".55"/></svg>',
    'pergola-toile' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 11v24M32 11v24"/><path d="M5 11h30"/><path d="M8 15c5 3 19 3 24 0"/><path d="M8 20c5 3 19 3 24 0" opacity=".55"/></svg>',
    'toiture-fixe' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17 20 7l16 10"/><path d="M8 17v18M32 17v18"/><path d="M4 17h32v4H4z"/></svg>',
    'carport' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14h32M7 14v21M33 14v21"/><path d="M12 30h16l-2-6H14l-2 6z"/><circle cx="15" cy="32" r="1.6"/><circle cx="25" cy="32" r="1.6"/></svg>',
    'fermetures' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="7" width="28" height="26" rx="2"/><path d="M20 7v26"/><path d="M16 20h-4M28 20h-4"/></svg>',

    // --- atouts -----------------------------------------------------------
    'france' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 5 9 9v9c0 8 4.5 13.5 11 16 6.5-2.5 11-8 11-16V9l-8-4h-6z"/><path d="M20 15v10M15 20h10"/></svg>',
    'sur-mesure' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 14h30v12H5z"/><path d="M11 14v5M17 14v8M23 14v5M29 14v8"/></svg>',
    'pose' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m10 30 12-12M8 28l4 4M25 6l-4 4 8 8 4-4-8-8zM21 10l-3.5 3.5 8 8L29 18"/></svg>',
    'garantie' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 5 8 10v9c0 8 5 13.5 12 16 7-2.5 12-8 12-16v-9L20 5z"/><path d="m15 20 3.5 3.5L26 16"/></svg>',
    'proximite' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="15" cy="13" r="5"/><path d="M6 32c0-5 4-8.5 9-8.5s9 3.5 9 8.5"/><circle cx="28" cy="15" r="4"/><path d="M26 24c4.5 0 8 3 8 8"/></svg>',

    // --- éléments d'interface --------------------------------------------
    'telephone' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 6h-.5A5.5 5.5 0 0 0 7 11.5C7 24 16 33 28.5 33a5.5 5.5 0 0 0 5.5-5.5V27l-7-3-3 4c-4-2-7.5-5.5-9.5-9.5l4-3-5.5-9.5z"/></svg>',
    'adresse' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 35s11-10 11-18a11 11 0 1 0-22 0c0 8 11 18 11 18z"/><circle cx="20" cy="17" r="4"/></svg>',
    'horaires' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="20" cy="20" r="14"/><path d="M20 11v9.5l6 4"/></svg>',
    'courriel' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="9" width="30" height="22" rx="2.5"/><path d="m6 11 14 10 14-10"/></svg>',
    'etoile' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z"/></svg>',

    // --- réseaux sociaux ---------------------------------------------------
    // Marques reprises à l'identique dans leur forme, en aplat monochrome :
    // elles prennent la couleur du texte, donc l'orange de la charte.
    'facebook' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5h1.65V3.6c-.29-.04-1.27-.13-2.42-.13-2.4 0-4.04 1.47-4.04 4.16v2.27H7.5V13h2.74v8h3.26z"/></svg>',
    'instagram' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>',
    'linkedin' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3.1 9.5h3.8V21H3.1V9.5zM10 9.5h3.6v1.6h.05c.5-.95 1.75-1.95 3.6-1.95 3.85 0 4.56 2.5 4.56 5.76V21h-3.8v-5.3c0-1.26-.02-2.9-1.75-2.9-1.75 0-2.02 1.37-2.02 2.8V21H10V9.5z"/></svg>',
];

echo $icones[$nom] ?? '';
