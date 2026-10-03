<?php
/** Une rubrique : libellés, introduction, menu, ordre des fiches. Variables : $cat, $rows, $total, $trail, $children, $hasOrder */
use App\Admin\Form;
use App\Data\Categories;
use App\Data\Fiches;
?>
<form class="editor" data-json-form data-url="/admin/rubriques" novalidate>
  <input type="hidden" name="slug" value="<?= e($cat['slug']) ?>">
  <div class="stack">
    <div class="card card--pad">
      <h2 class="card__t">Libellés</h2>
      <div class="fgrid fgrid--2">
        <?= Form::text('label', 'Libellé court (menus, fil d’Ariane)', $cat['label'] ?? '', ['placeholder' => $cat['name'], 'maxlength' => 80, 'help' => 'Vide : nom d’origine « ' . e($cat['name']) . ' ».']) ?>
        <div class="f"><span class="f__k">Libellé anglais <button type="button" class="btn btn--sm btn--ghost" data-tr="label">Traduire</button></span><input type="text" name="label_en" value="<?= e((string) ($cat['label_en'] ?? '')) ?>" maxlength="80" placeholder="Vide : traduction automatique de l’interface"></div>
      </div>
    </div>
    <div class="card card--pad" data-tr-scope>
      <h2 class="card__t">Texte d’introduction de la mosaïque</h2>
      <?= Form::html('description', 'Introduction (FR)', $cat['description'] ?? '', ['mini' => true]) ?>
      <div class="row"><button type="button" class="btn btn--sm" data-tr="description">Traduire en anglais avec Gemini</button></div>
      <?= Form::html('description_en', 'Introduction (EN)', $cat['description_en'] ?? '', ['mini' => true]) ?>
      <span class="f__help">Affichée sous le titre de la rubrique et utilisée comme description pour Google.</span>
    </div>
    <div class="card">
      <div class="card__head"><h2 class="card__t">Ordre des fiches</h2><span class="card__note"><?= (int) $total ?> fiche<?= $total > 1 ? 's' : '' ?> publiée<?= $total > 1 ? 's' : '' ?> (sous-rubriques comprises)</span></div>
      <div class="card__body">
        <?= Form::toggle('manual_order', 'Ordre manuel (glisser-déposer) au lieu de l’ordre automatique', $hasOrder, ['help' => 'Automatique : dates des matchs, ordre alphabétique des personnes, dates de publication.']) ?>
        <div data-show-if="manual_order">
          <div class="rep rep--compact" data-repeater="order" style="max-height:60vh;overflow:auto">
            <?php foreach ($rows as $i => $r): ?>
              <div class="rep__item" data-item>
                <div class="rep__handle" title="Glisser pour déplacer"><span data-item-n><?= sprintf('%02d', $i + 1) ?></span></div>
                <div class="rep__body" style="flex-direction:row;align-items:center;gap:10px">
                  <input type="hidden" data-field="id" data-type="int" value="<?= (int) $r['id'] ?>">
                  <?php if ($r['image']): ?><img src="<?= e(img($r['image'], 160)) ?>" alt="" style="width:56px;height:40px;object-fit:cover;border:1px solid var(--navy)"><?php endif; ?>
                  <a href="/admin/fiche/<?= (int) $r['id'] ?>" target="_blank" rel="noopener"><?= e($r['title']) ?></a>
                  <span class="xs muted"><?= e(Fiches::TYPES[$r['type']] ?? $r['type']) ?></span>
                </div>
                <div class="rep__tools"><button type="button" class="iconbtn" data-rep-up title="Monter" aria-label="Monter">↑</button><button type="button" class="iconbtn" data-rep-down title="Descendre" aria-label="Descendre">↓</button></div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($total > count($rows)): ?><p class="small muted">Seules les <?= count($rows) ?> premières fiches sont affichées ; les suivantes gardent l’ordre automatique.</p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <aside class="editor__side">
    <div class="card card--pad">
      <h2 class="card__t card__t--sm">Menu</h2>
      <?= Form::number('position', 'Position parmi les sous-rubriques', $cat['position'] ?? null, ['placeholder' => 'auto', 'help' => 'Plus petit = plus haut. Vide : ordre alphabétique ou chronologique.']) ?>
      <?= Form::toggle('technical', 'Masquer des menus et des filtres', !empty($cat['technical'])) ?>
      <button type="submit" class="btn btn--navy btn--block" data-save>Enregistrer</button>
      <span class="small muted" data-saved></span>
    </div>
    <div class="card card--pad">
      <h2 class="card__t card__t--sm">Adresse</h2>
      <a href="<?= e($cat['path']) ?>" target="_blank" rel="noopener" style="overflow-wrap:anywhere"><?= e($cat['path']) ?> ↗</a>
      <?php if (!empty($cat['old_path'])): ?><span class="xs muted" style="overflow-wrap:anywhere">Ancienne adresse WordPress (redirigée) : <?= e($cat['old_path']) ?></span><?php endif; ?>
      <span class="xs muted">Fil : <?= e(implode(' › ', array_map(fn ($c) => Categories::label($c['slug']), $trail))) ?></span>
    </div>
    <?php if ($children): ?>
      <div class="card">
        <div class="card__head"><h2 class="card__t card__t--sm">Sous-rubriques</h2></div>
        <?php foreach ($children as $ch): ?><a class="card__row" href="/admin/rubriques?rubrique=<?= e(rawurlencode($ch['slug'])) ?>"><?= e(Categories::label($ch['slug'])) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </aside>
</form>
