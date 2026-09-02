<?php use App\View; ?>
<h1 class="admin__title">Diagnostic</h1>
<p class="admin__intro">
    Cette page teste chaque maillon dont dépend le site. Elle n'utilise ni particules
    ni animations : elle reste lisible même quand le reste ne fonctionne pas.
</p>

<section class="panel panel--wide">
    <h2 class="panel__title">Serveur</h2>
    <table class="table">
        <tbody>
        <?php foreach ($checks as $check): ?>
            <tr>
                <td style="width: 2.2rem"><?= $check['ok'] ? '✅' : '❌' ?></td>
                <td><strong><?= View::e($check['label']) ?></strong></td>
                <td><?= View::e($check['detail']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel panel--wide">
    <h2 class="panel__title">Fichiers présents sur le disque</h2>
    <table class="table">
        <tbody>
        <?php foreach ($assets as $asset): ?>
            <tr>
                <td style="width: 2.2rem"><?= $asset['ok'] ? '✅' : '❌' ?></td>
                <td><code><?= View::e($asset['path']) ?></code></td>
                <td><?= $asset['ok'] ? number_format($asset['bytes'], 0, ',', ' ') . ' octets' : 'introuvable' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel panel--wide">
    <h2 class="panel__title">Navigateur</h2>
    <p class="panel__label">
        Ces vérifications se font depuis votre navigateur : elles seules peuvent
        révéler un type MIME refusé ou une syntaxe non reconnue.
    </p>
    <table class="table">
        <tbody id="client-checks">
            <tr><td colspan="3">Analyse en cours…</td></tr>
        </tbody>
    </table>
</section>

<section class="panel panel--wide">
    <h2 class="panel__title">Essai complet du moteur</h2>
    <p class="panel__label">
        Le moteur est instancié ici exactement comme sur le site, avec la forme de
        la première section. Si des particules apparaissent dans le cadre ci-dessous
        mais pas sur le site, la panne est dans la mise en route de la page, pas
        dans le moteur.
    </p>
    <div class="engine-test">
        <canvas id="engine-canvas" width="480" height="320"></canvas>
        <p class="panel__label" id="engine-status">Essai en cours…</p>
    </div>
</section>

<section class="panel panel--wide">
    <h2 class="panel__title">La page d'accueil, vue de l'intérieur</h2>
    <p class="panel__label">
        La page d'accueil est chargée dans un cadre isolé, puis interrogée sur son
        état réel : classes appliquées, moteur démarré, textes révélés.
    </p>
    <table class="table">
        <tbody id="page-checks">
            <tr><td colspan="3">Chargement…</td></tr>
        </tbody>
    </table>
    <?php /* Le cadre garde une taille réaliste et reste dans la fenêtre : un
             cadre hors écran est bridé par le navigateur, ses apparitions au
             défilement ne se déclencheraient pas et le rapport signalerait à
             tort des textes masqués. Il est donc rendu quasi transparent et
             placé sous la page plutôt que déporté au loin. */ ?>
    <iframe
        id="page-probe"
        src="/"
        title="Sonde de la page d'accueil"
        style="position:fixed;inset:0;width:1280px;height:800px;border:0;
               opacity:0.002;pointer-events:none;z-index:-1"
        aria-hidden="true"
        tabindex="-1"></iframe>
</section>

<section class="panel panel--wide">
    <h2 class="panel__title">À copier en cas de souci</h2>
    <pre id="report"><code>…</code></pre>
    <p><button type="button" class="button button--small" id="copy-report">Copier le rapport</button></p>
</section>

<p class="login__back"><a href="/">← Retour au site</a></p>

<script type="module" src="<?= View::e(View::asset('assets/js/diagnostic.js')) ?>"></script>
<script>
    // Chargé en script classique : si le module ci-dessus est refusé, celui-ci
    // s'exécute quand même et le signale.
    setTimeout(function () {
        var body = document.getElementById('client-checks');
        if (body && body.dataset.filled !== '1') {
            body.innerHTML =
                '<tr><td>❌</td><td><strong>Modules JavaScript</strong></td>' +
                '<td>Le module de diagnostic ne s\'est pas exécuté. C\'est très ' +
                'probablement la cause du problème : ouvrez la console du navigateur ' +
                '(F12, onglet « Console ») et relevez le message affiché.</td></tr>';
        }
    }, 4000);
</script>
