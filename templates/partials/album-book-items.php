<?php
/**
 * Vignettes d'un album de l'Album Sochini (App\Front\AlbumBook::build), une page (AlbumBook::PAGE).
 * Même rendu que l'album de saison (classes .alb*) : vignette collée (lien vers la fiche, verso au
 * survol), case non collée pour un joueur sans fiche, brillante pour les héros (finale, top buteurs).
 * Variables : $album
 */
use App\Front\AlbumBook;

$type = $album['type'];
$sticker = function (array $p) use ($type): string {
    $role = AlbumBook::role($p);
    $stats = AlbumBook::stats($p, $type);
    if (!$p['stuck']) {
        return '<div class="alb__slot is-empty"><span class="alb__no" aria-hidden="true">' . (int) $p['no'] . '</span>'
            . '<span class="alb__ph" aria-hidden="true">' . icon_photo() . '</span>'
            . '<span class="alb__name"><b>' . e($p['display']) . '</b><span>' . e($role) . '</span></span>'
            . '<span class="alb__stats">' . e($stats) . '</span>'
            . '<span class="sr-only">' . e(t('Vignette non collée : pas encore de fiche joueur.')) . '</span></div>';
    }
    $img = $p['image']
        ? '<img src="' . e(img($p['image'], 320)) . '" alt="' . e(t('Photo de {n}', ['n' => $p['display']])) . '" loading="lazy" decoding="async" width="240" height="300">'
        : '<span class="alb__ph" aria-hidden="true">' . icon_photo() . '</span>';
    $shine = !empty($p['shine']);
    return '<a class="alb__card' . ($shine ? ' alb__card--shine' : '') . (!empty($p['coach']) ? ' alb__card--coach' : '') . '" href="' . e($p['href']) . '"><span class="alb__in">'
        . '<span class="alb__face alb__front"><span class="alb__no">' . (int) $p['no'] . '</span><span class="alb__photo">' . $img . '</span>'
        . '<span class="alb__name"><b>' . e($p['display']) . '</b><span>' . e($role) . '</span></span>'
        . ($shine ? '<span class="sr-only"> · ' . e(t('vignette brillante')) . '</span>' : '') . '</span>'
        . '<span class="alb__face alb__back" aria-hidden="true"><span class="alb__bn">' . e($p['display']) . '</span><span class="alb__bs">' . e($stats) . '</span><span class="alb__go">' . e(t('Voir la fiche')) . ' →</span></span>'
        . '</span><span class="alb__stats">' . e($stats) . '</span></a>';
};
$prev = $album['prevGroup'];
$open = false;
foreach ($album['shown'] as $p):
    if ($p['group'] !== $prev):
        if ($open) echo '</ul>'; ?>
<h3 class="alb__line"><?= e(AlbumBook::groupLabel($p['group'])) ?> <small>(<?= (int) $album['sizes'][$p['group']] ?>)</small></h3>
<?php   echo '<ul class="alb__grid" data-group="' . e($p['group']) . '">';
        $open = true;
        $prev = $p['group'];
    elseif (!$open):
        echo '<ul class="alb__grid" data-group="' . e($p['group']) . '">';
        $open = true;
    endif; ?>
<li class="alb__item"><?= $sticker($p) ?></li>
<?php endforeach;
if ($open) echo '</ul>';
if ($album['page'] < $album['pages']): $to = min($album['total'], $album['start'] + 2 * AlbumBook::PAGE); ?>
<p class="snb__more" data-snb-more><a class="btn btn--navy" href="<?= e(AlbumBook::url($type, (string) $album['v'], $album['page'] + 1)) ?>#vignettes" data-snb-next><?= e(t('Vignettes {a} à {b} sur {n}', ['a' => $album['start'] + AlbumBook::PAGE + 1, 'b' => $to, 'n' => $album['total']])) ?></a></p>
<?php endif; ?>
