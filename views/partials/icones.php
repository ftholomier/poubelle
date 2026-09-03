<?php
/**
 * Icônes SVG en ligne (tracés maison, aucune bibliothèque externe).
 *
 * Dessinées sur une grille de 40, au trait et sans remplissage : elles
 * prennent la couleur du texte qui les entoure et restent nettes à toute
 * taille. Le trait fin les accorde au registre du site — un picto plein
 * ferait tache à côté d'un titre en Montserrat léger.
 *
 * Un picto d'administration doit se reconnaître avant d'être joli : une urne
 * pour les élections, une carte pour l'état civil, un clocher pour le village.
 * Quand deux idées se disputent le même dessin, c'est le sujet qui gagne, pas
 * l'outil — l'école se dit par un cartable, pas par un stylo.
 *
 * @var string $nom
 */
$icones = [
    // --- la commune -------------------------------------------------------
    // Mairie : le fronton, les colonnes et le drapeau. C'est le seul bâtiment
    // que tout le monde identifie sans légende.
    'mairie' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 16 15-8 15 8"/><path d="M9 16v14M16 16v14M24 16v14M31 16v14"/><path d="M4 34h32M6 30h28"/><path d="M20 8V4M20 4h5v3h-5" opacity=".7"/></svg>',
    // Clocher : la silhouette du village, reprise du logo de la commune.
    'clocher' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 4 14 15v19h12V15L20 4z"/><path d="M20 20a3 3 0 0 1 3 3v11h-6V23a3 3 0 0 1 3-3z"/><path d="M4 34h32"/><path d="M14 34V22H8v12M26 34V22h6v12" opacity=".6"/></svg>',
    // Forêt : le Ballon d'Alsace ferme l'horizon de toutes les photos du
    // village ; la forêt communale est son premier patrimoine.
    'foret' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="m14 5 7 12h-4l5 9H6l5-9H7l7-12z"/><path d="M14 26v8"/><path d="m29 12 6 11h-4l3.5 6h-11l3.5-6h-4l6-11z" opacity=".65"/><path d="M29 29v5" opacity=".65"/><path d="M4 34h32"/></svg>',

    // --- démarches et administration -------------------------------------
    'document' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 34V6a1 1 0 0 1 1-1h13l8 8v21a1 1 0 0 1-1 1H10a1 1 0 0 1-1-1z"/><path d="M23 5v8h8"/><path d="M14 20h12M14 25h12M14 29h7" opacity=".6"/></svg>',
    // État civil : la carte d'identité, sa photo et ses lignes.
    'etat-civil' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="9" width="32" height="22" rx="2.5"/><circle cx="14" cy="17.5" r="3.5"/><path d="M8.5 26c.8-2.6 2.9-4 5.5-4s4.7 1.4 5.5 4"/><path d="M24 16h8M24 21h8M24 26h5" opacity=".6"/></svg>',
    // Élections : l'urne et le bulletin qu'on y glisse.
    'elections' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 18h26v15a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V18z"/><path d="M13 18v-3h14v3"/><path d="M15 13V6h10v7" opacity=".6"/><path d="M17 9h6" opacity=".6"/><path d="M16.5 18h7v3.5h-7z"/></svg>',
    // Urbanisme : le plan et l'équerre — ce qu'on dépose, et ce qui le vérifie.
    'urbanisme' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 32V9a1 1 0 0 1 1-1h20l8 8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1z"/><path d="M26 8v8h8"/><path d="m12 28 6-11 6 11H12z"/><path d="M10 28h20" opacity=".6"/><path d="M12 12h8" opacity=".5"/></svg>',
    // Démarches en ligne : le globe des téléservices de l'État.
    'internet' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="20" cy="20" r="15"/><path d="M5 20h30"/><path d="M20 5c4 4.5 6 9.5 6 15s-2 10.5-6 15c-4-4.5-6-9.5-6-15s2-10.5 6-15z"/></svg>',
    'telecharger' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6v18"/><path d="m12 17 8 8 8-8"/><path d="M7 28v4a2 2 0 0 0 2 2h22a2 2 0 0 0 2-2v-4"/></svg>',

    // --- vie municipale ---------------------------------------------------
    // Conseil : la table du conseil vue de dessus, et les places autour.
    'conseil' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><rect x="10" y="15" width="20" height="10" rx="2"/><circle cx="8" cy="12" r="2.6"/><circle cx="20" cy="9" r="2.6"/><circle cx="32" cy="12" r="2.6"/><circle cx="8" cy="28" r="2.6"/><circle cx="20" cy="31" r="2.6"/><circle cx="32" cy="28" r="2.6"/></svg>',
    // Budget : la pile d'euros et la courbe qui la commente.
    'budget' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="14" cy="11" rx="9" ry="3.5"/><path d="M5 11v7c0 1.9 4 3.5 9 3.5s9-1.6 9-3.5v-7"/><path d="M5 18v7c0 1.9 4 3.5 9 3.5 1.2 0 2.4-.1 3.4-.3"/><path d="M35 20v14M29 25v9M23 29v5" opacity=".7"/></svg>',

    // --- vie scolaire et sociale -----------------------------------------
    // École : le cartable. Le crayon dit l'écriture, le cartable dit l'écolier.
    'ecole' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 16a4 4 0 0 1 4-4h18a4 4 0 0 1 4 4v14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V16z"/><path d="M15 12V9a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v3"/><path d="M7 22h26" opacity=".6"/><path d="M17 22v4h6v-4"/></svg>',
    'restauration' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="20" cy="21" r="10"/><circle cx="20" cy="21" r="5" opacity=".55"/><path d="M6 6v9a3 3 0 0 0 6 0V6M9 15v19" opacity=".8"/><path d="M34 6c-2 0-3.5 3-3.5 7s1.5 5 3.5 5V6zM34 18v16" opacity=".8"/></svg>',
    'transport' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 12a4 4 0 0 1 4-4h18a4 4 0 0 1 4 4v15H7V12z"/><path d="M7 16h26" opacity=".6"/><path d="M12 21h4M24 21h4" opacity=".6"/><circle cx="13" cy="30" r="3"/><circle cx="27" cy="30" r="3"/><path d="M7 27v3M33 27v3"/></svg>',
    // Solidarité : la main qui porte, plutôt que le cœur seul — le CCAS
    // accompagne, il ne compatit pas.
    'solidarite' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 18.5c-2.5-3.5-8-3-8 1.5 0 3.5 5 7 8 9 3-2 8-5.5 8-9 0-4.5-5.5-5-8-1.5z"/><path d="M5 34c0-4 3-7 7-7M35 34c0-4-3-7-7-7" opacity=".6"/><path d="M13 12a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM27 12a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z" opacity=".6"/></svg>',
    'association' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="20" cy="12" r="4.5"/><path d="M12 27c0-4.4 3.6-8 8-8s8 3.6 8 8"/><circle cx="7.5" cy="18" r="3.5" opacity=".6"/><path d="M2 30c0-3.3 2.5-6 5.5-6" opacity=".6"/><circle cx="32.5" cy="18" r="3.5" opacity=".6"/><path d="M38 30c0-3.3-2.5-6-5.5-6" opacity=".6"/></svg>',

    // --- vie pratique -----------------------------------------------------
    'dechets' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h22l-2 21a2 2 0 0 1-2 1.8H13A2 2 0 0 1 11 33L9 12z"/><path d="M6 12h28"/><path d="M16 12V8a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v4"/><path d="M17 19v9M23 19v9" opacity=".6"/></svg>',
    'eau' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 5c5 7 9 11.5 9 16.5A9 9 0 0 1 11 21.5C11 16.5 15 12 20 5z"/><path d="M4 33c3 0 3 2 6 2s3-2 6-2 3 2 6 2 3-2 6-2 3 2 6 2" opacity=".6"/></svg>',
    'agenda' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="9" width="30" height="26" rx="2.5"/><path d="M5 17h30"/><path d="M13 5v7M27 5v7"/><path d="M12 23h4M18 23h4M24 23h4M12 29h4M18 29h4" opacity=".6"/></svg>',
    /* Cloche pleine, dessinée pour vingt pixels et pas réduite depuis
       quarante. Les pictogrammes de ce fichier sont des traits de 1,4 sur une
       grille de 40 : parfaits dans une carte de 34 px, illisibles dans la
       barre — à cette taille le trait tombe sous le pixel et l'œil ne voit
       plus qu'une tache. Celui-ci est en aplat, sur une grille de 24, avec des
       formes assez grosses pour survivre. C'est aussi la cloche que tout le
       monde lit comme « il y a du nouveau », ce qui va de pair avec la
       pastille de compte. */
    'cloche' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.6a1.3 1.3 0 0 1 1.3 1.3v.5a6 6 0 0 1 4.7 5.85v3.05l1.4 2.6a.9.9 0 0 1-.79 1.33H5.39a.9.9 0 0 1-.79-1.33l1.4-2.6V10.25A6 6 0 0 1 10.7 4.4v-.5A1.3 1.3 0 0 1 12 2.6z"/><path d="M9.6 19.1h4.8a2.4 2.4 0 0 1-4.8 0z"/></svg>',
    'actualite' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 10h22v22a2 2 0 0 0 2 2H8a2 2 0 0 1-2-2V10z"/><path d="M28 16h6v16a2 2 0 0 1-2 2" opacity=".7"/><path d="M11 15h8v7h-8z"/><path d="M23 15h1M11 26h12M11 30h8" opacity=".6"/></svg>',

    // --- éléments d'interface --------------------------------------------
    'telephone' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 6h-.5A5.5 5.5 0 0 0 7 11.5C7 24 16 33 28.5 33a5.5 5.5 0 0 0 5.5-5.5V27l-7-3-3 4c-4-2-7.5-5.5-9.5-9.5l4-3-5.5-9.5z"/></svg>',
    'adresse' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 35s11-10 11-18a11 11 0 1 0-22 0c0 8 11 18 11 18z"/><circle cx="20" cy="17" r="4"/></svg>',
    'horaires' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="20" cy="20" r="14"/><path d="M20 11v9.5l6 4"/></svg>',
    'courriel' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="9" width="30" height="22" rx="2.5"/><path d="m6 11 14 10 14-10"/></svg>',
    'information' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="20" cy="20" r="15"/><path d="M20 18v10"/><path d="M20 12.5h.02"/></svg>',
    'alerte' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 5.5 36 33H4L20 5.5z"/><path d="M20 16v8"/><path d="M20 28.5h.02"/></svg>',
    'urgence' => '<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 34S6 25 6 15.5A7.5 7.5 0 0 1 20 12a7.5 7.5 0 0 1 14 3.5C34 25 20 34 20 34z"/><path d="M11 20h5l2-4 3 8 2-4h6" opacity=".85"/></svg>',
    'lien-externe' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>',
    'etoile' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z"/></svg>',

    // --- réseaux sociaux ---------------------------------------------------
    // Marques reprises à l'identique dans leur forme, en aplat monochrome :
    // elles prennent la couleur du texte, donc le vert de la charte.
    'facebook' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5h1.65V3.6c-.29-.04-1.27-.13-2.42-.13-2.4 0-4.04 1.47-4.04 4.16v2.27H7.5V13h2.74v8h3.26z"/></svg>',
    'instagram' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>',
    'linkedin' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3.1 9.5h3.8V21H3.1V9.5zM10 9.5h3.6v1.6h.05c.5-.95 1.75-1.95 3.6-1.95 3.85 0 4.56 2.5 4.56 5.76V21h-3.8v-5.3c0-1.26-.02-2.9-1.75-2.9-1.75 0-2.02 1.37-2.02 2.8V21H10V9.5z"/></svg>',
];

echo $icones[$nom] ?? $icones['document'];
