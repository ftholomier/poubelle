<?php
/**
 * Photos en basse définition : img_lowres() (case remplie, agrandissement au-delà de 35 %),
 * galerie (jamais une grande case, photo à sa taille réelle sur fond flou), carte du joueur,
 * filtre « Basse définition » de la médiathèque, export PDF (jamais plus d'un point par pixel).
 * Dimensions lues dans la médiathèque ; rien n'est écrit. Usage : php tests/basse-definition.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\View;
use App\Data\Media;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Une petite photo (moins de 200 px) et une grande (au moins 1300 px de large et 900 de haut).
$small = $big = null;
foreach (Media::all() as $rel => $m) {
    $w = (int) ($m['width'] ?? 0);
    $h = (int) ($m['height'] ?? 0);
    if (!$small && $w > 100 && $w < 200 && $h > 100 && $h < 200) {
        $small = (string) $rel;
    }
    if (!$big && $w >= 1300 && $h >= 900 && $w < 3000) {
        $big = (string) $rel;
    }
}
$eq('photos d’essai trouvées dans la médiathèque', [$small !== null, $big !== null], [true, true]);
[$sw, $sh] = img_size($small);

// 1. Détection.
$eq('petite photo : basse définition pour une case de galerie', img_lowres($small, 358, 240) !== null, true);
$eq('petite photo : taille réelle donnée au gabarit', img_lowres($small, 358, 240)['style'], "--w:{$sw}px;--h:{$sh}px");
$eq('petite photo : fond flou tiré d’elle-même', str_contains(img_lowres($small, 358, 240)['blur'], 'class="lowres__blur"'), true);
$eq('grande photo : jamais en basse définition (case, carte, bandeau)', [img_lowres($big, 606, 412), img_lowres($big, 346, 380), img_lowres($big, 1200, 600)], [null, null, null]);
$eq('agrandie de 35 % : tolérée ; au-delà : basse définition', [img_lowres($small, (int) floor($sw * 1.35)), img_lowres($small, (int) ceil($sw * 1.35) + 1) !== null], [null, true]);
$eq('hauteur prise en compte (case remplie)', [img_lowres($small, 1, (int) ceil($sh * 1.35) + 1) !== null, img_lowres($small, 1, 0)], [true, null]);
$eq('photo inconnue ou absente : rien', [img_lowres('2099/01/inconnue.jpg', 2000), img_lowres(null, 2000), img_lowres('', 2000)], [null, null, null]);

// 2. Galerie de 4 photos : la petite, placée en tête (grande case 2 × 2), est ramenée à une case simple.
$html = View::partial('partials/gallery', ['items' => [['image' => $small], ['image' => $big], ['image' => $big], ['image' => $big]], 'context' => 'Essai']);
preg_match_all('/<a class="gallery__item([^"]*)" style="([^"]*)"/', $html, $m);
$eq('galerie : la petite photo n’a pas de grande case', $m[2][0] ?? null, "grid-row:span 1;grid-column:span 1;--w:{$sw}px;--h:{$sh}px");
$eq('galerie : la petite photo est montrée à sa taille, sur fond flou', [trim($m[1][0] ?? ''), substr_count($html, 'lowres__blur')], ['is-lowres', 1]);
$eq('galerie : les grandes photos inchangées', [trim($m[1][1] ?? ''), $m[2][1] ?? null], ['', 'grid-row:span 1;grid-column:span 1']);
$html1 = View::partial('partials/gallery', ['items' => [['image' => $big]]]);
$eq('galerie d’une seule grande photo : rien de changé', str_contains($html1, 'is-lowres'), false);

// Carte à collectionner de la fiche joueur : la photo garde tout le cadre, même petite (choix des historiens).
$person = null;
foreach (\App\Data\Fiches::all() as $d) {
    if (($d['type'] ?? '') === 'personne' && ($d['status'] ?? '') === 'publie' && img_lowres($d['featured_image'] ?? null, 346, 380)) {
        $person = $d;
        break;
    }
}
$req = new \App\Core\Request('GET', '/', [], [], [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1'], '');
$page = $person ? \App\Front\Fiche::show($req, $person)->body : '';
$eq('fiche joueur à petite photo : carte pleine, sans fond flou', [$person !== null, str_contains($page, '<span class="pcard__photo">'), str_contains($page, 'pcard__photo is-lowres')], [true, true, false]);

// 3. Médiathèque : filtre « Basse définition » (moins de 400 px de large et de haut).
$eq('médiathèque : petite photo signalée, grande non', [\App\Admin\Medias::lowres(Media::get($small) ?? []), \App\Admin\Medias::lowres(Media::get($big) ?? [])], [true, false]);
$eq('médiathèque : 500 × 300 px n’est pas en basse définition', \App\Admin\Medias::lowres(['width' => 500, 'height' => 300]), false);
$eq('médiathèque : filtre proposé', isset(\App\Admin\Medias::FILTERS['basse-definition']), true);

// 4. PDF : une photo de 163 × 159 pixels n'est plus étirée sur toute la largeur.
$l = new \App\Pdf\Layout();
$l->newPage();
$l->y = 100;
$l->figure(['res' => 'Im1', 'w' => 163, 'h' => 159], '', ['after' => 0]);
$eq('PDF : petite photo à sa taille (159 points de haut, pas 250)', round($l->y - 100, 2), 165.0);
$l->y = 100;
$l->figure(['res' => 'Im2', 'w' => 1600, 'h' => 1200], '', ['h' => 250, 'after' => 0]);
$eq('PDF : grande photo inchangée (250 points de haut)', round($l->y - 100, 2), 256.0);

echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
