<?php
/**
 * Champ multilingue réutilisable : un onglet par langue active.
 * @var string $name      nom du champ (ex. "title")
 * @var mixed  $value     valeur courante (chaîne ou carte de langues)
 * @var string $label
 * @var array  $languages
 * @var string $type      text | textarea | wysiwyg
 * @var string $hint
 * @var string $attrs attributs HTML supplémentaires posés sur le champ de la 1re langue
 */
$type   = $type ?? 'text';
$attrs  = $attrs ?? '';
$hint   = $hint ?? '';
$rows   = $rows ?? 3;
$uid    = 'ml-' . substr(md5($name . random_bytes(4)), 0, 8);
$values = is_array($value) ? $value : [];
$single = is_string($value) ? $value : '';
?>
<div class="ad-field ad-ml" data-multilang>
    <div class="ad-ml__head">
        <label class="ad-label"><?= e($label) ?></label>
        <?php if (count($languages) > 1): ?>
            <div class="ad-ml__tabs" role="tablist">
                <?php foreach ($languages as $i => $lang): ?>
                    <button type="button" class="ad-ml__tab<?= $i === 0 ? ' is-active' : '' ?>"
                            data-ml-tab="<?= e($lang) ?>" role="tab"
                            aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= e(strtoupper($lang)) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php foreach ($languages as $i => $lang):
        $current = $values[$lang] ?? ($i === 0 ? $single : '');
        $fieldName = $name . '[' . $lang . ']';
        ?>
        <div class="ad-ml__pane<?= $i === 0 ? ' is-active' : '' ?>" data-ml-pane="<?= e($lang) ?>">
            <?php if ($type === 'wysiwyg'): ?>
                <div class="ad-editor" data-editor>
                    <div class="ad-editor__bar" role="toolbar" aria-label="Mise en forme">
                        <button type="button" data-cmd="bold" title="Gras"><strong>B</strong></button>
                        <button type="button" data-cmd="italic" title="Italique"><em>I</em></button>
                        <button type="button" data-cmd="underline" title="Souligné"><u>U</u></button>
                        <span class="ad-editor__sep"></span>
                        <button type="button" data-cmd="formatBlock" data-value="h2" title="Titre 2">H2</button>
                        <button type="button" data-cmd="formatBlock" data-value="h3" title="Titre 3">H3</button>
                        <button type="button" data-cmd="formatBlock" data-value="p" title="Paragraphe">¶</button>
                        <span class="ad-editor__sep"></span>
                        <button type="button" data-cmd="insertUnorderedList" title="Liste à puces">•</button>
                        <button type="button" data-cmd="insertOrderedList" title="Liste numérotée">1.</button>
                        <button type="button" data-cmd="formatBlock" data-value="blockquote" title="Citation">❝</button>
                        <span class="ad-editor__sep"></span>
                        <button type="button" data-cmd="createLink" title="Lien">🔗</button>
                        <button type="button" data-cmd="unlink" title="Retirer le lien">⛓</button>
                        <button type="button" data-cmd="removeFormat" title="Effacer la mise en forme">✕</button>
                        <span class="ad-editor__sep"></span>
                        <button type="button" data-cmd="source" title="Code HTML">&lt;/&gt;</button>
                    </div>
                    <div class="ad-editor__area" contenteditable="true" data-editor-area
                         aria-label="<?= e($label) ?>"><?= \App\Security\Sanitizer::html((string) $current) ?></div>
                    <textarea class="ad-editor__source" data-editor-source name="<?= e($fieldName) ?>" hidden><?= e((string) $current) ?></textarea>
                </div>
            <?php elseif ($type === 'textarea'): ?>
                <textarea class="ad-input" name="<?= e($fieldName) ?>" rows="<?= (int) $rows ?>"><?= e((string) $current) ?></textarea>
            <?php else: ?>
                <input class="ad-input" type="text" name="<?= e($fieldName) ?>" value="<?= e((string) $current) ?>"<?= $i === 0 ? ' ' . $attrs : '' ?>>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($hint !== ''): ?><p class="ad-hint"><?= e($hint) ?></p><?php endif; ?>
</div>
