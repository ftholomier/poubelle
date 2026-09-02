<?php use App\View; ?>
<canvas id="particles" aria-hidden="true"></canvas>

<div class="studio">
    <aside class="studio__panel">
        <h1 class="admin__title admin__title--tight">Atelier de formes</h1>
        <p class="admin__intro">
            Composez le dessin, regardez-le vivre en particules, puis affectez-le
            à une section.
        </p>

        <form class="studio__form" id="shape-form">
            <label class="field">
                <span class="field__label">Type</span>
                <select name="type" id="type">
                    <option value="svg">Fichier SVG</option>
                    <option value="image">Image (PNG, JPG, WEBP)</option>
                    <option value="preset">Forme mathématique</option>
                    <option value="text">Texte</option>
                </select>
            </label>

            <label class="field" data-when="svg image">
                <span class="field__label">Source</span>
                <select name="src" id="src"></select>
            </label>

            <label class="field" data-when="preset">
                <span class="field__label">Préréglage</span>
                <select name="preset" id="preset"></select>
            </label>

            <label class="field" data-when="text">
                <span class="field__label">Texte</span>
                <input type="text" name="text" id="text" value="H2H" maxlength="24">
            </label>

            <label class="field" data-when="svg">
                <span class="field__label">Rendu</span>
                <select name="mode" id="mode">
                    <option value="fill">Surface pleine</option>
                    <option value="outline">Contour seul</option>
                </select>
            </label>

            <label class="field" data-when="svg">
                <span class="field__label">Règle de remplissage</span>
                <select name="fillRule" id="fillRule">
                    <option value="nonzero">Non-zéro (par défaut)</option>
                    <option value="evenodd">Pair-impair (formes ajourées)</option>
                </select>
            </label>

            <label class="field" data-when="image">
                <span class="field__label">Pixels retenus</span>
                <select name="criterion" id="criterion">
                    <option value="auto">Automatique</option>
                    <option value="alpha">Zones opaques</option>
                    <option value="dark">Zones sombres</option>
                    <option value="light">Zones claires</option>
                </select>
            </label>

            <label class="field">
                <span class="field__label">Particules <output id="count-out">14000</output></span>
                <input type="range" name="count" id="count" min="2000" max="40000" step="1000" value="14000">
            </label>

            <label class="field">
                <span class="field__label">Épaisseur <output id="depth-out">0.12</output></span>
                <input type="range" name="depth" id="depth" min="0" max="0.6" step="0.01" value="0.12">
            </label>

            <label class="field">
                <span class="field__label">Échelle <output id="scale-out">1.00</output></span>
                <input type="range" name="scale" id="scale" min="0.4" max="1.6" step="0.05" value="1">
            </label>

            <label class="field">
                <span class="field__label">Rotation <output id="spin-out">0.00</output></span>
                <input type="range" name="spin" id="spin" min="0" max="1" step="0.02" value="0">
            </label>

            <label class="field">
                <span class="field__label">Axe de rotation</span>
                <select name="spinAxis" id="spinAxis">
                    <option value="y">Vertical — pour les volumes</option>
                    <option value="z">Dans le plan — pour les dessins plats</option>
                </select>
            </label>

            <label class="field">
                <span class="field__label">Décalage horizontal <output id="offsetX-out">0.00</output></span>
                <input type="range" name="offsetX" id="offsetX" min="-1" max="1" step="0.05" value="0">
            </label>

            <label class="field">
                <span class="field__label">Décalage vertical <output id="offsetY-out">0.00</output></span>
                <input type="range" name="offsetY" id="offsetY" min="-1" max="1" step="0.05" value="0">
            </label>

            <label class="field">
                <span class="field__label">Graine <output id="seed-out">1337</output></span>
                <input type="range" name="seed" id="seed" min="1" max="9999" step="1" value="1337">
            </label>

            <label class="field">
                <span class="field__label">Légende affichée sur le site</span>
                <input type="text" name="label" id="label" maxlength="120" placeholder="Fusée — le décollage">
            </label>
        </form>

        <p class="studio__status" id="status" role="status">Prêt.</p>

        <section class="studio__assign">
            <h2 class="field__label">Affecter à une section</h2>
            <label class="field">
                <span class="sr-only">Section cible</span>
                <select id="target">
                    <?php foreach ($pages as $page): ?>
                        <optgroup label="<?= View::e($page['title'] ?? $page['slug']) ?>">
                            <?php foreach ($page['sections'] as $section): ?>
                                <?php
                                $value = $page['slug'] . '|' . $section['id'];
                                $selected = $target !== null
                                    && $target['page'] === $page['slug']
                                    && $target['section'] === $section['id'];
                                ?>
                                <option value="<?= View::e($value) ?>" <?= $selected ? 'selected' : '' ?>>
                                    <?= View::e($section['id']) ?>
                                    — <?= View::e($section['kind'] ?? 'section') ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="button" class="button" id="assign">Enregistrer sur cette section</button>
            <p class="studio__hint">
                Le dessin est vérifié avant enregistrement : une forme que le moteur
                ne sait pas construire est refusée plutôt que de casser la page.
            </p>
        </section>

        <section class="studio__output">
            <div class="studio__output-head">
                <h2 class="field__label">Bloc JSON équivalent</h2>
                <button type="button" id="copy" class="button button--small">Copier</button>
            </div>
            <pre id="snippet"><code></code></pre>
        </section>
    </aside>
</div>

<script type="application/json" id="theme-data"><?= View::json($site['theme'] ?? []) ?></script>
<script type="application/json" id="studio-config"><?= View::json([
    'csrf' => $csrf,
    'current' => $target,
    'shapes' => array_reduce(
        $pages,
        static function (array $carry, array $page): array {
            foreach ($page['sections'] as $section) {
                $carry[$page['slug'] . '|' . $section['id']] = $section['shape'];
            }
            return $carry;
        },
        []
    ),
]) ?></script>
<script type="module" src="<?= View::e(View::asset('assets/js/admin-shapes.js')) ?>"></script>
