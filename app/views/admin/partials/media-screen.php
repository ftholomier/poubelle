<?php
/**
 * Écran de gestion de fichiers, partagé par « Médias » et « Documents IA ».
 * @var array $items @var string $kind @var bool $isDoc @var string $heading @var string $intro
 */
use App\Security\Csrf;

$accept = $isDoc ? '.pdf,.txt,.md,.csv,.docx' : 'image/*';
?>
<header class="ad-head">
    <div>
        <h1 class="ad-head__title"><?= e($heading) ?></h1>
        <p class="ad-head__sub"><?= e($intro) ?></p>
    </div>
</header>

<section class="ad-panel">
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= e(Csrf::token('media')) ?>">
        <input type="hidden" name="action" value="upload">
        <?php if ($isDoc): ?>
            <input type="hidden" name="in_kb" value="1">
            <input type="hidden" name="public" value="0">
        <?php else: ?>
            <input type="hidden" name="public" value="1">
        <?php endif; ?>

        <div class="ad-drop" data-drop tabindex="0" role="button">
            <?= icon('plus', '', 26) ?>
            <strong>Déposez vos fichiers ici</strong>
            <span>ou cliquez pour parcourir — <?= e($isDoc ? 'PDF, TXT, MD, CSV, DOCX' : 'JPG, PNG, WebP, GIF, SVG') ?>, 12 Mo maximum</span>
        </div>
        <input class="sr-only" type="file" name="file[]" multiple accept="<?= e($accept) ?>">
    </form>
</section>

<section class="ad-panel">
    <div class="ad-panel__head">
        <h2 class="ad-panel__title"><?= count($items) ?> fichier<?= count($items) > 1 ? 's' : '' ?></h2>
    </div>

    <?php if (empty($items)): ?>
        <p class="ad-empty">Aucun fichier pour l’instant.</p>
    <?php elseif ($isDoc): ?>
        <div class="ad-scroll">
            <table class="ad-table">
                <thead><tr><th>Document</th><th>Type</th><th>Taille</th><th>Base IA</th><th>Public</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <strong><?= e($item['name']) ?></strong><br>
                            <small style="color:var(--ad-muted)">
                                <?= e(mb_substr($item['text'], 0, 110)) ?><?= mb_strlen($item['text']) > 110 ? '…' : '' ?>
                            </small>
                        </td>
                        <td><span class="ad-tag"><?= e(strtoupper($item['ext'])) ?></span></td>
                        <td class="ad-nowrap"><?= e(number_format($item['size'] / 1024, 0, ',', ' ')) ?> Ko</td>
                        <td><span class="ad-tag ad-tag--<?= !empty($item['in_kb']) ? 'ok' : '' ?>">
                            <?= !empty($item['in_kb']) ? 'Indexé' : 'Ignoré' ?></span></td>
                        <td><span class="ad-tag ad-tag--<?= !empty($item['public']) ? 'warn' : 'info' ?>">
                            <?= !empty($item['public']) ? 'Téléchargeable' : 'Privé' ?></span></td>
                        <td>
                            <div class="ad-table__actions">
                                <form method="post" class="ad-inline">
                                    <input type="hidden" name="_token" value="<?= e(Csrf::token('media')) ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                    <input type="hidden" name="name" value="<?= e($item['name']) ?>">
                                    <input type="hidden" name="in_kb" value="<?= !empty($item['in_kb']) ? '0' : '1' ?>">
                                    <input type="hidden" name="public" value="<?= !empty($item['public']) ? '1' : '0' ?>">
                                    <button type="submit" class="ad-btn ad-btn--ghost ad-btn--sm">
                                        <?= !empty($item['in_kb']) ? 'Retirer de l’IA' : 'Ajouter à l’IA' ?>
                                    </button>
                                </form>
                                <form method="post" class="ad-inline" data-confirm="Supprimer ce document ?">
                                    <input type="hidden" name="_token" value="<?= e(Csrf::token('media')) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                    <button type="submit" class="ad-btn ad-btn--danger ad-btn--sm">Supprimer</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="ad-media">
            <?php foreach ($items as $item): ?>
                <article class="ad-media__item">
                    <div class="ad-media__thumb">
                        <img src="<?= e($item['url']) ?>" alt="<?= e($item['alt']) ?>" loading="lazy">
                    </div>
                    <div class="ad-media__body">
                        <div class="ad-media__name" title="<?= e($item['name']) ?>"><?= e($item['name']) ?></div>
                        <div class="ad-media__meta">
                            <?= e(strtoupper($item['ext'])) ?> · <?= e(number_format($item['size'] / 1024, 0, ',', ' ')) ?> Ko
                        </div>
                        <input class="ad-media__url" type="text" value="<?= e($item['url']) ?>" readonly data-copy
                               aria-label="Adresse du fichier (cliquez pour sélectionner)">
                        <div class="ad-media__actions">
                            <a class="ad-btn ad-btn--ghost ad-btn--sm" href="<?= e($item['url']) ?>" target="_blank" rel="noopener">Voir</a>
                            <form method="post" class="ad-inline" data-confirm="Supprimer ce fichier ? Les pages qui l’utilisent afficheront une image manquante.">
                                <input type="hidden" name="_token" value="<?= e(Csrf::token('media')) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                <button type="submit" class="ad-btn ad-btn--danger ad-btn--sm">Supprimer</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
