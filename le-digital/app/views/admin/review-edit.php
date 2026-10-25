<?php /** @var array $r @var string $csrf */ ?>
<p><a href="/admin/avis" class="back">← Tous les avis</a></p>
<section class="card">
  <form method="post" class="form form--cols">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="span2">Témoignage<textarea name="text" rows="6" required><?= e($r['text']) ?></textarea></label>
    <label>Prénom<input name="name" required value="<?= e($r['name']) ?>"></label>
    <label>Activité<input name="activity" value="<?= e($r['activity']) ?>" placeholder="Ex. : Menuisier à Besançon"></label>
    <label>Thème
      <select name="tag"><?php foreach (LD\Reviews::TAGS as $t): ?><option<?= $r['tag'] === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
    </label>
    <label>Note
      <select name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"<?= (int) $r['rating'] === $i ? ' selected' : '' ?>><?= str_repeat('★', $i) ?></option><?php endfor; ?></select>
    </label>
    <div class="span2"><span class="lbl">Photo (facultative)</span><div class="imgfield" data-imgfield><input type="hidden" name="photo" value="<?= e($r['photo']) ?>"></div></div>
    <label>Ordre d’affichage<input type="number" name="order" value="<?= (int) $r['order'] ?>"></label>
    <label>Source (interne)<input name="source" value="<?= e($r['source'] ?? '') ?>" placeholder="Ex. : avis Google du 12/03"></label>
    <label class="check"><input type="checkbox" name="published" value="1"<?= !empty($r['published']) ? ' checked' : '' ?>> Publié sur le site</label>
    <label class="check"><input type="checkbox" name="home" value="1"<?= !empty($r['home']) ? ' checked' : '' ?>> Mis en avant sur l’accueil (3 max)</label>
    <div class="span2 actions"><button class="btn btn--red" type="submit">Enregistrer</button></div>
  </form>
  <?php if ($r['id']): ?>
    <form method="post" action="/admin/avis/<?= e($r['id']) ?>/supprimer" class="danger" data-confirm="Supprimer cet avis ?">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="link-danger" type="submit">Supprimer l’avis</button>
    </form>
  <?php endif; ?>
</section>
