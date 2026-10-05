<?php
/**
 * Boutique › Supports : produits vierges de l'imprimeur. Variables : $supports, $mockups
 */
$form = function (array $s, bool $new = false) use ($mockups): string {
    ob_start(); ?>
  <form method="post" action="/admin/boutique/supports" class="card card--pad" id="s-<?= e($s['key'] ?: 'nouveau') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="key" value="<?= e($s['key']) ?>">
    <div class="card__head"><h2 class="card__t"><?= $new ? 'Nouveau support' : e($s['name']) ?></h2><?php if (!$new): ?><span class="card__note"><?= $s['active'] ? 'actif' : 'désactivé' ?><?= $s['custom'] ? ' · ajouté' : '' ?></span><?php endif; ?></div>
    <div class="fgrid fgrid--2">
      <label class="f"><span class="f__k">Nom</span><input class="in" name="name" value="<?= e($s['name']) ?>" required></label>
      <label class="f"><span class="f__k">Aperçu</span><select class="in" name="mockup"><?php foreach ($mockups as $k => $l): ?><option value="<?= e($k) ?>"<?= $s['mockup'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <label class="f"><span class="f__k">Référence chez l’imprimeur</span><input class="in" name="ref" value="<?= e($s['ref']) ?>" placeholder="ex. TS-BIO-150"></label>
      <label class="f"><span class="f__k">Coût de fabrication chez l’imprimeur (€ TTC / article)</span><input class="in" type="number" step="0.01" min="0" name="cost" value="<?= e(number_format(($s['cost'] ?? 0) / 100, 2, '.', '')) ?>"></label>
      <label class="f"><span class="f__k">Commission de l’imprimeur (% du prix de vente TTC ; 0 : coût fixe ci-dessus)</span><input class="in" type="number" step="0.1" min="0" max="100" name="rate" value="<?= e(rtrim(rtrim(number_format((float) ($s['rate'] ?? 0), 2, '.', ''), '0'), '.')) ?>"></label>
      <label class="f"><span class="f__k">Tailles (séparées par des virgules)</span><input class="in" name="sizes" value="<?= e(implode(', ', $s['sizes'])) ?>" placeholder="S, M, L, XL"></label>
    </div>
    <div class="f"><span class="f__k">Faces imprimables (mm)</span>
      <table class="xs" style="width:100%"><thead><tr><th style="text-align:left">Libellé</th><th>Largeur</th><th>Hauteur</th><th>Fonds perdus</th></tr></thead><tbody>
      <?php $faces = array_values($s['faces']); for ($i = 0; $i < max(2, count($faces) + 1); $i++): $f = $faces[$i] ?? ['label' => '', 'w' => '', 'h' => '', 'bleed' => '']; ?>
        <tr><td><input class="in in--sm" name="faces[<?= $i ?>][label]" value="<?= e((string) $f['label']) ?>" placeholder="<?= $i ? 'Dos' : 'Avant' ?>"></td><td><input class="in in--sm" type="number" step="0.5" name="faces[<?= $i ?>][w]" value="<?= e((string) $f['w']) ?>"></td><td><input class="in in--sm" type="number" step="0.5" name="faces[<?= $i ?>][h]" value="<?= e((string) $f['h']) ?>"></td><td><input class="in in--sm" type="number" step="0.5" name="faces[<?= $i ?>][bleed]" value="<?= e((string) $f['bleed']) ?>"></td></tr>
      <?php endfor; ?>
      </tbody></table>
    </div>
    <div class="f" data-colpick><span class="f__k">Couleurs du produit vierge chez l’imprimeur (aucune : imprimé en entier, le client choisit la couleur du fond)</span>
      <textarea name="colors" hidden data-colpick-out><?= e(implode("\n", array_map(fn ($n, $h) => "$n : $h", array_keys($s['colors']), $s['colors']))) ?></textarea>
      <ul class="colpick" data-colpick-list></ul>
      <div class="row" style="gap:6px;flex-wrap:wrap;align-items:center;margin-top:6px">
        <button type="button" class="btn btn--sm btn--yellow" data-colpick-add>+ Ajouter une couleur</button>
        <span class="xs muted">ou d’un clic, une couleur de la charte :</span>
        <?php foreach (\App\Shop\Vector::PALETTE as $cn => $hex): ?><button type="button" class="colpick__sw" style="--c:<?= e($hex) ?>" title="<?= e($cn) ?>" data-colpick-preset="<?= e($cn) ?>|<?= e($hex) ?>"></button><?php endforeach; ?>
      </div>
    </div>
    <label class="f"><span class="f__k">Consignes de l’imprimeur</span><input class="in" name="note" value="<?= e($s['note']) ?>"></label>
    <div class="row" style="justify-content:space-between;align-items:center">
      <label class="toggle"><input type="checkbox" name="active" value="1"<?= $s['active'] ? ' checked' : '' ?>><span class="toggle__box"></span><span>Proposé pour de nouveaux modèles</span></label>
      <button class="btn btn--navy btn--sm"><?= $new ? 'Ajouter le support' : 'Enregistrer' ?></button>
    </div>
  </form>
    <?php return (string) ob_get_clean();
};
?>
<p class="small" style="margin:0 0 12px;max-width:95ch">Les produits vierges de l’imprimeur : dimensions de chaque face imprimable (en millimètres, format fini), fonds perdus (marge de sécurité coupée après impression : 2 à 3 mm pour le papier), couleurs disponibles et tailles. Les modèles se dessinent dessus.</p>
<div class="cols" style="align-items:start;grid-template-columns:repeat(auto-fill,minmax(420px,1fr))">
  <?php foreach ($supports as $s): ?><?= $form($s) ?><?php endforeach; ?>
  <?= $form(['key' => '', 'name' => '', 'mockup' => 'paper', 'faces' => [], 'colors' => [], 'sizes' => [], 'ref' => '', 'note' => '', 'cost' => 0, 'active' => true, 'custom' => true], true) ?>
</div>
<style>
.colpick{list-style:none;margin:6px 0 0;padding:0;display:grid;gap:6px}
.colpick li{display:flex;align-items:center;gap:8px;padding:6px 8px;border:1.5px solid rgba(14,31,77,.2);background:#fff}
.colpick input[type=color]{width:38px;height:32px;padding:0;border:2px solid #0e1f4d;background:none;cursor:pointer}
.colpick input[type=text]{flex:1;min-width:0}
.colpick code{font-size:12px;color:#5a6070;width:64px}
.colpick__x{border:0;background:none;font-size:18px;line-height:1;cursor:pointer;color:#b3261e;padding:2px 6px}
.colpick__sw{width:24px;height:24px;border-radius:50%;border:2px solid #0e1f4d;background:var(--c);cursor:pointer;padding:0}
.colpick__sw:hover{transform:scale(1.15)}
.colpick__empty{font-size:13px;color:#5a6070;font-style:italic;padding:4px 0}
</style>
<script nonce="<?= e(csp_nonce()) ?>">
// Sélecteur de couleurs des supports : ronds modifiables, nom, retrait ; la zone de texte cachée part avec le formulaire.
document.querySelectorAll('[data-colpick]').forEach(box => {
  const out = box.querySelector('[data-colpick-out]'), list = box.querySelector('[data-colpick-list]');
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  let cols = out.value.split(/\n/).map(l => l.match(/^\s*(.+?)\s*[:=]\s*(#[0-9a-f]{6})\s*$/i)).filter(Boolean).map(m => ({ n: m[1], h: m[2].toUpperCase() }));
  const sync = () => { out.value = cols.filter(c => c.n.trim()).map(c => c.n.trim().replace(/[:=]/g, ' ') + ' : ' + c.h).join('\n'); };
  const draw = () => {
    list.innerHTML = cols.length ? cols.map((c, i) => `<li><input type="color" value="${c.h.toLowerCase()}" data-i="${i}" aria-label="Couleur"><input class="in in--sm" type="text" value="${esc(c.n)}" data-i="${i}" placeholder="Nom (ex. Rouge bordeaux)" aria-label="Nom de la couleur"><code>${c.h}</code><button type="button" class="colpick__x" data-x="${i}" title="Retirer cette couleur" aria-label="Retirer ${esc(c.n)}">×</button></li>`).join('')
      : '<li class="colpick__empty" style="border:0;background:none">Aucune couleur : support imprimé en entier.</li>';
    sync();
  };
  list.addEventListener('input', e => {
    const i = +e.target.dataset.i; if (Number.isNaN(i)) return;
    if (e.target.type === 'color') { cols[i].h = e.target.value.toUpperCase(); e.target.closest('li').querySelector('code').textContent = cols[i].h; }
    else cols[i].n = e.target.value;
    sync();
  });
  list.addEventListener('click', e => { const x = e.target.closest('[data-x]'); if (x) { cols.splice(+x.dataset.x, 1); draw(); } });
  const add = (n, h) => { if (cols.some(c => c.h === h)) return; cols.push({ n, h }); draw(); const ins = list.querySelectorAll('input[type=text]'); if (!n && ins.length) ins[ins.length - 1].focus(); };
  box.querySelector('[data-colpick-add]').addEventListener('click', () => { add('', '#B3261E'); const pick = list.querySelectorAll('input[type=color]'); pick[pick.length - 1]?.click(); });
  box.querySelectorAll('[data-colpick-preset]').forEach(b => b.addEventListener('click', () => { const [n, h] = b.dataset.colpickPreset.split('|'); add(n, h); }));
  draw();
});
</script>
