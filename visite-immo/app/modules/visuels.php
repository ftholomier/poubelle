<?php
// Visuels pour les réseaux sociaux, générés aux couleurs de Synapse à partir de la meilleure photo :
// carré (1080 × 1080, publication) et story (1080 × 1920). Étiquette « À vendre », titre surligné citron, prix.

const POLICE_TITRE = APP_ROOT . '/app/lib/tfpdf/font/unifont/Archivo-ExtraBold.ttf';
const POLICE_MONO = APP_ROOT . '/app/lib/tfpdf/font/unifont/JetBrainsMono-Medium.ttf';

function couleur($img, array $rgb, int $alpha = 0): int
{
    return imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], $alpha);
}

/** Texte découpé en lignes qui tiennent dans la largeur. */
function lignes_texte(string $texte, string $police, float $taille, int $largeur): array
{
    $lignes = [];
    $cour = '';
    foreach (preg_split('/\s+/u', trim($texte)) as $mot) {
        $essai = $cour === '' ? $mot : "$cour $mot";
        $b = imagettfbbox($taille, 0, $police, $essai);
        if ($cour !== '' && $b[2] - $b[0] > $largeur) { $lignes[] = $cour; $cour = $mot; } else $cour = $essai;
    }
    if ($cour !== '') $lignes[] = $cour;
    return $lignes;
}

/** Photo recadrée pour remplir exactement le rectangle (comme object-fit: cover). */
function couvrir($dest, $src, int $x, int $y, int $w, int $h): void
{
    $sw = imagesx($src); $sh = imagesy($src);
    $r = max($w / $sw, $h / $sh);
    $cw = (int) ($w / $r); $ch = (int) ($h / $r);
    imagecopyresampled($dest, $src, $x, $y, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), $w, $h, $cw, $ch);
}

function generer_visuel(array $agent, array $v, string $format): string
{
    global $CONFIG;
    [$W, $H] = $format === 'story' ? [1080, 1920] : [1080, 1080];
    $img = imagecreatetruecolor($W, $H);
    imagealphablending($img, true);
    imagefill($img, 0, 0, couleur($img, C_CREME));
    $photoH = $format === 'story' ? 1000 : 540;
    $photo = null;
    foreach ($v['photos'] ?? [] as $p) if (empty($p['floue'])) { $photo = $p; break; }
    if ($photo) {
        $src = charger_image(chemin_photo($agent, $v['id'], $photo['staging']['fichier'] ?? $photo['fichier']));
        if ($src) couvrir($img, $src, 0, 0, $W, $photoH);
    } else {
        imagefilledrectangle($img, 0, 0, $W, $photoH, couleur($img, C_CITRON));
        $t = mb_strtoupper(champ($v, 'type_bien') ?: 'À vendre');
        $b = imagettfbbox(120, 0, POLICE_TITRE, $t);
        imagettftext($img, 120, 0, (int) (($W - ($b[2] - $b[0])) / 2), (int) ($photoH / 2 + 50), couleur($img, C_ENCRE), POLICE_TITRE, $t);
    }
    imagesetthickness($img, 6);
    imageline($img, 0, $photoH, $W, $photoH, couleur($img, C_ENCRE));

    // Étiquette orange inclinée « À vendre » (dessinée sur un calque puis tournée)
    $tag = mb_strtoupper('À vendre' . (champ($v, 'ville') ? ' · ' . preg_replace('/^\d{5}\s*/', '', champ($v, 'ville')) : ''));
    $tb = imagettfbbox(30, 0, POLICE_MONO, $tag);
    $tw = $tb[2] - $tb[0] + 56;
    $calque = imagecreatetruecolor($tw, 78);
    imagesavealpha($calque, true);
    imagefill($calque, 0, 0, imagecolorallocatealpha($calque, 0, 0, 0, 127));
    imagefilledrectangle($calque, 0, 0, $tw, 78, couleur($calque, C_ORANGE));
    imagettftext($calque, 30, 0, 28, 52, couleur($calque, C_ENCRE), POLICE_MONO, $tag);
    $tourne = imagerotate($calque, 2, imagecolorallocatealpha($calque, 0, 0, 0, 127));
    imagecopy($img, $tourne, 56, $photoH - 50, 0, 0, imagesx($tourne), imagesy($tourne));

    // Titre : dernière ligne surlignée citron
    $titre = $v['titre_annonce'] ?: titre_bien($v);
    $taille = $format === 'story' ? 66 : 58;
    $maxLignes = $format === 'story' ? 3 : 2;
    $lignes = lignes_texte($titre, POLICE_TITRE, $taille, $W - 140);
    while (count($lignes) > $maxLignes && $taille > 34) $lignes = lignes_texte($titre, POLICE_TITRE, $taille -= 4, $W - 140);
    $y = $photoH + 90 + $taille;
    foreach ($lignes as $i => $l) {
        if ($i === count($lignes) - 1) {
            $b = imagettfbbox($taille, 0, POLICE_TITRE, $l);
            imagefilledrectangle($img, 60, $y - $taille - 6, 76 + $b[2] - $b[0], $y + 14, couleur($img, C_CITRON));
        }
        imagettftext($img, $taille, 0, 68, $y, couleur($img, C_ENCRE), POLICE_TITRE, $l);
        $y += (int) ($taille * 1.22);
    }

    // Chiffres clés et prix
    $infos = implode('  ·  ', array_filter([champ($v, 'surface_habitable') ? champ($v, 'surface_habitable') . ' m²' : '', champ($v, 'nb_chambres') ? champ($v, 'nb_chambres') . ' chambres' : '', champ($v, 'surface_terrain') ? 'terrain ' . champ($v, 'surface_terrain') . ' m²' : '', champ($v, 'dpe') ? 'DPE ' . champ($v, 'dpe') : '']));
    $infos = str_replace('  ·  ', ' · ', $infos);
    if ($y + 30 < $H - 180) imagettftext($img, 24, 0, 68, $y + 24, couleur($img, [59, 59, 64]), POLICE_MONO, mb_strtoupper($infos));
    if ($format === 'story') {
        foreach (array_slice($v['points_forts'] ?? [], 0, 4) as $i => $p) {
            if ($y + 140 + $i * 70 > $H - 190) break; // pas de chevauchement avec le bandeau du prix
            imagefilledrectangle($img, 68, $y + 104 + $i * 70, 92, $y + 128 + $i * 70, couleur($img, C_CITRON));
            imagerectangle($img, 68, $y + 104 + $i * 70, 92, $y + 128 + $i * 70, couleur($img, C_ENCRE));
            imagettftext($img, 36, 0, 116, $y + 130 + $i * 70, couleur($img, C_ENCRE), POLICE_TITRE, $p);
        }
    }
    $prix = mention_prix($v)['prix'];
    $bandeau = $H - 150;
    imagefilledrectangle($img, 0, $bandeau, $W, $H, couleur($img, C_ENCRE));
    if ($prix) imagettftext($img, 64, 0, 64, $bandeau + 102, couleur($img, C_CREME), POLICE_TITRE, number_format($prix, 0, ',', ' ') . ' €');
    $ag = mb_strtoupper((string) $CONFIG['agence']);
    $b = imagettfbbox(28, 0, POLICE_MONO, $ag);
    imagettftext($img, 28, 0, $W - 64 - ($b[2] - $b[0]), $bandeau + 92, couleur($img, C_CITRON), POLICE_MONO, $ag);
    if (!empty($photo['staging'])) mention_virtuelle($img);

    ob_start();
    imagejpeg($img, null, 88);
    return (string) ob_get_clean();
}

route('GET visuel', function ($id) {
    $me = require_user();
    $v = load_visit($me, $id);
    $format = ($_GET['format'] ?? '') === 'story' ? 'story' : 'carre';
    $jpeg = generer_visuel($me, $v, $format);
    header('Content-Type: image/jpeg');
    header('Content-Disposition: ' . (!empty($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . slug(titre_bien($v), 40) . "-$format.jpg\"");
    header('Cache-Control: private, no-cache');
    echo $jpeg;
    exit;
});
