<?php
/**
 * L'équipe de Sochaux Rétro en cartes à collectionner (recto : photo, nom, rôle ; verso : mission,
 * anecdote). Partagé par le musée et le site de l'association, chacun avec son thème.
 * Variables : $team (membres nommés, voir App\Vitrine\Content::team), $theme ('musee' | 'asso')
 */
$theme = ($theme ?? 'musee') === 'asso' ? 'asso' : 'musee';
?>
<div class="tcards tcards--<?= $theme ?>">
  <?php foreach ($team as $i => $m):
      $name = trim((string) $m['name']);
      $role = trim((string) ($m['role'] ?? ''));
      $mission = trim((string) ($m['mission'] ?? '')) ?: trim((string) ($m['text'] ?? ''));
      $anec = trim((string) ($m['anecdote'] ?? ''));
      $photo = \App\Vitrine\Content::hasImage($m['photo'] ?? null) ? $m['photo'] : null;
      $no = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
  ?>
  <button type="button" class="tcard" data-tflip aria-pressed="false" aria-label="<?= e('Retourner la carte de ' . $name) ?>" style="--d:<?= ($i % 8) * 60 ?>ms">
    <span class="tcard__in">
      <span class="tcard__face tcard__front">
        <span class="tcard__top"><span class="tcard__no">N° <?= $no ?></span><img src="/assets/img/logo-sochaux-retro.png" alt="" width="34" height="34"></span>
        <span class="tcard__photo"><?php if ($photo): ?><img src="<?= e(img($photo, 480)) ?>" alt="<?= e($name) ?>" loading="lazy"><?php else: ?><span class="tcard__ini" aria-hidden="true"><?= e(\App\Admin\Base::initials($name)) ?></span><?php endif; ?></span>
        <span class="tcard__name"><b><?= e($name) ?></b><?php if ($role !== ''): ?><span><?= e($role) ?></span><?php endif; ?></span>
      </span>
      <span class="tcard__face tcard__back">
        <b class="tcard__bn"><?= e($name) ?></b>
        <?php if ($mission !== ''): ?><span class="tcard__blk"><em>Sa mission</em><?= e($mission) ?></span><?php endif; ?>
        <?php if ($anec !== ''): ?><span class="tcard__blk tcard__anec"><em>L’anecdote</em><?= e($anec) ?></span><?php endif; ?>
        <span class="tcard__foot">Collection L’équipe de Sochaux Rétro · N° <?= $no ?></span>
      </span>
    </span>
  </button>
  <?php endforeach; ?>
</div>
