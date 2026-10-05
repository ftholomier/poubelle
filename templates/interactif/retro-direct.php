<?php
/**
 * Rétro-Direct : programme des directs. Variables : $live, $soon, $past (avec stats), $classics, $now
 */
use App\Front\Retro;
use App\Services\RetroDirect;

$ago = function (array $s, ?string $on = null): string {
    $d = (string) ($s['m']['date'] ?? '');
    if (!$d) {
        return '';
    }
    $n = (int) substr($on ?? date('Y-m-d'), 0, 4) - (int) substr($d, 0, 4);
    $same = $on !== null && substr($on, 5) === substr($d, 5);
    return $n > 0 ? t($same ? 'Il y a {n} ans jour pour jour' : 'Il y a {n} ans', ['n' => $n]) : '';
};
$comp = fn (array $s): string => trim((string) ($s['m']['label'] ?: ($s['m']['competition'] ?? '')));
$card = function (array $s, string $kicker, string $when, string $cta, ?string $extra = null, bool $score = false) use ($comp): string {
    ob_start(); ?>
    <a class="rdcard" href="<?= e(RetroDirect::url($s)) ?>" data-reveal>
      <span class="rdcard__img"><?php if (!empty($s['image'])): ?><img src="<?= e(img($s['image'], 480)) ?>" srcset="<?= e(srcset($s['image'], [480, 800])) ?>" sizes="(max-width: 700px) 100vw, 400px" alt="" loading="lazy"><?php endif; ?></span>
      <span class="rdcard__body">
        <span class="rdcard__kicker"><?= e($kicker) ?></span>
        <span class="rdcard__teams"><?= e(($s['m']['home'] ?? '') . ' – ' . ($s['m']['away'] ?? '')) ?><?php if ($score && is_array($s['m']['sh_score'] ?? null)): ?> <b><?= (int) $s['m']['sh_score'][0] ?>-<?= (int) $s['m']['sh_score'][1] ?></b><?php endif; ?></span>
        <span class="rdcard__meta"><?= e(trim($comp($s) . ' · ' . date_fr($s['m']['date'] ?? null), ' ·')) ?></span>
        <?php if ($when !== ''): ?><span class="rdcard__when"><?= e($when) ?></span><?php endif; ?>
        <?php if ($extra): ?><span class="rdcard__extra"><?= e($extra) ?></span><?php endif; ?>
        <span class="rdcard__cta"><?= e($cta) ?> →</span>
      </span>
    </a>
    <?php return (string) ob_get_clean();
};
?>
<section class="mhead rdhead">
  <div class="wrap mhead__inner" style="padding-bottom:clamp(32px,4vw,56px)">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><a href="<?= e(url('/interactif/')) ?>"><?= e(t('Interactif')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t('Rétro-Direct')) ?></span></nav>
    <span class="eyebrow eyebrow--lg eyebrow--yellow"><span class="rdlive-dot" aria-hidden="true"></span> <?= e(t('Rétro-Direct')) ?></span>
    <h1 class="mhead__title"><?= e(t('Les grands matchs, rejoués en direct')) ?></h1>
    <p class="mhead__intro"><?= e(t('Le jour anniversaire d’un grand match, à l’heure du coup d’envoi, le musée le rejoue minute par minute : le score change à la minute des buts, les remplaçants entrent, l’arbitre siffle la mi-temps. Comme devant le poste de radio, il y a 30, 40 ou 50 ans.')) ?></p>
    <div class="row" style="gap:12px;flex-wrap:wrap">
      <a class="btn btn--yellow" href="<?= e(url('/interactif/retro-direct/agenda.ics')) ?>" data-rd-subscribe><?= e(t('Ajouter le programme à mon agenda')) ?></a>
      <?php if (\App\Services\Notifications::enabled()): ?><a class="btn btn--ghost-light" href="<?= e(url('/appli/') . '#notifications') ?>"><?= e(t('Être prévenu du coup d’envoi')) ?></a><?php endif; ?>
      <a class="btn btn--ghost-light" href="#comment"><?= e(t('Comment ça marche ?')) ?></a>
    </div>
  </div>
</section>

<div class="wrap rdland">
  <?php foreach ($live as $e): $s = $e['s']; ?>
    <a class="rdnow" href="<?= e(RetroDirect::url($s)) ?>">
      <span class="rdnow__badge"><span class="rdlive-dot" aria-hidden="true"></span> <?= e(t('En direct')) ?></span>
      <span class="rdnow__teams"><?= e(($s['m']['home'] ?? '') . ' – ' . ($s['m']['away'] ?? '')) ?></span>
      <span class="rdnow__meta"><?= e(trim($comp($s) . ' · ' . date_fr($s['m']['date'] ?? null) . ' · ' . $ago($s, $e['date']), ' ·')) ?></span>
      <span class="rdnow__go"><?= e(t('Rejoindre le direct')) ?> →</span>
    </a>
  <?php endforeach; ?>

  <section class="rdland__sec" aria-labelledby="rd-soon">
    <h2 class="h-section" id="rd-soon"><?= e(t('Prochains directs')) ?></h2>
    <?php if ($soon): ?>
      <div class="rdgrid">
        <?php foreach ($soon as $e): ?>
          <?= $card($e['s'], $ago($e['s'], $e['date']), ucfirst(Retro::when((int) $e['start'])), t('La page du direct')) ?>
        <?php endforeach; ?>
      </div>
      <?php $first = $soon[0]; ?>
      <p class="rdland__next" data-rd-countdown-at="<?= (int) $first['start'] ?>" data-rd-now="<?= (int) $now ?>"><?= e(t('Prochain coup d’envoi')) ?> : <b><?= e(Retro::when((int) $first['start'])) ?></b> <span data-rd-countdown-text></span></p>
    <?php else: ?>
      <p class="rdland__empty"><?= e(t('Aucun direct n’est programmé pour l’instant. En attendant, revivez un grand match en accéléré ci-dessous.')) ?></p>
    <?php endif; ?>
  </section>

  <section class="rdland__sec" aria-labelledby="rd-classics">
    <div class="between" style="align-items:flex-end;gap:16px;flex-wrap:wrap">
      <h2 class="h-section" id="rd-classics"><?= e(t('Grands matchs à revivre')) ?></h2>
      <p class="rdland__note"><?= e(t('En accéléré, sans connaître le score : il s’affiche au fil des buts.')) ?></p>
    </div>
    <div class="rdgrid">
      <?php foreach ($classics as $c): ?>
        <?= $card($c['s'], $ago($c['s']), '', t('Revivre le match')) ?>
      <?php endforeach; ?>
    </div>
    <p class="rdland__note"><?= e(t('Chaque fiche de match qui a ses temps forts minute par minute se revit aussi : bouton « Revivre en direct » sur la fiche.')) ?></p>
  </section>

  <?php if ($past): ?>
  <section class="rdland__sec" aria-labelledby="rd-past">
    <h2 class="h-section" id="rd-past"><?= e(t('Déjà joués en direct')) ?></h2>
    <div class="rdgrid">
      <?php foreach ($past as $e): $st = $e['stats']; $r = array_sum($st['reactions']); ?>
        <?= $card($e['s'], t('Direct du {d}', ['d' => date_fr($e['date'])]), '', t('Revoir en accéléré'),
            trim(($st['peak'] ? tn($st['peak'], '{n} spectateur au plus fort', '{n} spectateurs au plus fort') : '') . ($r ? ' · ' . tn($r, '{n} réaction', '{n} réactions') : ''), ' ·') ?: null, true) ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="rdland__sec rdhow" id="comment" aria-labelledby="rd-how">
    <h2 class="h-section" id="rd-how"><?= e(t('Comment ça marche ?')) ?></h2>
    <ol class="rdhow__steps">
      <li><b><?= e(t('Le programme')) ?></b><span><?= e(t('Les historiens du musée choisissent les anniversaires : finales, derbys, soirées de légende, 10, 20, 30 ou 40 ans après, jour pour jour.')) ?></span></li>
      <li><b><?= e(t('Le direct')) ?></b><span><?= e(t('À l’heure du coup d’envoi, le match se déroule minute par minute, à partir des temps forts, des buteurs et des compositions de la fiche. Mi-temps de 15 minutes comprise.')) ?></span></li>
      <li><b><?= e(t('Tous ensemble')) ?></b><span><?= e(t('Le compteur montre combien de supporters suivent le direct ; réagissez d’un clic, et dites-le si vous étiez au stade ce jour-là.')) ?></span></li>
      <li><b><?= e(t('Et après')) ?></b><span><?= e(t('Le match se revit en accéléré quand vous voulez. Vos souvenirs du match enrichissent la fiche : racontez-les au musée.')) ?></span></li>
    </ol>
  </section>
</div>
