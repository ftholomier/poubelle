<?php
// Rendu HTML de la page publique d'un bien (public/v/) : photos, prix et mentions légales, atouts, description,
// étiquettes énergie, croquis de plan, demande de visite avec créneaux libres, assistant en ligne.

const COULEURS_DPE = ['A' => '#009c6d', 'B' => '#52b153', 'C' => '#78bd76', 'D' => '#f4e70f', 'E' => '#f0b50f', 'F' => '#eb8235', 'G' => '#d7221f'];
const COULEURS_GES = ['A' => '#f6edfd', 'B' => '#e4c7fb', 'C' => '#d5aaf6', 'D' => '#cb95f3', 'E' => '#ba72ec', 'F' => '#a94deb', 'G' => '#8a19df'];

function echelle_html(string $lettre, array $couleurs, string $titre): string
{
    if (!isset($couleurs[$lettre])) return '';
    $h = '<div class="vt-echelle"><span class="vt-mono">' . e($titre) . '</span>';
    $i = 0;
    foreach ($couleurs as $l => $c) {
        $clair = $couleurs === COULEURS_GES ? $i < 4 : in_array($l, ['D', 'E'], true);
        $h .= '<div class="vt-barre' . ($l === $lettre ? ' on' : '') . '" style="background:' . $c . ';width:' . (34 + $i * 9) . '%;color:' . ($clair ? '#2b1745' : '#fff') . '">' . $l . '</div>';
        $i++;
    }
    return $h . '</div>';
}

function page_vitrine(array $agent, array $v, string $slug): string
{
    global $CONFIG;
    $prix = mention_prix($v);
    $infos = infos_publiques($v);
    $titre = $v['titre_annonce'] ?: titre_bien($v);
    $ville = champ($v, 'ville') ?: ($v['public']['geo']['city'] ?? '');
    $photos = '';
    foreach ($v['photos'] ?? [] as $i => $p) {
        $photos .= '<figure><img src="?b=' . e($slug) . '&photo=' . $i . '" alt="' . e($p['piece']) . '" loading="' . ($i ? 'lazy' : 'eager') . '"><figcaption>' . e($p['piece']) . (!empty($p['staging']) ? ' · aménagement virtuel' : '') . '</figcaption></figure>';
    }
    if ($photos === '') $photos = '<figure class="vt-sans-photo"><span>' . e(champ($v, 'type_bien') ?: 'Bien') . '</span></figure>';
    $chiffres = array_filter([
        champ($v, 'surface_habitable') ? [champ($v, 'surface_habitable') . ' m²', 'habitables'] : null,
        champ($v, 'nb_pieces') ? [champ($v, 'nb_pieces'), 'pièces'] : null,
        champ($v, 'nb_chambres') ? [champ($v, 'nb_chambres'), 'chambres'] : null,
        champ($v, 'surface_terrain') ? [number_format((float) champ($v, 'surface_terrain'), 0, ',', ' ') . ' m²', 'terrain'] : null,
    ]);
    $liste = '';
    foreach ($infos as $k => $x) if (!in_array($k, ['Ville', 'Prix souhaité par le vendeur'], true)) $liste .= '<div class="vt-info"><span>' . e($k) . '</span><strong>' . e($x) . '</strong></div>';
    $copro = champ($v, 'copropriete') === 'oui';
    $legales = array_filter([
        $prix['texte'],
        $copro ? 'Bien en copropriété' . (champ($v, 'charges_mensuelles') ? ' ; charges courantes annuelles moyennes : ' . number_format((float) champ($v, 'charges_mensuelles') * 12, 0, ',', ' ') . ' €' : '') . '. Aucune procédure en cours connue.' : '',
        champ($v, 'dpe') ? 'Classe énergie ' . champ($v, 'dpe') . (champ($v, 'ges') ? ', classe climat ' . champ($v, 'ges') : '') . '.' : 'Diagnostics en cours de réalisation.',
        'Les informations sur les risques auxquels ce bien est exposé sont disponibles sur le site Géorisques : www.georisques.gouv.fr',
        !empty($v['mandat']['numero']) ? 'Mandat n° ' . $v['mandat']['numero'] . ' · ' . (($CONFIG['raison_sociale'] ?? '') ?: $CONFIG['agence']) . (($CONFIG['carte_numero'] ?? '') ? ', carte professionnelle ' . $CONFIG['carte_numero'] : '') : '',
    ]);
    $creneaux = creneaux_libres($agent, 10, 6);
    $plan = plan_svg($v);
    $titrePage = e($titre) . ' · ' . e((string) $CONFIG['agence']);
    $desc = e(mb_substr(preg_replace('/\s+/', ' ', (string) $v['annonce']), 0, 160));
    $og = !empty($v['photos']) ? '<meta property="og:image" content="' . e(url_publique('v/?b=' . $slug . '&photo=0')) . '">' : '';
    $logo = uploaded_logo_path() ? '../api/?r=logo' : '../img/synapse-logo.svg';
    $ver = substr(md5((string) @filemtime(APP_ROOT . '/public/css/vitrine.css') . @filemtime(APP_ROOT . '/public/js/vitrine.js')), 0, 8);
    $tel = $agent['telephone'] ?? '';

    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . "<title>$titrePage</title><meta name=\"description\" content=\"$desc\"><meta property=\"og:title\" content=\"" . e($titre) . "\"><meta property=\"og:description\" content=\"$desc\">$og"
        . '<link rel="icon" href="../icon.svg"><link rel="stylesheet" href="../css/espace.css?v=' . $ver . '"><link rel="stylesheet" href="../css/vitrine.css?v=' . $ver . '"></head><body>'
        . '<header class="vt-entete"><img src="' . $logo . '" alt="' . e((string) $CONFIG['agence']) . '">' . ($tel ? '<a class="vt-appel" href="tel:' . e(preg_replace('/\s/', '', $tel)) . '">📞 Appeler</a>' : '') . '</header>'
        . '<div class="vt-galerie">' . $photos . '</div>'
        . '<main class="es-page vt-page">'
        . '<span class="es-tag">À vendre' . ($ville ? ' · ' . e($ville) : '') . '</span>'
        . '<h1 class="vt-titre">' . e($titre) . '</h1>'
        . ($prix['prix'] ? '<p class="vt-prix">' . number_format($prix['prix'], 0, ',', ' ') . ' €</p><p class="vt-mention">' . e($prix['texte']) . '</p>' : '')
        . ($chiffres ? '<div class="vt-chiffres">' . implode('', array_map(fn ($c) => '<div><strong>' . e($c[0]) . '</strong><span>' . e($c[1]) . '</span></div>', $chiffres)) . '</div>' : '')
        . (!empty($v['points_forts']) ? '<section class="es-card"><h2>Les atouts</h2><ul class="vt-atouts">' . implode('', array_map(fn ($p) => '<li>' . e($p) . '</li>', $v['points_forts'])) . '</ul></section>' : '')
        . '<section class="es-card"><h2>Description</h2><div class="vt-texte">' . nl2br(e((string) $v['annonce'])) . '</div></section>'
        . '<section class="es-card vt-visite" id="visite"><h2>Visiter ce bien</h2><p class="es-aide">Choisissez un créneau, votre conseiller confirme par e-mail.</p>'
        . '<div class="vt-creneaux">' . implode('', array_map(fn ($c) => '<button type="button" class="vt-creneau" data-creneau="' . e($c) . '">' . e(ucfirst(libelle_creneau($c))) . '</button>', $creneaux)) . '</div>'
        . '<form id="vt-form"><label>Nom<input name="nom" required autocomplete="name"></label><label>Téléphone<input name="telephone" type="tel" autocomplete="tel"></label><label>E-mail<input name="email" type="email" autocomplete="email"></label>'
        . '<label>Message (facultatif)<textarea name="message" rows="2"></textarea></label><input type="hidden" name="creneau"><button class="es-btn es-primaire">Envoyer ma demande</button><p class="es-msg" id="vt-msg"></p></form></section>'
        . '<section class="es-card"><h2>Caractéristiques</h2>' . $liste . '</section>'
        . ((champ($v, 'dpe') || champ($v, 'ges')) ? '<section class="es-card"><h2>Performance énergétique</h2>' . echelle_html(champ($v, 'dpe'), COULEURS_DPE, 'Énergie') . echelle_html(champ($v, 'ges'), COULEURS_GES, 'Climat (GES)') . '</section>' : '')
        . ($plan ? '<section class="es-card"><h2>Distribution des pièces</h2>' . $plan . '<p class="es-aide">Croquis indicatif, non coté.</p></section>' : '')
        . '<section class="es-card es-noir"><span class="es-mono">Votre conseiller</span><strong>' . e($agent['nom']) . ' · ' . e((string) $CONFIG['agence']) . '</strong><p>'
        . implode(' · ', array_filter([$tel ? '<a href="tel:' . e(preg_replace('/\s/', '', $tel)) . '">' . e($tel) . '</a>' : '', ($agent['email'] ?? '') ? '<a href="mailto:' . e($agent['email']) . '">' . e($agent['email']) . '</a>' : ''])) . '</p></section>'
        . '<section class="vt-legal">' . implode('', array_map(fn ($l) => '<p>' . e($l) . '</p>', $legales)) . '</section>'
        . '</main>'
        . '<button class="vt-chat-bouton" id="vt-chat-ouvrir">💬 Une question ?</button>'
        . '<div class="vt-chat" id="vt-chat" hidden><div class="vt-chat-tete"><strong>Assistant ' . e((string) $CONFIG['agence']) . '</strong><span>répond 24 h/24</span><button id="vt-chat-fermer" aria-label="Fermer">✕</button></div>'
        . '<div class="vt-fil" id="vt-fil"><div class="vt-bulle ia">Bonjour ! Je réponds à vos questions sur ce bien et je peux réserver une visite. Que souhaitez-vous savoir ?</div></div>'
        . '<form class="vt-saisie" id="vt-saisie"><input id="vt-message" placeholder="Votre question…" autocomplete="off" maxlength="800"><button aria-label="Envoyer">➤</button></form></div>'
        . '<script src="../js/vitrine.js?v=' . $ver . '" defer></script></body></html>';
}
