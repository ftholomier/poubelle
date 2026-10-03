<?php
/** Assistant IA du musée : bulle en bas à droite de toutes les pages. */
use App\Core\Settings;

$name = (string) Settings::get('ai.assistant_name', 'Le guide du musée');
$welcome = (string) Settings::get('ai.welcome', '');
$days = (int) Settings::get('ai.log_retention_days', 365);
$logs = (bool) Settings::get('ai.log_questions', true);
$suggestions = [
    t('Qui est le meilleur buteur du club ?'),
    t('Quel est le bilan face à Saint-Étienne ?'),
    t('Raconte-moi la saison 1987-1988'),
    t('Quand le club a-t-il été fondé ?'),
];
$i18n = [
    'error' => t('L’assistant ne répond pas pour le moment. Réessayez dans un instant.'),
    'sources' => t('Sources'),
    'thanks' => t('Merci pour votre avis !'),
    'useful' => t('Réponse utile'),
    'useless' => t('Réponse à améliorer'),
    'you' => t('Vous'),
    'clear' => t('Effacer la conversation ?'),
];
?>
<div class="chat" data-chat data-api="<?= e(url('/api/chat')) ?>" data-feedback="<?= e(url('/api/chat/avis')) ?>" data-i18n="<?= e(json_encode($i18n, JSON_UNESCAPED_UNICODE)) ?>">
  <button type="button" class="chat__fab" data-chat-toggle aria-expanded="false" aria-controls="chat-panel">
    <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M4 6h24v16H13l-6 5v-5H4z" fill="currentColor"/><circle cx="11" cy="14" r="1.8" fill="#0E1F4D"/><circle cx="16" cy="14" r="1.8" fill="#0E1F4D"/><circle cx="21" cy="14" r="1.8" fill="#0E1F4D"/></svg>
    <span><?= e(t('Une question ?')) ?></span>
  </button>
  <section class="chat__panel" id="chat-panel" role="dialog" aria-modal="false" aria-labelledby="chat-title" hidden>
    <header class="chat__head">
      <img src="/assets/img/logo-sochaux-retro.png" alt="" width="40" height="44">
      <div class="chat__who"><b id="chat-title"><?= e($name) ?></b><span><?= e(t('Assistant IA · répond à partir des fiches du musée')) ?></span></div>
      <button type="button" class="chat__icon" data-chat-clear title="<?= e(t('Nouvelle conversation')) ?>" aria-label="<?= e(t('Nouvelle conversation')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 1 0 2.4-5.7M4 4v5h5" fill="none" stroke="currentColor" stroke-width="2"/></svg></button>
      <button type="button" class="chat__icon" data-chat-close aria-label="<?= e(t('Fermer')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4"/></svg></button>
    </header>
    <div class="chat__log" data-chat-log aria-live="polite">
      <div class="chat__msg chat__msg--bot chat__welcome"><?= safe_html($welcome) ?: '<p>' . e(t('Bonjour ! Je connais tous les matchs, joueurs et personnages du musée Sochaux Rétro. Posez-moi votre question.')) . '</p>' ?></div>
      <div class="chat__sugg" data-chat-sugg>
        <?php foreach ($suggestions as $s): ?><button type="button" data-chat-ask="<?= e($s) ?>"><?= e($s) ?></button><?php endforeach; ?>
      </div>
    </div>
    <form class="chat__form" data-chat-form>
      <label class="sr-only" for="chat-q"><?= e(t('Votre question')) ?></label>
      <textarea id="chat-q" name="q" rows="1" maxlength="500" required placeholder="<?= e(t('Posez votre question sur le FCSM…')) ?>"></textarea>
      <button type="submit" aria-label="<?= e(t('Envoyer')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h14M12 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.6"/></svg></button>
    </form>
    <p class="chat__legal"><?= e(t('Réponses générées par une IA (Google Gemini) à partir des fiches du musée : elles peuvent contenir des erreurs.')) ?> <?= $logs ? e(t('Vos questions sont conservées {n} jours pour améliorer le musée, sans donnée d’identification.', ['n' => $days])) : '' ?> <a href="<?= e(url('/confidentialite/')) ?>"><?= e(t('En savoir plus')) ?></a></p>
  </section>
</div>
