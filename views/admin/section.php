<?php

use App\View;

/** @var array<string,mixed> $page */
/** @var array<string,mixed> $section */
/** @var array<string,mixed> $schema */

$slug = rawurlencode((string) $page['slug']);
$id = rawurlencode((string) $section['id']);
?>
<p class="admin__crumbs">
    <a class="link" href="/admin/pages">Pages</a>
    <span aria-hidden="true">›</span>
    <a class="link" href="/admin/page/<?= $slug ?>"><?= View::e($page['title'] ?? $page['slug']) ?></a>
</p>

<h1 class="admin__title"><?= View::e($section['id']) ?></h1>
<p class="admin__intro">
    <?= View::e($schema['label']) ?> — <?= View::e($schema['hint']) ?>
</p>

<form method="post" action="/admin/page/<?= $slug ?>/section/<?= $id ?>" class="panel panel--wide">
    <input type="hidden" name="csrf" value="<?= View::e($csrf) ?>">

    <?php foreach ($schema['fields'] as $name => $field): ?>
        <?= View::capture('admin/partials/field', [
            'name'   => $name,
            'field'  => $field,
            'value'  => $section[$name] ?? null,
            'prefix' => 'champ',
        ]) ?>
    <?php endforeach; ?>

    <div class="form-actions">
        <button type="submit" class="button">Enregistrer le contenu</button>
        <a class="link" href="/admin/formes?page=<?= $slug ?>&amp;section=<?= $id ?>">Régler le dessin →</a>
        <a class="link" href="<?= View::e($page['url']) ?>#<?= $id ?>" target="_blank" rel="noopener">Voir sur le site ↗</a>
    </div>
</form>

<script type="module" src="<?= View::e(View::asset('assets/js/admin-sections.js')) ?>"></script>
