<?php
/** @var array $kb @var bool $aiReady @var string $model @var ?array $test @var array $docs
 *  @var array $settings @var array $languages @var array $user */
use App\Core\View; use App\Security\Csrf;
$title = 'Assistant IA';
echo View::render('admin/partials/shell-open', compact('settings', 'user', 'active', 'newLeads', 'flash', 'title'));
$bot = $settings['chatbot'];
?>
<header class="ad-head">
    <div>
        <h1 class="ad-head__title">Assistant IA</h1>
        <p class="ad-head__sub">
            L’assistant répond à partir de vos pages et de vos documents, jamais de connaissances inventées.
            La clé d’API reste sur le serveur : le navigateur ne la voit pas.
        </p>
    </div>
    <div class="ad-head__actions">
        <form method="post" class="ad-inline">
            <input type="hidden" name="_token" value="<?= e(Csrf::token('assistant')) ?>">
            <input type="hidden" name="action" value="rebuild">
            <button type="submit" class="ad-btn ad-btn--ghost">Réindexer maintenant</button>
        </form>
    </div>
</header>

<div class="ad-grid ad-grid--3">
    <div class="ad-stat">
        <span class="ad-stat__icon"><?= icon('sparkles', '', 18) ?></span>
        <div class="ad-stat__value"><?= (int) $kb['chunks'] ?></div>
        <div class="ad-stat__label">extraits indexés<?= !empty($kb['stale']) ? ' — index à rafraîchir' : '' ?></div>
    </div>
    <div class="ad-stat">
        <span class="ad-stat__icon"><?= icon('document', '', 18) ?></span>
        <div class="ad-stat__value"><?= count($docs) ?></div>
        <div class="ad-stat__label">documents disponibles</div>
    </div>
    <div class="ad-stat">
        <span class="ad-stat__icon"><?= icon('shield', '', 18) ?></span>
        <div class="ad-stat__value" style="font-size:1.1rem;padding-top:8px">
            <?= $aiReady ? e($model) : 'Mode local' ?>
        </div>
        <div class="ad-stat__label"><?= $aiReady ? 'Gemini connecté' : 'Sans clé : réponse extraite du site' ?></div>
    </div>
</div>

<section class="ad-panel ad-mt">
    <div class="ad-panel__head">
        <div>
            <h2 class="ad-panel__title">Tester une question</h2>
            <p class="ad-panel__hint">Vérifiez ce que l’assistant répondra réellement aux visiteurs.</p>
        </div>
    </div>
    <form method="post">
        <input type="hidden" name="_token" value="<?= e(Csrf::token('assistant')) ?>">
        <input type="hidden" name="action" value="test">
        <div class="ad-field">
            <label class="ad-label" for="question">Question</label>
            <input class="ad-input" id="question" name="question" placeholder="Comment se passe un accompagnement ?">
        </div>
        <button type="submit" class="ad-btn ad-btn--ghost">Envoyer la question</button>
    </form>

    <?php if ($test !== null): ?>
        <div class="ad-list ad-mt">
            <div class="ad-list__item" style="display:block">
                <strong>Réponse :</strong>
                <p style="margin:8px 0 0;white-space:pre-line"><?= e((string) ($test['reply'] ?? $test['error'] ?? '')) ?></p>
                <?php if (!empty($test['sources'])): ?>
                    <p style="margin:12px 0 0">
                        <?php foreach ($test['sources'] as $source): ?>
                            <span class="ad-tag ad-tag--info"><?= e($source['title']) ?></span>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<form method="post">
    <input type="hidden" name="_token" value="<?= e(Csrf::token('assistant')) ?>">

    <section class="ad-panel">
        <div class="ad-panel__head"><h2 class="ad-panel__title">Apparence &amp; discours</h2></div>

        <div class="ad-field">
            <label class="ad-switch">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1"<?= !empty($bot['enabled']) ? ' checked' : '' ?>>
                <span class="ad-switch__track"></span><span>Afficher l’assistant sur le site</span>
            </label>
        </div>

        <?= View::render('admin/partials/multilang', ['name' => 'name', 'value' => $bot['name'],
            'label' => 'Nom affiché', 'languages' => $languages, 'type' => 'text']) ?>
        <?= View::render('admin/partials/multilang', ['name' => 'welcome', 'value' => $bot['welcome'],
            'label' => 'Message d’accueil', 'languages' => $languages, 'type' => 'textarea']) ?>
        <?= View::render('admin/partials/multilang', ['name' => 'placeholder', 'value' => $bot['placeholder'],
            'label' => 'Texte du champ de saisie', 'languages' => $languages, 'type' => 'text']) ?>
        <?= View::render('admin/partials/multilang', ['name' => 'handoff_label', 'value' => $bot['handoff_label'],
            'label' => 'Lien de reprise humaine', 'languages' => $languages, 'type' => 'text']) ?>
        <?= View::render('admin/partials/multilang', ['name' => 'persona', 'value' => $bot['persona'],
            'label' => 'Consigne donnée au modèle',
            'languages' => $languages, 'type' => 'textarea', 'rows' => 6,
            'hint' => 'Décrivez le rôle, le ton et les limites. Les règles anti-détournement sont ajoutées automatiquement.']) ?>
    </section>

    <section class="ad-panel">
        <div class="ad-panel__head">
            <div>
                <h2 class="ad-panel__title">Questions suggérées</h2>
                <p class="ad-panel__hint">Affichées sous le message d’accueil, en un clic.</p>
            </div>
        </div>

        <div class="ad-repeat" id="rep-suggestions">
            <?php
            $renderSuggestion = static function ($value, $index) use ($languages): void { ?>
                <div class="ad-repeat__item">
                    <button type="button" class="ad-repeat__remove" data-repeat-remove aria-label="Supprimer">
                        <?= icon('close', '', 15) ?>
                    </button>
                    <?= App\Core\View::render('admin/partials/multilang', [
                        'name' => 'suggestions[' . $index . ']',
                        'value' => $value, 'label' => 'Question', 'languages' => $languages, 'type' => 'text',
                    ]) ?>
                </div>
            <?php };

            foreach ($bot['suggestions'] as $i => $suggestion) { $renderSuggestion($suggestion, $i); } ?>
            <template><?php $renderSuggestion([], '__INDEX__'); ?></template>
        </div>

        <button type="button" class="ad-btn ad-btn--ghost ad-btn--sm ad-mt" data-repeat-add="rep-suggestions">+ Ajouter une suggestion</button>
    </section>

    <button type="submit" class="ad-btn ad-btn--primary">Enregistrer l’assistant</button>
</form>

<section class="ad-panel ad-mt">
    <div class="ad-panel__head"><h2 class="ad-panel__title">Comment ça marche</h2></div>
    <ol class="ad-list" style="list-style:none">
        <li class="ad-list__item"><span class="ad-tag ad-tag--info">1</span>
            <span>Le contenu de vos pages publiées et de vos documents indexés est découpé en extraits.</span></li>
        <li class="ad-list__item"><span class="ad-tag ad-tag--info">2</span>
            <span>À chaque question, les cinq extraits les plus pertinents sont sélectionnés (recherche lexicale pondérée).</span></li>
        <li class="ad-list__item"><span class="ad-tag ad-tag--info">3</span>
            <span>Ces extraits, et eux seuls, sont transmis à Gemini avec votre consigne.</span></li>
        <li class="ad-list__item"><span class="ad-tag ad-tag--info">4</span>
            <span>Sans clé d’API ou en cas de panne, l’assistant cite directement le meilleur extrait trouvé.</span></li>
    </ol>
    <p class="ad-hint">L’index se reconstruit tout seul dès qu’une page ou un document change.</p>
</section>

<?= View::render('admin/partials/shell-close') ?>
