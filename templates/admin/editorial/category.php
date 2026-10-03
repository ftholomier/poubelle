<?php
/** Une rubrique : libellés, introduction, menu, ordre des fiches et des sous-rubriques. Variables : $cat, $rows, $total, $trail, $children, $sort, $unordered */
use App\Admin\Form;
use App\Data\Categories;
use App\Data\Fiches;
?>
<form class="editor" data-json-form data-url="/admin/rubriques" novalidate>
  <input type="hidden" name="slug" value="<?= e($cat['slug']) ?>">
  <div class="stack">
    <div class="card">
      <div class="card__head"><h2 class="card__t">Ordre des fiches</h2><span class="card__note"><?= (int) $total ?> fiche<?= $total > 1 ? 's' : '' ?> publiée<?= $total > 1 ? 's' : '' ?> (sous-rubriques comprises)</span></div>
      <div class="card__body">
        <?= Form::select('sort', 'Ordre d’affichage sur le site', $sort, Categories::SORTS, ['help' => 'Les visiteurs peuvent toujours changer de tri sur la page ; ce réglage choisit l’ordre affiché d’abord.']) ?>
        <div data-show-if="sort" data-show-value="selection" class="stack" style="gap:10px">
          <p class="small muted" style="margin:0"><b>↑ / ↓</b> : monter ou descendre d’un cran. Pour aller plus loin, attrapez l’icône <b>quatre flèches</b> et glissez : un <b>trait jaune</b> montre où la fiche sera déposée. Cliquez sur un numéro pour taper directement la position voulue. Pensez à <b>enregistrer</b>.</p>
          <div class="ordertools">
            <input type="search" class="in in--sm" data-order-find placeholder="Trouver une fiche dans la liste…" aria-label="Trouver une fiche dans la liste">
            <span class="xs muted" data-order-found></span>
            <span class="grow"></span>
            <span class="xs muted">Remettre en ordre :</span>
            <button type="button" class="btn btn--sm btn--ghost" data-order-sort="asc" title="Du plus ancien au plus récent">Date ↑</button>
            <button type="button" class="btn btn--sm btn--ghost" data-order-sort="desc" title="Du plus récent au plus ancien">Date ↓</button>
            <button type="button" class="btn btn--sm btn--ghost" data-order-sort="az">A → Z</button>
          </div>
          <div class="rep rep--compact rep--numbered rep--order" data-repeater="order" data-pad="<?= strlen((string) count($rows)) > 2 ? strlen((string) count($rows)) : 2 ?>">
            <?php $pad = max(2, strlen((string) count($rows))); foreach ($rows as $i => $r): ?>
              <div class="rep__item" data-item data-date="<?= e($r['date']) ?>" data-title="<?= e($r['sort']) ?>">
                <?= Form::position($i, $pad) ?>
                <div class="rep__body ordrow">
                  <input type="hidden" data-field="id" data-type="int" value="<?= (int) $r['id'] ?>">
                  <?php if ($r['image']): ?><img src="<?= e(img($r['image'], 160)) ?>" alt="" loading="lazy" width="56" height="40"><?php else: ?><span class="ordrow__ph" aria-hidden="true"></span><?php endif; ?>
                  <span class="ordrow__t"><a href="/admin/fiche/<?= (int) $r['id'] ?>" target="_blank" rel="noopener"><?= e($r['title']) ?></a>
                    <span class="xs muted"><?= e($r['meta']) ?><?= !$r['ordered'] ? ' · <b class="ordrow__new">non classée</b>' : '' ?><?= $r['status'] !== 'publie' ? ' · ' . e(Fiches::STATUSES[$r['status']] ?? $r['status']) : '' ?></span></span>
                </div>
                <div class="rep__tools"><?= Form::mover() ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($unordered): ?><p class="small muted" style="margin:0"><b><?= (int) $unordered ?></b> fiche<?= $unordered > 1 ? 's' : '' ?> « non classée<?= $unordered > 1 ? 's' : '' ?> » (ajoutée<?= $unordered > 1 ? 's' : '' ?> depuis le dernier classement) : elle<?= $unordered > 1 ? 's sont placées' : ' est placée' ?> en fin de liste tant que vous n’enregistrez pas un nouvel ordre.</p><?php endif; ?>
        </div>
      </div>
    </div>
    <?php if (count($children) > 1): ?>
    <div class="card">
      <div class="card__head"><h2 class="card__t">Ordre des sous-rubriques</h2><span class="card__note">menus, onglets et filtres</span></div>
      <div class="card__body">
        <p class="small muted" style="margin:0">Flèches pour monter ou descendre d’un cran, icône quatre flèches pour glisser plus loin (trait jaune = position de dépôt). L’ordre est enregistré avec la rubrique.</p>
        <div class="rep rep--compact rep--numbered rep--order" data-repeater="children_order">
          <?php foreach ($children as $i => $ch): ?>
            <div class="rep__item" data-item>
              <?= Form::position($i) ?>
              <div class="rep__body ordrow"><input type="hidden" data-field="slug" value="<?= e($ch['slug']) ?>"><span class="ordrow__t"><a href="/admin/rubriques?rubrique=<?= e(rawurlencode($ch['slug'])) ?>"><?= e(Categories::label($ch['slug'])) ?></a><span class="xs muted"><?= e($ch['path'] ?? '') ?></span></span></div>
              <div class="rep__tools"><?= Form::mover() ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <div class="card card--pad">
      <h2 class="card__t">Libellés</h2>
      <div class="fgrid fgrid--2">
        <?= Form::text('label', 'Libellé court (menus, fil d’Ariane)', $cat['label'] ?? '', ['placeholder' => $cat['name'], 'maxlength' => 80, 'proof' => 'title', 'help' => 'Vide : nom d’origine « ' . e($cat['name']) . ' ».']) ?>
        <div class="f"><span class="f__k">Libellé anglais <button type="button" class="btn btn--sm btn--ghost" data-tr="label">Traduire</button></span><input type="text" name="label_en" aria-label="Libellé anglais" data-proof="title" spellcheck="true" lang="en" value="<?= e((string) ($cat['label_en'] ?? '')) ?>" maxlength="80" placeholder="Vide : traduction automatique de l’interface"></div>
      </div>
    </div>
    <div class="card card--pad" data-tr-scope>
      <h2 class="card__t">Texte d’introduction de la mosaïque</h2>
      <?= Form::html('description', 'Introduction (FR)', $cat['description'] ?? '', ['mini' => true]) ?>
      <div class="row"><button type="button" class="btn btn--sm" data-tr="description">Traduire en anglais avec Gemini</button></div>
      <?= Form::html('description_en', 'Introduction (EN)', $cat['description_en'] ?? '', ['mini' => true]) ?>
      <span class="f__help">Affichée sous le titre de la rubrique et utilisée comme description pour Google.</span>
    </div>
  </div>
  <aside class="editor__side">
    <div class="card card--pad">
      <h2 class="card__t card__t--sm">Menu</h2>
      <?= Form::number('position', 'Position dans le menu', $cat['position'] ?? null, ['placeholder' => 'auto', 'help' => !empty($cat['parent']) ? 'Plus simple : glissez les sous-rubriques dans la page de la rubrique parente.' : 'Plus petit = plus haut. Vide : ordre alphabétique ou chronologique.']) ?>
      <?= Form::toggle('technical', 'Masquer des menus et des filtres', !empty($cat['technical'])) ?>
      <button type="submit" class="btn btn--navy btn--block" data-save>Enregistrer</button>
      <button type="button" class="btn btn--block" data-proofread>Vérifier l’orthographe</button>
      <span class="small muted" data-saved></span>
    </div>
    <div class="card card--pad">
      <h2 class="card__t card__t--sm">Adresse</h2>
      <a href="<?= e($cat['path']) ?>" target="_blank" rel="noopener" style="overflow-wrap:anywhere"><?= e($cat['path']) ?> ↗</a>
      <?php if (!empty($cat['old_path'])): ?><span class="xs muted" style="overflow-wrap:anywhere">Ancienne adresse WordPress (redirigée) : <?= e($cat['old_path']) ?></span><?php endif; ?>
      <span class="xs muted">Fil : <?= e(implode(' › ', array_map(fn ($c) => Categories::label($c['slug']), $trail))) ?></span>
    </div>
    <?php if (!empty($cat['parent'])): ?>
      <div class="card">
        <div class="card__head"><h2 class="card__t card__t--sm">Rubrique parente</h2></div>
        <a class="card__row" href="/admin/rubriques?rubrique=<?= e(rawurlencode((string) $cat['parent'])) ?>"><?= e(Categories::label((string) $cat['parent'])) ?> ↑</a>
      </div>
    <?php endif; ?>
  </aside>
</form>
