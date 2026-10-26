<?php
/** @var array $w @var string $csrf */
$code = (string) ($w['code'] ?? '');
$avis = (string) ($w['avis'] ?? 'after');
$home = (string) ($w['home'] ?? 'off');
$opt = static fn(string $value, string $current, string $label) => '<option value="' . e($value) . '"' . ($value === $current ? ' selected' : '') . '>' . e($label) . '</option>';
?>
<?= LD\View::render('admin/reviews-tabs', ['on' => 'widget']) ?>

<form method="post" action="/admin/avis/widget" class="settings-form">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <section class="card">
    <div class="card__head"><h2>Widget d’avis à coller</h2><?php if ($code !== ''): ?><span class="badge badge--ok">En ligne</span><?php endif; ?></div>
    <p class="small">Tu utilises un service d’avis (Trustindex, Elfsight, Avis Vérifiés, Guest Suite, Custplace, EmbedSocial…) qui fournit un code à intégrer ? Colle-le tel quel : il s’affichera sur le site à l’endroit choisi, en plus (ou à la place) des avis du site et des avis Google.</p>
    <label class="lbl">Code fourni par le service (HTML / JavaScript)
      <textarea name="code" class="code-area" rows="10" spellcheck="false" placeholder="&lt;script src=&quot;https://…&quot; defer&gt;&lt;/script&gt;&#10;&lt;div class=&quot;…&quot;&gt;&lt;/div&gt;"><?= e($code) ?></textarea>
    </label>
    <div class="form form--cols">
      <label>Page Avis
        <select name="avis">
          <?= $opt('after', $avis, 'Sous les avis du site') ?>
          <?= $opt('before', $avis, 'Au-dessus des avis du site') ?>
          <?= $opt('replace', $avis, 'À la place des avis du site') ?>
          <?= $opt('off', $avis, 'Ne pas afficher') ?>
        </select>
      </label>
      <label>Page d’accueil (section « Ils en parlent mieux que moi »)
        <select name="home">
          <?= $opt('off', $home, 'Ne pas afficher') ?>
          <?= $opt('after', $home, 'Sous les 3 avis mis en avant') ?>
          <?= $opt('replace', $home, 'À la place des 3 avis') ?>
        </select>
      </label>
    </div>
    <p class="small muted">Tant qu’aucun avis n’est publié sur le site, le widget s’affiche aussi (même réglé sur « Ne pas afficher ») à la place des emplacements « Témoignage réel à fournir ». Le code est inséré tel quel : ne colle que du code venant d’un service de confiance.</p>
  </section>
  <div class="actions sticky-actions">
    <button class="btn btn--red" type="submit">Enregistrer</button>
    <a class="btn btn--ghost" href="/avis" target="_blank" rel="noopener">Voir la page Avis ↗</a>
  </div>
</form>
