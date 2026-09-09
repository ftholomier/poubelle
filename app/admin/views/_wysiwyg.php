<?php
/** Éditeur WYSIWYG : barre d'outils + zone contenteditable, HTML assaini au serveur. */

use App\Text;

$id = $id ?? ('w-' . bin2hex(random_bytes(3)));
$tools = [
    ['cmd' => 'bold', 'label' => 'B', 'class' => '', 'title' => 'Gras'],
    ['cmd' => 'italic', 'label' => 'I', 'class' => 'tool--i', 'title' => 'Italique'],
    ['cmd' => 'underline', 'label' => 'U', 'class' => '', 'title' => 'Souligné'],
    ['cmd' => 'formatBlock:h2', 'label' => 'H2', 'class' => 'tool--h2', 'title' => 'Sous-titre'],
    ['cmd' => 'formatBlock:p', 'label' => '¶', 'class' => '', 'title' => 'Paragraphe'],
    ['cmd' => 'insertUnorderedList', 'label' => '•', 'class' => '', 'title' => 'Liste'],
    ['cmd' => 'createLink', 'label' => '🔗', 'class' => '', 'title' => 'Lien'],
    ['cmd' => 'removeFormat', 'label' => '↺', 'class' => '', 'title' => 'Enlever la mise en forme'],
];
?>
<div class="wysiwyg-wrap" data-wysiwyg style="margin-top:10px">
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
    <?php foreach ($tools as $tool): ?>
      <button class="tool <?= Text::e($tool['class']) ?>" type="button" data-cmd="<?= Text::e($tool['cmd']) ?>" title="<?= Text::e($tool['title']) ?>"><?= Text::e($tool['label']) ?></button>
    <?php endforeach; ?>
  </div>
  <div class="wysiwyg" id="<?= Text::e($id) ?>" contenteditable="true" data-wysiwyg-area role="textbox" aria-multiline="true"><?= $value ?></div>
  <textarea class="sr-only" name="<?= Text::e($name) ?>" data-wysiwyg-input aria-hidden="true" tabindex="-1"><?= Text::e($value) ?></textarea>
</div>
