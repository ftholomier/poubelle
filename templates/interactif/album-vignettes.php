<?php
/**
 * Album Sochini / Parini : kiosque des albums, album choisi, « Mon album ».
 * Variables : $cat (AlbumBook::catalog), $type, $v, $album (AlbumBook::build ou null), $prev, $next, $name, $missing
 */
use App\Front\AlbumBook;

$logo = \App\Core\Club::s('logo_400');
$lists = [];
foreach ($cat as $ty => $albums) {
    foreach ($albums as $k => $a) {
        $lists[$ty][] = [(string) $k, AlbumBook::title($a)];
    }
}
$curCat = $cat[$type] ?? [];
?>
<div id="sochini" class="snb" data-path="<?= e(url(AlbumBook::PATH)) ?>">
<section class="wrap snb__head">
  <span class="eyebrow eyebrow--lg"><?= e(t('Interactif')) ?></span>
  <h1 class="h-xl snb__title"><?= e($name) ?></h1>
  <p class="muted snb__lead"><?= e(t('Des albums de vignettes calculés d’après les compositions des matchs officiels : par saison, par décennie, par entraîneur, les grandes épopées… Et, plus bas, votre propre album à compléter.')) ?></p>
  <p class="snb__jump"><a class="linkbtn" href="#mon-album"><?= e(t('Mon album')) ?> ↓</a></p>
</section>

<section class="wrap snk" aria-labelledby="snk-t" data-snb-kiosk>
  <h2 class="sr-only" id="snk-t"><?= e(t('Le kiosque des albums')) ?></h2>
  <nav class="snk__types" aria-label="<?= e(t('Familles d’albums')) ?>">
    <ul>
      <?php foreach ($cat as $ty => $albums): $first = in_array($ty, AlbumBook::SINGLE, true) ? '' : (string) array_key_last($albums); ?>
        <li><a href="<?= e(AlbumBook::url($ty, $first)) ?>"<?= $ty === $type ? ' aria-current="true"' : '' ?> data-snb-link><b><?= e(AlbumBook::typeLabel($ty)) ?></b><small><?= in_array($ty, AlbumBook::SINGLE, true) ? e(t('{n} vignettes', ['n' => reset($albums)['count']])) : e(t('{n} albums', ['n' => count($albums)])) ?></small></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
  <form class="snk__form" method="get" action="<?= e(url(AlbumBook::PATH)) ?>" data-snb-form>
    <label class="snk__f"><span><?= e(t('Type d’album')) ?></span>
      <select name="album" class="in" data-snb-type>
        <?php foreach ($cat as $ty => $_): ?><option value="<?= e($ty) ?>"<?= $ty === $type ? ' selected' : '' ?>><?= e(AlbumBook::typeLabel($ty)) ?></option><?php endforeach; ?>
      </select></label>
    <label class="snk__f"<?= in_array($type, AlbumBook::SINGLE, true) ? ' hidden' : '' ?> data-snb-vwrap><span><?= e(t('Album')) ?></span>
      <select name="v" class="in" data-snb-v<?= in_array($type, AlbumBook::SINGLE, true) ? ' disabled' : '' ?>>
        <?php foreach ($lists[$type] ?? [] as [$k, $label]): ?><option value="<?= e($k) ?>"<?= $k === (string) $v ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select></label>
    <button type="submit" class="btn btn--navy"><?= e(t('Ouvrir l’album')) ?></button>
    <script type="application/json" data-snb-lists><?= json_encode($lists, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  </form>
  <?php if (count($curCat) > 1): ?>
  <ul class="snk__covers" aria-label="<?= e(AlbumBook::typeLabel($type)) ?>">
    <?php foreach ($curCat as $k => $a): $img = AlbumBook::cover($a); $cur = (string) $k === (string) $v; ?>
      <li><a class="snk__cover<?= $cur ? ' is-on' : '' ?>" href="<?= e(AlbumBook::url($type, (string) $k)) ?>"<?= $cur ? ' aria-current="page"' : '' ?> data-snb-link>
        <span class="snk__img"><?php if ($img): ?><img src="<?= e(img($img, 160)) ?>" alt="" loading="lazy" decoding="async" width="120" height="150"><?php else: ?><span class="alb__ph" aria-hidden="true"><?= icon_photo() ?></span><?php endif; ?></span>
        <b><?= e(AlbumBook::title($a)) ?></b><small><?= e(AlbumBook::subtitle($a, $a['count'])) ?></small>
      </a></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>

<?php if ($missing): ?>
<section class="wrap snb__missing" role="alert"><p><b><?= e(t('Album introuvable.')) ?></b> <?= e(t('Choisissez un album dans le kiosque ci-dessus.')) ?></p></section>
<?php endif; ?>

<?php if ($album): ?>
<section id="vignettes" class="alb snb__album" aria-labelledby="alb-title" data-snb-album>
  <div class="wrap">
    <nav class="snb__nav" aria-label="<?= e(t('Albums voisins')) ?>">
      <?php if ($prev): ?><a class="snb__nb" rel="prev" href="<?= e(AlbumBook::url($type, (string) $prev['v'])) ?>" data-snb-link>← <?= e(AlbumBook::title($prev)) ?></a><?php else: ?><span></span><?php endif; ?>
      <?php if ($next): ?><a class="snb__nb" rel="next" href="<?= e(AlbumBook::url($type, (string) $next['v'])) ?>" data-snb-link><?= e(AlbumBook::title($next)) ?> →</a><?php endif; ?>
    </nav>
    <div class="alb__page">
      <header class="alb__head">
        <?php if ($album['coach'] && $album['coach']['stuck'] && $album['coach']['image']): ?>
          <span class="alb__team"><img src="<?= e(img($album['coach']['image'], 160)) ?>" alt="<?= e(t('Photo de {n}', ['n' => $album['coach']['display']])) ?>" loading="lazy" width="80" height="80"><small><?= e(t('Entraîneur')) ?></small></span>
        <?php elseif ($logo): ?><span class="alb__team"><img src="<?= e($logo) ?>" alt="" loading="lazy" width="80" height="80"><small><?= e($name) ?></small></span><?php endif; ?>
        <div>
          <p class="alb__kicker"><?= e(AlbumBook::kicker($album)) ?></p>
          <h2 id="alb-title" class="alb__title"><?= e(AlbumBook::title($album)) ?></h2>
          <p class="alb__lead"><?= e(AlbumBook::subtitle($album, $album['total'])) ?> · <?= e(t('{n} collées', ['n' => $album['stuckCount']])) ?>.
            <?= e(t('Chiffres des matchs officiels (amicaux à part). Une case grise : joueur sans fiche, vignette pas encore collée.')) ?>
            <?php if ($type === 'epopee' && !empty($album['final'])): ?> <?= e(t('Vignettes brillantes : les joueurs de la finale.')) ?><?php elseif ($type === 'buteurs'): ?> <?= e(t('Vignettes brillantes : les dix meilleurs buteurs.')) ?><?php endif; ?>
            <?php if ($type === 'saison'): ?> <a href="<?= e(url('/matchs/' . $album['v'] . '/')) ?>"><?= e(t('La saison {s} en détail', ['s' => $album['v']])) ?> →</a><?php endif; ?>
            <?php if ($album['coach'] && $album['coach']['href']): ?> <a href="<?= e($album['coach']['href']) ?>"><?= e(t('Fiche de {n}', ['n' => $album['coach']['display']])) ?> →</a> · <?= e(AlbumBook::stats($album['coach'], 'staff')) ?><?php endif; ?>
          </p>
          <?php if ($album['page'] > 1): ?><p class="alb__lead"><a href="<?= e(AlbumBook::url($type, (string) $album['v'])) ?>"><?= e(t('Vignettes {a} à {b} : revenir au début de l’album', ['a' => $album['start'] + 1, 'b' => min($album['total'], $album['start'] + AlbumBook::PAGE)])) ?></a></p><?php endif; ?>
        </div>
      </header>
      <div data-snb-items><?= \App\Core\View::partial('partials/album-book-items', ['album' => $album]) ?></div>
    </div>
  </div>
</section>
<?php endif; ?>

<section id="mon-album" class="wrap mya" aria-labelledby="mya-t" data-mya data-api="<?= e('/api/album-vignettes') ?>" data-pool="<?= e('/api/album-vignettes/vignettes.json') ?>" data-share="<?= e('/api/album-vignettes/partage/') ?>">
  <div class="mya__head">
    <div>
      <span class="eyebrow"><?= e(t('Ma collection')) ?></span>
      <h2 class="h-section" id="mya-t"><?= e(t('Mon album')) ?></h2>
      <p class="muted"><?= e(t('Une pochette de vignettes offerte chaque jour, des vignettes bonus en visitant les fiches des joueurs ou en réussissant le défi du jour, des doubles à échanger. Sans compte : votre album est gardé sur cet appareil (code de sauvegarde pour le retrouver ailleurs), ou dans votre carnet du supporter si vous en avez un.')) ?></p>
    </div>
  </div>
  <div class="mya__app" data-mya-app aria-live="polite">
    <noscript><p><?= e(t('Mon album a besoin de JavaScript.')) ?></p></noscript>
  </div>
<?php $T = [
    'err' => t('Mon album ne répond pas pour le moment. Réessayez plus tard.'), 'closed' => t('L’album est fermé pour le moment.'),
    'stuck' => t('vignettes collées'), 'doubles' => t('doubles'), 'packsLeft' => t('pochette(s) aujourd’hui'),
    'open' => t('Ouvrir ma pochette ({n} vignettes)'), 'tomorrow' => t('Prochaine pochette demain'),
    'tab_album' => t('Mon album'), 'tab_echanges' => t('Échanger mes doubles'), 'tab_sauvegarde' => t('Sauvegarde'),
    'page' => t('Page de l’album'), 'progress' => t('{a} vignettes sur {b}'), 'done' => t('Page complétée le {d} !'), 'shareImg' => t('Image à partager'),
    'missing' => t('vignette manquante'), 'pg' => t('{a} / {b}'), 'r_b' => t('brillante'), 'r_o' => t('dorée'),
    'offerT' => t('Proposer un échange'), 'offerD' => t('Choisissez un double à donner et la vignette qui vous manque : le site vous donne un code à transmettre (mis de côté jusqu’à l’échange, 14 jours au plus).'),
    'give' => t('Je donne (un double)'), 'want' => t('Contre (vignette qui me manque)'), 'noDouble' => t('Pas encore de double'), 'noMissing' => t('Album complet !'),
    'makeCode' => t('Créer le code d’échange'), 'newCode' => t('Code d’échange à donner à l’autre collectionneur :'),
    'acceptT' => t('J’ai reçu un code'), 'code' => t('Code'), 'see' => t('Voir l’échange'), 'tradeIs' => t('Vous recevez {g} et donnez {w}.'),
    'accept' => t('Accepter l’échange'), 'needDouble' => t('Il vous faut la vignette demandée en double pour cet échange.'),
    'offers' => t('Mes offres en cours'), 'offerLine' => t('je donne {g} contre {w}'), 'used' => t('échangée'), 'cancel' => t('Annuler'), 'cancelled' => t('Offre annulée : votre double est revenu dans l’album.'),
    'noOffer' => t('Aucune offre en cours.'), 'traded' => t('Échange réussi !'),
    'saveT' => t('Retrouver mon album'), 'saveD' => t('Votre album est gardé sur cet appareil. Notez ce code de sauvegarde pour le retrouver sur un autre appareil ou après avoir effacé vos cookies.'),
    'showCode' => t('Afficher mon code de sauvegarde'), 'keep' => t('Gardez-le pour vous : il ouvre votre album.'),
    'linked' => t('Votre album est rattaché à votre carnet du supporter : vous le retrouvez sur tous vos appareils où le carnet est ouvert.'),
    'restoreT' => t('Restaurer un album'), 'restoreD' => t('Collez un code de sauvegarde pour retrouver un album sur cet appareil.'), 'restore' => t('Restaurer'), 'restored' => t('Album restauré.'),
    'privacyT' => t('Vie privée'), 'privacy' => t('Aucune donnée personnelle : seulement les numéros des vignettes et des dates. Un cookie technique (sr_vignettes) ouvre votre album sur cet appareil ; un album inactif depuis 18 mois est effacé.'),
    'brand' => $name, 'isNew' => t('Nouvelle'), 'double' => t('double'), 'packT' => t('Votre pochette du jour'), 'defi' => t('Défi du jour réussi : vignettes bonus !'),
    'pageDone' => t('Page complétée : {t} !'), 'stick' => t('Coller dans l’album'),
]; ?>
<script type="application/json" id="mya-i18n"><?= json_encode($T, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</section>
</div>
