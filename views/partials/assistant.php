<?php
/**
 * Bulle de discussion, en bas à droite.
 *
 * Le fragment se retire de lui-même quand l'assistant est désactivé ou qu'il
 * n'a pas de clé : aucune condition à écrire dans le gabarit.
 *
 * L'échange passe par /api/assistant, sur ce domaine. Le navigateur ne
 * contacte donc jamais Google : ni script tiers, ni cookie à soumettre au
 * consentement.
 *
 * @var App\Core\Assistant $assistant
 */
if (!isset($assistant) || !$assistant->actif()) {
    return;
}
use App\Core\Assistant;
use App\Core\Csrf;
?>
<div class="assistant" data-assistant>
  <button type="button" class="assistant__bulle" data-assistant-ouvrir
          aria-expanded="false" aria-controls="assistant-panneau">
    <span class="assistant__bulle-icone" aria-hidden="true"></span>
    <span class="assistant__bulle-texte"><?= e($assistant->titre()) ?></span>
  </button>

  <section id="assistant-panneau" class="assistant__panneau" hidden aria-label="<?= e($assistant->titre()) ?>">
    <header class="assistant__tete">
      <p class="assistant__titre"><?= e($assistant->titre()) ?></p>
      <button type="button" class="assistant__fermer" data-assistant-fermer aria-label="<?= e(t('Fermer')) ?>"></button>
    </header>

    <div class="assistant__fil" data-assistant-fil role="log" aria-live="polite">
      <p class="assistant__message assistant__message--robot"><?= e($assistant->accueil()) ?></p>
    </div>

    <form class="assistant__saisie" data-assistant-form action="<?= url('/api/assistant') ?>">
      <?= Csrf::champ() ?>
      <label for="assistant-question" class="assistant__label"><?= e(t('Votre question')) ?></label>
      <textarea id="assistant-question" name="question" rows="1"
                maxlength="<?= Assistant::QUESTION_MAX ?>"
                placeholder="<?= e(t('Votre question…')) ?>" required></textarea>
      <button type="submit" class="assistant__envoyer" aria-label="<?= e(t('Envoyer')) ?>"></button>
    </form>

    <p class="assistant__mention">
      <?= e(t('Réponses générées à partir du contenu de ce site. Pour un devis, appelez-nous ou passez par le formulaire.')) ?>
    </p>
  </section>
</div>
