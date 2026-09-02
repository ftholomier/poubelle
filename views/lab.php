<?php use App\View; ?>
<canvas id="particles" aria-hidden="true"></canvas>
<div class="backdrop" aria-hidden="true"></div>

<main class="lab">
    <aside class="lab__panel">
        <header class="lab__head">
            <h1 class="lab__title">Laboratoire de formes</h1>
            <p class="lab__intro">
                Réglez un dessin, regardez-le vivre en particules, puis copiez le bloc JSON
                dans <code>content/sections.json</code> pour l'attribuer à une section.
            </p>
        </header>

        <form class="lab__form" id="lab-form">
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
                    <option value="y">Vertical — pour les volumes (sphère, globe…)</option>
                    <option value="z">Dans le plan — pour les dessins plats</option>
                </select>
            </label>

            <label class="field">
                <span class="field__label">Graine <output id="seed-out">1337</output></span>
                <input type="range" name="seed" id="seed" min="1" max="9999" step="1" value="1337">
            </label>
        </form>

        <p class="lab__status" id="lab-status" role="status">Prêt.</p>

        <section class="lab__output">
            <div class="lab__output-head">
                <h2>Bloc à coller</h2>
                <button type="button" id="copy" class="lab__copy">Copier</button>
            </div>
            <pre id="snippet"><code></code></pre>
        </section>

        <p class="lab__back"><a href="/">← Retour au site</a></p>
    </aside>
</main>

<script type="application/json" id="theme-data"><?= View::json($site['theme'] ?? []) ?></script>
