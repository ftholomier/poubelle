<?php
/** @var array $li @var string $csrf */
$code = (string) ($li['code'] ?? '');
$place = (string) ($li['place'] ?? 'before');
$title = (string) ($li['title'] ?? 'Mes derniers posts LinkedIn');
$opt = static fn(string $value, string $label) => '<option value="' . e($value) . '"' . ($value === $place ? ' selected' : '') . '>' . e($label) . '</option>';
?>
<?= LD\View::render('admin/blog-tabs', ['on' => 'linkedin']) ?>

<form method="post" action="/admin/blog/linkedin" class="settings-form">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <section class="card">
    <div class="card__head"><h2>Mes posts LinkedIn sur la page Blog</h2><?php if (LD\Posts::linkedinPlace() !== ''): ?><span class="badge badge--ok">En ligne</span><?php endif; ?></div>
    <p class="small">Colle ici le code d’intégration fourni par ton application (HTML / JavaScript) : tes posts LinkedIn s’afficheront directement sur la <a href="/blog" target="_blank" rel="noopener">page Blog</a>, à côté de tes articles, et se mettront à jour tout seuls.</p>
    <label class="lbl">Code d’intégration (HTML / JavaScript)
      <textarea name="code" class="code-area" rows="10" spellcheck="false" placeholder="&lt;script src=&quot;https://…&quot; defer&gt;&lt;/script&gt;&#10;&lt;div id=&quot;…&quot;&gt;&lt;/div&gt;"><?= e($code) ?></textarea>
    </label>
    <div class="form form--cols">
      <label>Titre au-dessus des posts <small class="muted">(vide = pas de titre)</small>
        <input type="text" name="title" maxlength="120" value="<?= e($title) ?>">
      </label>
      <label>Emplacement sur la page Blog
        <select name="place">
          <?= $opt('before', 'Au-dessus des articles') ?>
          <?= $opt('after', 'Sous les articles') ?>
          <?= $opt('replace', 'À la place des articles') ?>
          <?= $opt('off', 'Ne pas afficher') ?>
        </select>
      </label>
    </div>
    <p class="small muted">Un bouton « Me suivre sur LinkedIn » accompagne le titre si l’adresse de ton profil est renseignée dans Paramètres. Tant qu’aucun article n’est publié, les emplacements « Article à fournir » sont masqués quand tes posts LinkedIn sont affichés. Le code est inséré tel quel : ne colle que du code venant d’une application de confiance.</p>
  </section>
  <div class="actions sticky-actions">
    <button class="btn btn--red" type="submit">Enregistrer</button>
    <a class="btn btn--ghost" href="/blog" target="_blank" rel="noopener">Voir la page Blog ↗</a>
  </div>
</form>
