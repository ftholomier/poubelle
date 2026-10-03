<?php
/**
 * « Les chiffres du FCSM » : 100 statistiques calculées (App\Services\Chiffres).
 * Variables : $chapters (key, title, intro, stats), $count, $scope
 */
use App\Services\Chiffres;

$pad = fn (int $n) => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
// Compteur animé seulement pour les nombres entiers (pas les scores, rangs ou décimales).
$countable = fn (string $v) => (bool) preg_match('/^\d{1,3}(?:[\x{00A0}\x{202F} ,]\d{3})*$/u', $v);
$first = $scope['careers_from'] ?? 1929;
?>
<section class="rhead chead">
  <div class="wrap rhead__inner">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><a href="<?= e(url('/matchs/')) ?>"><?= e(t('Matchs')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t('Les chiffres')) ?></span></nav>
    <span class="eyebrow eyebrow--lg" style="color:var(--navy)"><?= e(t('{n} chiffres · calculés automatiquement', ['n' => $count])) ?></span>
    <h1 class="rhead__title"><?= e(t('Les chiffres')) ?><br><?= e(t('du FCSM')) ?></h1>
    <p class="chead__lead"><?= e(t('De {y} à aujourd’hui, {n} statistiques tirées de toute la mémoire du musée : les carrières saison par saison, les matchs racontés minute par minute et les fiches des Lions.', ['y' => $first, 'n' => $count])) ?></p>
    <dl class="chead__scope">
      <div><dt><?= e(Chiffres::num((int) $scope['careers'])) ?></dt><dd><?= e(t('carrières détaillées saison par saison')) ?></dd></div>
      <div><dt><?= e(Chiffres::num((int) $scope['matches'])) ?></dt><dd><?= e(t('matchs officiels racontés')) ?></dd></div>
      <div><dt><?= e(Chiffres::num((int) $scope['full'])) ?></dt><dd><?= e(t('saisons racontées en entier')) ?></dd></div>
      <div><dt><?= e(Chiffres::num((int) $scope['people'])) ?></dt><dd><?= e(t('fiches de joueurs')) ?></dd></div>
    </dl>
    <nav class="chead__toc" aria-label="<?= e(t('Chapitres')) ?>">
      <?php foreach ($chapters as $ch): $a = $ch['stats'][0]['n']; ?>
        <a href="#ch-<?= e($ch['key']) ?>"><span><?= $pad($a) ?></span><?= e($ch['title']) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</section>

<div class="wrap cbody">
  <?php foreach ($chapters as $ch): $a = $ch['stats'][0]['n']; $b = end($ch['stats'])['n']; ?>
  <section class="cchap" id="ch-<?= e($ch['key']) ?>" aria-labelledby="t-<?= e($ch['key']) ?>">
    <header class="cchap__head">
      <span class="cchap__range"><?= $pad($a) ?> – <?= $pad($b) ?></span>
      <h2 class="h-section" id="t-<?= e($ch['key']) ?>"><?= e($ch['title']) ?></h2>
      <p class="cchap__intro"><?= e($ch['intro']) ?></p>
    </header>
    <div class="cgrid">
      <?php foreach ($ch['stats'] as $i => $s): ?>
      <article class="cstat<?= $i === 0 ? ' cstat--hero' : '' ?>" id="<?= e($s['key']) ?>" data-reveal aria-labelledby="l-<?= e($s['key']) ?>">
        <div class="cstat__top">
          <a class="cstat__n" href="#<?= e($s['key']) ?>" aria-label="<?= e(t('Lien vers le chiffre n° {n}', ['n' => $s['n']])) ?>"><?= $pad($s['n']) ?></a>
          <span class="cstat__src"><?= e($s['src_label']) ?></span>
        </div>
        <h3 class="cstat__label" id="l-<?= e($s['key']) ?>"><?= e($s['label']) ?></h3>
        <p class="cstat__value"><b<?= $countable($s['value']) ? ' data-count' : '' ?>><?= e($s['value']) ?></b><?php if ($s['unit'] !== ''): ?> <span><?= e($s['unit']) ?></span><?php endif; ?></p>
        <?php if ($s['who']): ?>
        <ul class="cstat__who">
          <?php foreach ($s['who'] as $w): ?>
            <li><a href="<?= e($w['href']) ?>"><span class="cstat__img"><?php if (!empty($w['image'])): ?><img src="<?= e(img($w['image'], 160)) ?>" alt="" loading="lazy" width="44" height="44"><?php else: ?><span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($w['name'], 0, 1))) ?></span><?php endif; ?></span><span class="cstat__wn"><b><?= e($w['name']) ?></b><?php if (!empty($w['meta'])): ?><small><?= e($w['meta']) ?></small><?php endif; ?></span></a></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <p class="cstat__text"><?= e($s['text']) ?></p>
        <?php if ($s['more']): ?>
          <p class="cstat__more"><span><?= e(t('Suivent :')) ?></span> <?php foreach ($s['more'] as $k => $m): ?><?= $k ? ' · ' : '' ?><a href="<?= e($m['href']) ?>"><?= e($m['name']) ?></a> <b><?= e($m['v']) ?></b><?php endforeach; ?></p>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <section class="cmethod" aria-labelledby="t-methode">
    <h2 class="h-2" id="t-methode"><?= e(t('Comment sont calculés ces chiffres ?')) ?></h2>
    <div class="cmethod__grid">
      <div>
        <h3><?= e(t('Carrières')) ?></h3>
        <p><?= e(t('Les tableaux de statistiques des fiches joueurs, saison par saison depuis {y}, toutes compétitions officielles confondues. Un tableau recopié à l’identique sur plusieurs fiches est écarté.', ['y' => $first])) ?></p>
      </div>
      <div>
        <h3><?= e(t('Matchs racontés')) ?></h3>
        <p><?= e(t('Les {n} matchs officiels du musée (championnat, coupes, barrages ; sans les amicaux), avec leurs compositions. Les séries ne sont comptées que dans les {s} saisons racontées en entier, et une composition signalée comme douteuse par le contrôle qualité est mise de côté.', ['n' => Chiffres::num((int) $scope['matches']), 's' => $scope['full']])) ?></p>
      </div>
      <div>
        <h3><?= e(t('Récits des matchs')) ?></h3>
        <p><?= e(t('Les temps forts minute par minute : passes décisives, buts de la tête, remontadas, penaltys arrêtés. Seuls les matchs dont le récit retrouve le score final sont pris en compte.')) ?></p>
      </div>
      <div>
        <h3><?= e(t('Fiches des Lions')) ?></h3>
        <p><?= e(t('Dates et lieux de naissance, tailles, pied fort et formation au club. Une date de naissance invraisemblable est ignorée.')) ?></p>
      </div>
    </div>
    <p class="cmethod__foot"><?= e(t('Tout est recalculé à chaque nouvelle fiche publiée : les chiffres grandissent avec le musée.')) ?> <a href="<?= e(url('/contribuer/')) ?>"><?= e(t('Une erreur, un chiffre à compléter ? Écrivez-nous.')) ?></a> · <a href="<?= e(url('/records/')) ?>"><?= e(t('Le livre des records')) ?></a></p>
  </section>
</div>
