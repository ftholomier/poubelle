<?php
// Carte imprimable des ventes autour du bien, pour le rapport d'estimation envoyé au vendeur (PDF).
// Fond Plan IGN (Géoplateforme, tuiles gardées 30 jours en cache), le bien en citron, les ventes retenues numérotées
// (même numéro que dans le tableau) avec leur prix, les autres ventes en gris. Sans réseau : fond neutre quadrillé.

const URL_IGN = 'https://data.geopf.fr';
const CARTE_L = 1100;  // taille de la carte en pixels de tuile ; l'image est dessinée au double pour l'impression
const CARTE_H = 680;

/** Coordonnées « Web Mercator » en pixels au niveau de zoom $z. */
function mercator(float $lat, float $lon, int $z): array
{
    $n = 256 * 2 ** $z;
    $r = deg2rad(max(-85, min(85, $lat)));
    return [($lon + 180) / 360 * $n, (1 - log(tan($r) + 1 / cos($r)) / M_PI) / 2 * $n];
}

/** Tuiles Plan IGN (en parallèle, avec cache). Renvoie [clé "x/y" => image GD] ; tuile manquante : absente. */
function tuiles_ign(int $z, array $liste): array
{
    $dir = DATA_DIR . "/cache/tuiles/$z";
    $images = $manquantes = [];
    foreach ($liste as [$x, $y]) {
        $f = "$dir/{$x}_{$y}.png";
        if (is_file($f) && filemtime($f) > time() - 30 * 86400 && ($im = @imagecreatefromstring((string) file_get_contents($f)))) $images["$x/$y"] = $im;
        else $manquantes[] = [$x, $y];
    }
    if (!$manquantes) return $images;
    $base = api_base('ign', URL_IGN);
    $multi = curl_multi_init();
    $poignees = [];
    foreach ($manquantes as [$x, $y]) {
        $ch = curl_init("$base/wmts?" . http_build_query(['SERVICE' => 'WMTS', 'REQUEST' => 'GetTile', 'VERSION' => '1.0.0', 'LAYER' => 'GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2',
            'STYLE' => 'normal', 'TILEMATRIXSET' => 'PM', 'FORMAT' => 'image/png', 'TILEMATRIX' => $z, 'TILECOL' => $x, 'TILEROW' => $y]));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8, CURLOPT_USERAGENT => 'VisiteImmo-Synapse/1.0']);
        curl_multi_add_handle($multi, $ch);
        $poignees["$x/$y"] = $ch;
    }
    do {
        $statut = curl_multi_exec($multi, $actifs);
        if ($actifs) curl_multi_select($multi, 1);
    } while ($actifs && $statut === CURLM_OK);
    if (!is_dir($dir)) @mkdir($dir, 0770, true);
    foreach ($poignees as $cle => $ch) {
        $png = (string) curl_multi_getcontent($ch);
        $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && $png !== '' && ($im = @imagecreatefromstring($png));
        if ($ok) {
            $images[$cle] = $im;
            @file_put_contents("$dir/" . str_replace('/', '_', $cle) . '.png', $png);
        }
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }
    curl_multi_close($multi);
    return $images;
}

/** Échelle « propre » (100 m, 200 m, 500 m, 1 km…) pour une largeur visée en mètres. */
function echelle_ronde(float $metres): int
{
    foreach ([50, 100, 200, 250, 500, 1000, 2000, 2500, 5000, 10000, 20000] as $e) if ($e >= $metres) return $e;
    return 50000;
}

/**
 * Dessine la carte et renvoie le chemin d'un PNG temporaire (à supprimer après usage), ou null sans coordonnées.
 * $bien : ['lat', 'lon'] ; $comparables : ventes retenues dans l'ordre du tableau ; $autres : autres ventes du secteur.
 */
function carte_ventes_png(array $bien, int $prix, array $comparables, array $autres = []): ?array
{
    if (!function_exists('imagecreatetruecolor') || empty($bien['lat'])) return null;
    $points = [[(float) $bien['lat'], (float) $bien['lon']]];
    foreach ($comparables as $c) if (!empty($c['lat'])) $points[] = [(float) $c['lat'], (float) $c['lon']];

    // Zoom : le plus fort qui fait tenir le bien et toutes les ventes retenues, avec une marge
    $marge = 90;
    for ($z = 17; $z >= 9; $z--) {
        $px = array_map(fn ($p) => mercator($p[0], $p[1], $z), $points);
        $xs = array_column($px, 0);
        $ys = array_column($px, 1);
        if (max($xs) - min($xs) <= CARTE_L - 2 * $marge && max($ys) - min($ys) <= CARTE_H - 2 * $marge) break;
    }
    $cx = (max($xs) + min($xs)) / 2;
    $cy = (max($ys) + min($ys)) / 2;
    $x0 = $cx - CARTE_L / 2;
    $y0 = $cy - CARTE_H / 2;

    // Fond : tuiles IGN (1:1) puis agrandies ×2 pour des repères nets à l'impression
    $fond = imagecreatetruecolor(CARTE_L, CARTE_H);
    imagefill($fond, 0, 0, imagecolorallocate($fond, 244, 241, 234));
    $liste = [];
    for ($tx = (int) floor($x0 / 256); $tx <= (int) floor(($x0 + CARTE_L) / 256); $tx++) {
        for ($ty = (int) floor($y0 / 256); $ty <= (int) floor(($y0 + CARTE_H) / 256); $ty++) $liste[] = [$tx, $ty];
    }
    $tuiles = tuiles_ign($z, $liste);
    foreach ($tuiles as $cle => $t) {
        [$tx, $ty] = array_map('intval', explode('/', $cle));
        imagecopy($fond, $t, (int) round($tx * 256 - $x0), (int) round($ty * 256 - $y0), 0, 0, imagesx($t), imagesy($t));
        imagedestroy($t);
    }
    $avecFond = count($tuiles) >= count($liste) / 2;
    $S = 2;
    $im = imagecreatetruecolor(CARTE_L * $S, CARTE_H * $S);
    imagecopyresampled($im, $fond, 0, 0, 0, 0, CARTE_L * $S, CARTE_H * $S, CARTE_L, CARTE_H);
    imagedestroy($fond);
    $col = fn (array $rgb) => imagecolorallocate($im, ...$rgb);
    if (!$avecFond) {
        // Pas de fond de carte (hors ligne) : quadrillage discret, les distances restent justes
        $g = $col([226, 221, 210]);
        for ($i = 0; $i < CARTE_L * $S; $i += 100) imageline($im, $i, 0, $i, CARTE_H * $S, $g);
        for ($i = 0; $i < CARTE_H * $S; $i += 100) imageline($im, 0, $i, CARTE_L * $S, $i, $g);
    }
    $pos = function (float $lat, float $lon) use ($z, $x0, $y0, $S) {
        [$x, $y] = mercator($lat, $lon, $z);
        return [($x - $x0) * $S, ($y - $y0) * $S];
    };
    $polices = dirname(__DIR__, 2) . '/public/fonts';
    $mono = "$polices/JetBrainsMono-Medium.ttf";
    $gras = "$polices/Archivo-ExtraBold.ttf";
    $encre = $col(C_ENCRE);
    $blanc = $col([255, 255, 255]);
    $kEuros = fn (float $p) => $p >= 1e6 ? number_format($p / 1e6, 2, ',', ' ') . ' M€' : round($p / 1000) . ' k€';

    $occupes = []; // rectangles déjà dessinés (repères et étiquettes), pour éviter les chevauchements
    $libre = function (array $r) use (&$occupes) {
        foreach ($occupes as $o) if ($r[0] < $o[2] && $r[2] > $o[0] && $r[1] < $o[3] && $r[3] > $o[1]) return false;
        return $r[0] >= 0 && $r[1] >= 0 && $r[2] <= CARTE_L * 2 && $r[3] <= CARTE_H * 2;
    };
    $cartouche = function (float $x, float $y, string $texte, array $fondRgb, array $texteRgb, string $police, float $taille) use ($im, $col, $encre) {
        $b = imagettfbbox($taille, 0, $police, $texte);
        $l = $b[2] - $b[0] + 22;
        $h = $taille * 1.9;
        $f = $col($fondRgb);
        imagefilledrectangle($im, (int) ($x - 2), (int) ($y - 2), (int) ($x + $l + 2), (int) ($y + $h + 2), $encre);
        imagefilledrectangle($im, (int) $x, (int) $y, (int) ($x + $l), (int) ($y + $h), $f);
        imagettftext($im, $taille, 0, (int) ($x + 11), (int) ($y + $h * 0.72), $col($texteRgb), $police, $texte);
        return [$x - 2, $y - 2, $x + $l + 2, $y + $h + 2];
    };
    $taille = fn (string $texte, string $police, float $t) => [imagettfbbox($t, 0, $police, $texte)[2] - imagettfbbox($t, 0, $police, $texte)[0] + 26, $t * 1.9 + 4];

    // Autres ventes du secteur : points gris
    $gris = $col([138, 135, 127]);
    foreach ($autres as $a) {
        if (empty($a['lat'])) continue;
        [$x, $y] = $pos((float) $a['lat'], (float) $a['lon']);
        if ($x < 0 || $y < 0 || $x > CARTE_L * $S || $y > CARTE_H * $S) continue;
        imagefilledellipse($im, (int) $x, (int) $y, 26, 26, $blanc);
        imagefilledellipse($im, (int) $x, (int) $y, 19, 19, $gris);
    }

    // Le bien : repère citron, étiquette « Votre bien · prix »
    [$bx, $by] = $pos((float) $bien['lat'], (float) $bien['lon']);
    imagefilledellipse($im, (int) $bx, (int) $by, 78, 78, $encre);
    imagefilledellipse($im, (int) $bx, (int) $by, 64, 64, $col(C_CITRON));
    imagefilledellipse($im, (int) $bx, (int) $by, 18, 18, $encre);
    $occupes[] = [$bx - 41, $by - 41, $bx + 41, $by + 41];

    // Ventes retenues : numéro (comme dans le tableau) et prix ; couleur selon le rang de ressemblance
    $couleurs = [[46, 125, 58], [95, 127, 36], [236, 232, 220]];
    $reperes = [];
    foreach ($comparables as $i => $c) {
        if (empty($c['lat'])) continue;
        [$x, $y] = $pos((float) $c['lat'], (float) $c['lon']);
        $rgb = $couleurs[$i < 4 ? 0 : ($i < 8 ? 1 : 2)];
        $reperes[] = [$i, $x, $y, $rgb, $c];
        $occupes[] = [$x - 32, $y - 32, $x + 32, $y + 32];
    }
    $etiquette = function (float $x, float $y, float $rayon, string $texte, array $fondRgb, array $texteRgb, string $police, float $t) use ($taille, $libre, $cartouche, &$occupes) {
        [$l, $h] = $taille($texte, $police, $t);
        foreach ([[$x + $rayon + 6, $y - $h / 2], [$x - $rayon - 6 - $l, $y - $h / 2], [$x - $l / 2, $y - $rayon - 6 - $h], [$x - $l / 2, $y + $rayon + 6]] as [$ex, $ey]) {
            if ($libre([$ex, $ey, $ex + $l, $ey + $h])) {
                $occupes[] = $cartouche($ex, $ey, $texte, $fondRgb, $texteRgb, $police, $t);
                return true;
            }
        }
        return false;
    };
    // les plus ressemblantes d'abord : leurs étiquettes passent en priorité ; les repères sont dessinés à la fin
    $etiquette($bx, $by, 41, 'Votre bien · ' . $kEuros($prix), C_CITRON, C_ENCRE, $gras, 25);
    foreach ($reperes as [$i, $x, $y, $rgb, $c]) $etiquette($x, $y, 32, ($i + 1) . ' · ' . $kEuros((float) $c['prix']), [255, 253, 248], C_ENCRE, $mono, 21); // le numéro du tableau, même quand des repères se superposent (ventes à la même adresse)
    foreach (array_reverse($reperes) as [$i, $x, $y, $rgb, $c]) {
        imagefilledellipse($im, (int) $x, (int) $y, 62, 62, $i < 8 ? $blanc : $encre);
        imagefilledellipse($im, (int) $x, (int) $y, 54, 54, $col($rgb));
        $n = (string) ($i + 1);
        $b = imagettfbbox(20, 0, $gras, $n);
        imagettftext($im, 20, 0, (int) ($x - ($b[2] - $b[0]) / 2 - 1), (int) ($y + 10), $i < 8 ? $blanc : $encre, $gras, $n);
    }

    // Échelle et sources
    $mParPixel = 156543.03392 * cos(deg2rad((float) $bien['lat'])) / 2 ** $z / $S;
    $e = echelle_ronde(CARTE_L * $S / 6 * $mParPixel);
    $lpx = $e / $mParPixel;
    $ey = CARTE_H * $S - 44;
    imagefilledrectangle($im, 22, $ey - 40, (int) (46 + $lpx), $ey + 24, $col([255, 253, 248]));
    imagefilledrectangle($im, 34, $ey, (int) (34 + $lpx), $ey + 10, $encre);
    imagettftext($im, 19, 0, 34, $ey - 10, $encre, $mono, $e >= 1000 ? ($e / 1000) . ' km' : "$e m");
    $source = ($avecFond ? '© IGN · Plan IGN' : 'Fond de carte indisponible') . ' · Ventes : DVF (DGFiP)';
    $b = imagettfbbox(17, 0, $mono, $source);
    $sx = CARTE_L * $S - ($b[2] - $b[0]) - 34;
    imagefilledrectangle($im, $sx - 12, CARTE_H * $S - 50, CARTE_L * $S, CARTE_H * $S, $col([255, 253, 248]));
    imagettftext($im, 17, 0, $sx, CARTE_H * $S - 18, $encre, $mono, $source);
    imagerectangle($im, 0, 0, CARTE_L * $S - 1, CARTE_H * $S - 1, $encre);

    $temp = tempnam(sys_get_temp_dir(), 'carte');
    @unlink($temp); // seul le nom sert : l'image a besoin de l'extension .png
    $fichier = "$temp.png";
    imagepng($im, $fichier, 6);
    imagedestroy($im);
    return ['fichier' => $fichier, 'fond' => $avecFond, 'zoom' => $z, 'ratio' => CARTE_H / CARTE_L];
}

/** Autres ventes du même type autour du bien (hors ventes retenues), les plus proches d'abord. */
function autres_ventes(array $v, array $comparables, int $max = 150): array
{
    $type = champ($v, 'type_bien');
    $cle = fn ($x) => ($x['date'] ?? '') . '|' . ($x['prix'] ?? '') . '|' . ($x['adresse'] ?? '');
    $vues = array_flip(array_map($cle, $comparables));
    $autres = array_values(array_filter($v['public']['ventes'] ?? [], fn ($x) => !empty($x['lat']) && !isset($vues[$cle($x)])
        && (!in_array($type, ['Maison', 'Appartement'], true) || $x['type'] === $type)));
    usort($autres, fn ($a, $b) => ($a['distance'] ?? PHP_INT_MAX) <=> ($b['distance'] ?? PHP_INT_MAX));
    return array_slice($autres, 0, $max);
}
