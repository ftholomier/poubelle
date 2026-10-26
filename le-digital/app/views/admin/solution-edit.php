<?php /** @var array $so @var string $csrf */ ?>
<p><a href="/admin/solutions" class="back">← Toutes les solutions</a></p>
<form method="post" class="settings-form">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <section class="card">
    <div class="card__head"><h2><?= $so['id'] ? e($so['name']) : 'Nouvelle solution' ?></h2><?php if ($so['id'] && $so['enabled']): ?><a href="/mes-solutions#<?= e($so['id']) ?>" target="_blank" rel="noopener">Voir sur le site ↗</a><?php endif; ?></div>
    <div class="form form--cols">
      <label>Nom<input name="name" required maxlength="80" value="<?= e($so['name']) ?>" placeholder="Ex. : Formaliance"></label>
      <label>Apparence du bloc
        <select name="theme"><?php foreach (LD\Solutions::THEMES as $k => $l): ?><option value="<?= e($k) ?>"<?= $so['theme'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      </label>
      <label class="span2">Accroche (titre du bloc)<input name="tagline" maxlength="200" value="<?= e($so['tagline']) ?>" placeholder="Ex. : La gestion de tes formations, enfin simple."></label>
      <label class="span2">Présentation<textarea name="text" rows="5" placeholder="À quoi ça sert, pour qui, ce que ça change."><?= e($so['text']) ?></textarea></label>
      <label>Points forts (un par ligne, 8 max)<textarea name="strengths" rows="5" placeholder="Planning en ligne&#10;Émargement numérique&#10;Conforme Qualiopi"><?= e(implode("\n", $so['strengths'])) ?></textarea></label>
      <label>Phrase courte pour l’accueil<textarea name="short" rows="5" maxlength="200" placeholder="Une phrase sous le nom, sur la carte de l’accueil."><?= e($so['short']) ?></textarea></label>
      <div><span class="lbl">Capture d’écran</span><div class="imgfield" data-imgfield><input type="hidden" name="image" value="<?= e($so['image']) ?>"></div></div>
      <div><span class="lbl">Logo (facultatif, remplace le nom)</span><div class="imgfield<?= $so['theme'] === 'sombre' ? ' imgfield--dark' : '' ?>" data-imgfield><input type="hidden" name="logo" value="<?= e($so['logo']) ?>"></div></div>
      <label>Lien du site de la solution<input name="url" value="<?= e($so['url']) ?>" placeholder="https://www.ma-solution.fr"></label>
      <label>Texte du bouton principal<input name="cta" maxlength="60" value="<?= e($so['cta']) ?>" placeholder="Découvrir <?= e($so['name'] ?: 'la solution') ?>"></label>
      <label>Texte du second bouton<input name="demo_cta" maxlength="60" value="<?= e($so['demo_cta']) ?>" placeholder="Demander une démo"></label>
      <label>Lien du second bouton<input name="demo_url" value="<?= e($so['demo_url']) ?>" placeholder="/on-prend-rdv"></label>
      <label class="check span2"><input type="checkbox" name="enabled" value="1"<?= $so['enabled'] ? ' checked' : '' ?>> Afficher cette solution sur le site</label>
    </div>
  </section>
  <div class="actions sticky-actions"><button class="btn btn--red" type="submit">Enregistrer</button></div>
</form>
<?php if ($so['id']): ?>
  <form method="post" action="/admin/solutions/<?= e($so['id']) ?>/supprimer" class="danger" data-confirm="Supprimer définitivement « <?= e($so['name']) ?> » ? (Pour la cacher seulement, décoche « Afficher ».)">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="link-danger" type="submit">Supprimer la solution</button>
  </form>
<?php endif; ?>
