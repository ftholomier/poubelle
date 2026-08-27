<?php
/**
 * Icônes SVG en ligne (tracés maison, aucune bibliothèque externe).
 *
 * Dessinées sur une grille de 40, au trait et sans remplissage : elles
 * prennent la couleur du texte qui les entoure et restent nettes à toute
 * taille. Le trait fin les accorde au registre du site — un picto plein
 * ferait tache à côté d'un titre en Montserrat léger.
 *
 * @var string $nom
 */
$icones = [
    // --- prestations ------------------------------------------------------
    'conception' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 32V10a2 2 0 0 1 2-2h18l6 6v18a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2z"/><path d="M25 8v6h6"/><path d="M10 20h9M10 25h13M10 29h7" opacity=".6"/><path d="M23.5 21.5c3 0 5 2 5 4.5s-2 4.5-5 4.5" opacity=".6"/></svg>',
    // Aménagement : deux niveaux de terrasse et une plantation. Une simple
    // butte plantée ne disait pas la maçonnerie paysagère, qui est
    // pourtant la moitié du métier.
    'amenagement' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 33h32"/><path d="M6 33v-6h10v6M16 27v-6h10v6"/><path d="M30 33V20"/><path d="M30 23c0-3.6 2.7-6.4 6.2-6.4-.1 3.5-2.8 6.4-6.2 6.4z"/><path d="M30 27.5c0-3.4-2.6-6-6-6 .1 3.3 2.7 6 6 6z" opacity=".6"/></svg>',
    // Entretien : un sécateur. La tondeuse dessinée au trait ne se lisait
    // pas à 24 px, les lames croisées oui — c'est la forme des ciseaux, que
    // tout le monde reconnaît.
    'entretien' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M26.5 6 14.8 25.6M13.5 6l11.7 19.6"/><circle cx="11.8" cy="29.2" r="4.3"/><circle cx="28.2" cy="29.2" r="4.3"/><circle cx="20" cy="19" r="1.3"/></svg>',
    // Élagage : un arbre, tout simplement. Le sécateur est déjà pris par
    // l'entretien ; ce qui distingue l'élagage, c'est le sujet, pas l'outil.
    'elagage' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 34V22"/><path d="M20 26c6.6 0 12-4.7 12-10.5S26.6 5 20 5 8 9.7 8 15.5 13.4 26 20 26z"/><path d="m20 26-5-4.5M20 21.5l4.5-4" opacity=".6"/><path d="M15 34h10"/></svg>',

    // --- valeurs ----------------------------------------------------------
    // Créativité : une feuille dont la nervure devient une plume.
    'creativite' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M8 32c0-13 8.5-22 24-22 0 15-9 22-19 22-2.6 0-5 0-5 0z"/><path d="M32 10 12 30" opacity=".7"/><path d="M22 12c-1.5 4.5-1.5 8.5 0 12M28 17c-4 1-7 2.5-9.5 5" opacity=".45"/></svg>',
    // Écoute : une oreille, et les ondes qui lui parviennent. Un dessin
    // d'oreille se reconnaît à 24 px, une bulle de dialogue ne dit rien de
    // l'écoute — elle dit la parole.
    'ecoute' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16a8 8 0 1 1 16 0c0 4-3 5.5-4.8 7.2-1.4 1.3-1.7 2.6-1.9 4.3-.2 1.9-1.4 3.3-3.3 3.3A3.4 3.4 0 0 1 14.6 27"/><path d="M17.5 16.5a3.2 3.2 0 0 1 6 1.3c0 1.6-1.2 2.4-2.2 3.2"/><path d="M32 12.5a13 13 0 0 1 0 15" opacity=".5"/><path d="M35.5 9a18 18 0 0 1 0 22" opacity=".28"/></svg>',
    // Exigence : un cordeau et son plomb — l'outil qui dit si c'est droit,
    // et le seul juge que le métier reconnaisse.
    'exigence' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7h24"/><path d="M20 7v14"/><path d="m20 21 5 4.5c0 4.5-2.2 8.5-5 10.5-2.8-2-5-6-5-10.5L20 21z"/><path d="M12 7v3M28 7v3" opacity=".5"/></svg>',

    // --- profils de clientèle ---------------------------------------------
    'maison' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 19 15-11 15 11"/><path d="M9 17v16h22V17"/><path d="M16 33v-9h8v9"/></svg>',
    'immeuble' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 34V9a1 1 0 0 1 1-1h13a1 1 0 0 1 1 1v25"/><path d="M21 34V17h12a1 1 0 0 1 1 1v16"/><path d="M10 14h3M10 20h3M10 26h3M26 22h3M26 28h3" opacity=".6"/><path d="M4 34h32"/></svg>',
    'collectivite' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 16 15-8 15 8"/><path d="M8 16v14M16 16v14M24 16v14M32 16v14"/><path d="M4 34h32M6 30h28"/></svg>',
    'piscine' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 26c3.2 0 3.2 2.5 6.4 2.5S13.6 26 16.8 26s3.2 2.5 6.4 2.5S26.4 26 29.6 26s3.2 2.5 6.4 2.5"/><path d="M4 32c3.2 0 3.2 2.5 6.4 2.5S13.6 32 16.8 32s3.2 2.5 6.4 2.5S26.4 32 29.6 32s3.2 2.5 6.4 2.5" opacity=".55"/><path d="M13 27V10a3 3 0 0 1 6 0M25 27V10a3 3 0 0 1 6 0"/><path d="M13 16h6M25 16h6" opacity=".6"/></svg>',

    // --- éléments d\'interface --------------------------------------------
    'telephone' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 6h-.5A5.5 5.5 0 0 0 7 11.5C7 24 16 33 28.5 33a5.5 5.5 0 0 0 5.5-5.5V27l-7-3-3 4c-4-2-7.5-5.5-9.5-9.5l4-3-5.5-9.5z"/></svg>',
    'adresse' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 35s11-10 11-18a11 11 0 1 0-22 0c0 8 11 18 11 18z"/><circle cx="20" cy="17" r="4"/></svg>',
    'horaires' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="20" cy="20" r="14"/><path d="M20 11v9.5l6 4"/></svg>',
    'courriel' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="9" width="30" height="22" rx="2.5"/><path d="m6 11 14 10 14-10"/></svg>',
    'etoile' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z"/></svg>',
    'feuille' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M8 32c0-13 8.5-22 24-22 0 15-9 22-19 22-2.6 0-5 0-5 0z"/><path d="M32 10 12 30" opacity=".7"/></svg>',

    // --- réseaux sociaux ---------------------------------------------------
    // Marques reprises à l'identique dans leur forme, en aplat monochrome :
    // elles prennent la couleur du texte, donc le vert de la charte.
    'facebook' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5h1.65V3.6c-.29-.04-1.27-.13-2.42-.13-2.4 0-4.04 1.47-4.04 4.16v2.27H7.5V13h2.74v8h3.26z"/></svg>',
    'instagram' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>',
    'linkedin' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3.1 9.5h3.8V21H3.1V9.5zM10 9.5h3.6v1.6h.05c.5-.95 1.75-1.95 3.6-1.95 3.85 0 4.56 2.5 4.56 5.76V21h-3.8v-5.3c0-1.26-.02-2.9-1.75-2.9-1.75 0-2.02 1.37-2.02 2.8V21H10V9.5z"/></svg>',
];

echo $icones[$nom] ?? '';
