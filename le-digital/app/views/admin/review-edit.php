<?php /** @var array $r @var string $csrf */
$google = LD\Reviews::isGoogle($r);
?>
<p><a href="/admin/avis" class="back">← Tous les avis</a></p>
<section class="card">
  <?php if ($google): ?>
    <div class="card__head">
      <h2><?= google_g(18) ?> Avis Google de <?= e($r['name']) ?></h2>
      <?php if (!empty($r['google_uri'])): ?><a href="<?= e($r['google_uri']) ?>" target="_blank" rel="noopener">Voir sur Google ↗</a><?php endif; ?>
    </div>
    <p class="small muted"><?= str_repeat('★', (int) $r['rating']) ?><?= !empty($r['date']) ? ' · ' . e(date_fr(substr((string) $r['date'], 0, 10))) : '' ?> · Le texte, la note et l’auteur viennent de Google et sont mis à jour à chaque synchronisation.</p>
    <div class="ro"><?= e($r['text']) ?></div>
    <?php if (!empty($r['reply'])): ?><p class="small"><b>Ta réponse :</b> <?= e($r['reply']) ?></p><?php endif; ?>
  <?php endif; ?>
  <form method="post" class="form form--cols">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <?php if (!$google): ?>
      <label class="span2">Témoignage<textarea name="text" rows="6" required><?= e($r['text']) ?></textarea></label>
      <label>Prénom<input name="name" required value="<?= e($r['name']) ?>"></label>
    <?php endif; ?>
    <label>Activité<input name="activity" value="<?= e($r['activity'] ?? '') ?>" placeholder="Ex. : Menuisier à Besançon"></label>
    <label>Thème (filtres de la page Avis)
      <select name="tag">
        <option value=""<?= ($r['tag'] ?? '') === '' ? ' selected' : '' ?>>— Aucun thème —</option>
        <?php foreach (LD\Reviews::TAGS as $t): ?><option<?= ($r['tag'] ?? '') === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
      </select>
    </label>
    <?php if (!$google): ?>
      <label>Note
        <select name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"<?= (int) $r['rating'] === $i ? ' selected' : '' ?>><?= str_repeat('★', $i) ?></option><?php endfor; ?></select>
      </label>
      <div class="span2"><span class="lbl">Photo (facultative)</span><div class="imgfield" data-imgfield><input type="hidden" name="photo" value="<?= e($r['photo'] ?? '') ?>"></div></div>
    <?php endif; ?>
    <label>Ordre d’affichage<input type="number" name="order" value="<?= (int) ($r['order'] ?? 0) ?>"></label>
    <?php if (!$google): ?>
      <label>Source (interne)<input name="source" value="<?= e($r['source'] ?? '') ?>" placeholder="Ex. : recommandation LinkedIn"></label>
    <?php endif; ?>
    <label class="check"><input type="checkbox" name="published" value="1"<?= !empty($r['published']) ? ' checked' : '' ?>> Publié sur le site</label>
    <label class="check"><input type="checkbox" name="home" value="1"<?= !empty($r['home']) ? ' checked' : '' ?>> Mis en avant sur l’accueil (3 max)</label>
    <div class="span2 actions"><button class="btn btn--red" type="submit">Enregistrer</button></div>
  </form>
  <?php if ($r['id'] && !$google): ?>
    <form method="post" action="/admin/avis/<?= e($r['id']) ?>/supprimer" class="danger" data-confirm="Supprimer cet avis ?">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="link-danger" type="submit">Supprimer l’avis</button>
    </form>
  <?php elseif ($google): ?>
    <p class="small muted">Pour ne plus l’afficher, décoche « Publié sur le site » : il resterait sinon réimporté à la prochaine synchronisation.</p>
  <?php endif; ?>
</section>
