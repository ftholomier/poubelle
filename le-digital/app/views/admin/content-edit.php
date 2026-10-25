<?php /** @var string $page @var string $label @var array $data @var string $url @var string $csrf */ ?>
<div class="toolbar">
  <a href="/admin/contenus" class="back">← Toutes les pages</a>
  <div class="toolbar__r">
    <a class="btn btn--ghost" href="<?= e($url) ?>" target="_blank" rel="noopener">Voir la page ↗</a>
    <form method="post" action="/admin/contenus/<?= e($page) ?>/reinitialiser" data-confirm="Remettre les textes d’origine de la maquette pour cette page ? Tes modifications seront perdues.">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="btn btn--ghost" type="submit">Réinitialiser</button>
    </form>
  </div>
</div>
<p class="intro">Astuce : un champ laissé vide affiche l’encadré « à fournir » de la maquette quand c’est prévu. Les variables entre accolades (ex. <code>{prep}</code>) sont remplacées automatiquement.</p>
<form method="post" class="editor-form" data-content-form>
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="content_json" value="">
  <div class="editor" data-editor="<?= e($page) ?>"></div>
  <script type="application/json" data-editor-json><?= json_script($data) ?></script>
  <div class="savebar"><span class="savebar__state" data-dirty-label>Aucune modification</span><button class="btn btn--red" type="submit">Enregistrer la page</button></div>
</form>
