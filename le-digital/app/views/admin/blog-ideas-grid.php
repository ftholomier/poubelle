<?php
/**
 * Mosaïque des idées d'articles proposées par l'IA (un lot) : coche, date de parution, rédaction en file.
 * Rendue dans la page « Écrire avec l'IA » et renvoyée telle quelle après une nouvelle proposition.
 * @var array $batch @var bool $canImage @var array $batches (facultatif : lots précédents)
 */
use LD\Controllers\AdminContent;
use LD\Posts;

$batches ??= [];
$heat = [1 => 'Sujet de fond', 2 => 'Intéressant', 3 => 'Dans l’air du temps', 4 => 'Tendance', 5 => 'Très chaud'];
$ideas = array_values(array_filter($batch['ideas'], static fn($i) => ($i['status'] ?? '') !== 'dismissed'));
$hidden = count($batch['ideas']) - count($ideas);
$usd = $batch['usd'] ?? null;
?>
<div class="bi__batch" data-bi-batch="<?= e($batch['id']) ?>">
  <div class="bi__info">
    <p class="small muted">
      Veille du <?= e(date('d/m/Y à H:i', (int) strtotime((string) $batch['at']))) ?>
      <?= $batch['focus'] !== '' ? ' · consigne : « ' . e($batch['focus']) . ' »' : '' ?>
      · <?= (int) $batch['signals'] ?> actualités et tendances analysées
      · <?= !empty($batch['web']) ? 'avec recherche web' : 'sans recherche web' ?>
      <?= $usd !== null ? ' · coût ' . e(number_format((float) $usd * LD\AiCosts::rate(), 3, ',', ' ')) . ' €' : '' ?>
      <?= $hidden ? ' · ' . $hidden . ' idée' . ($hidden > 1 ? 's' : '') . ' écartée' . ($hidden > 1 ? 's' : '') : '' ?>
    </p>
    <?php if (count($batches) > 1): ?>
      <label class="bi__lot small">Lots précédents
        <select data-bi-lot>
          <?php foreach ($batches as $b): ?>
            <option value="<?= e($b['id']) ?>"<?= $b['id'] === $batch['id'] ? ' selected' : '' ?>><?= e(date('d/m H:i', (int) strtotime((string) $b['at']))) ?> · <?= count($b['ideas']) ?> idées<?= $b['focus'] !== '' ? ' · ' . e(mb_strimwidth($b['focus'], 0, 40, '…')) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>
  </div>

  <div class="bi__grid">
    <?php foreach ($ideas as $i):
        $post = $i['post_id'] !== '' ? Posts::find($i['post_id']) : null;
        $state = $post ? AdminContent::postState($post) : null; ?>
      <article class="bi__card<?= $post ? ' is-done' : '' ?>" data-idea="<?= e($i['id']) ?>">
        <div class="bi__top">
          <span class="pill"><?= e($i['theme']) ?></span>
          <span class="bi__heat" title="<?= e($heat[$i['tendance']] ?? '') ?>" aria-label="Tendance : <?= e($heat[$i['tendance']] ?? '') ?>"><?php for ($k = 1; $k <= 5; $k++): ?><i<?= $k <= $i['tendance'] ? ' class="on"' : '' ?>></i><?php endfor; ?><small><?= e($heat[$i['tendance']] ?? '') ?></small></span>
          <?php if (!$post): ?><button class="bi__x" type="button" data-bi-dismiss title="Écarter cette idée" aria-label="Écarter cette idée">✕</button><?php endif; ?>
        </div>
        <label class="bi__head">
          <input type="checkbox" data-bi-pick<?= $post ? ' disabled' : '' ?>>
          <span class="bi__title"><?= e($i['titre']) ?></span>
        </label>
        <?php if ($i['pourquoi'] !== ''): ?><p class="bi__why"><b>Pourquoi maintenant :</b> <?= e($i['pourquoi']) ?></p><?php endif; ?>
        <details class="bi__more">
          <summary>L’angle, les mots-clés<?= $i['sources'] ? ' et les sources' : '' ?></summary>
          <?php if ($i['angle'] !== ''): ?><p><?= e($i['angle']) ?></p><?php endif; ?>
          <?php if ($i['mots_cles'] !== ''): ?><p><b>Mots-clés :</b> <?= e($i['mots_cles']) ?></p><?php endif; ?>
          <?php if ($i['public'] !== ''): ?><p><b>Pour :</b> <?= e($i['public']) ?></p><?php endif; ?>
          <p><b>Longueur :</b> <?= e(LD\BlogWriter::LENGTHS[$i['longueur']] ?? $i['longueur']) ?></p>
          <?php if ($i['sources']): ?>
            <ul class="bi__src"><?php foreach ($i['sources'] as $s): ?><li><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($s['titre']) ?></a></li><?php endforeach; ?></ul>
          <?php endif; ?>
        </details>
        <div class="bi__plan" data-bi-plan<?= $post ? ' hidden' : '' ?>>
          <label class="sr-only" for="bi-m-<?= e($i['id']) ?>">Parution</label>
          <select id="bi-m-<?= e($i['id']) ?>" data-bi-mode>
            <option value="now">Publier dès qu’il est prêt</option>
            <option value="date">Programmer le…</option>
            <option value="draft">Brouillon (je relis avant)</option>
          </select>
          <input type="date" data-bi-date min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" aria-label="Date de parution" hidden>
        </div>
        <p class="bi__status<?= $state ? ' is-' . e($state['key']) : '' ?>" data-bi-status<?= $state ? '' : ' hidden' ?>>
          <?php if ($state): ?>
            ✓ Rédigé · <b><?= e($state['label']) ?></b>
            · <a href="/admin/blog/<?= e($post['id']) ?>">Relire / modifier</a><?= !empty($state['url']) ? ' · <a href="' . e($state['url']) . '" target="_blank" rel="noopener">Voir sur le site</a>' : '' ?>
          <?php endif; ?>
        </p>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="bi__bar sticky-actions" data-bi-bar>
    <div class="bi__opts">
      <label class="check small"><input type="checkbox" data-bi-all> Tout cocher</label>
      <label class="check small"><input type="checkbox" data-bi-img<?= $canImage ? ' checked' : ' disabled' ?>> Photo Nano Banana<?= $canImage ? '' : ' (clé Gemini requise)' ?></label>
      <label class="check small" title="L’IA vérifie l’actualité du sujet avant d’écrire (3 recherches au plus, avec Claude ou Gemini)"><input type="checkbox" data-bi-web checked> Vérifier l’actualité sur le web</label>
      <span class="bi__spread small">
        <button class="link" type="button" data-bi-spread>📅 Étaler les parutions</button>
        <select data-bi-every aria-label="Rythme de parution">
          <option value="3">tous les 3 jours</option>
          <option value="7" selected>chaque semaine</option>
          <option value="14">toutes les 2 semaines</option>
        </select>
      </span>
    </div>
    <div class="actions">
      <button class="btn btn--red" type="button" data-bi-run disabled>✨ Rédiger les articles cochés</button>
      <span class="small muted" data-bi-progress>Coche les idées qui te plaisent.</span>
    </div>
  </div>
</div>
