<?php
/**
 * Bouton « Écouter » : explication audio d'une fiche ou d'une page de synthèse (voix IA enregistrée,
 * sinon voix du navigateur), et lien discret « Voir le texte de l'audio » (le texte reste caché à
 * l'écoute). Variables : $audio (FicheAudio::forPage ou PageAudio), $navy (sur fond jaune ou
 * clair : bouton bleu marine)
 */
$sec = (int) ($audio['secs'] ?? 0);
$len = $sec < 55 ? max(5, (int) round($sec / 5) * 5) . ' s' : max(1, (int) round($sec / 60)) . ' min';
?>
<button type="button" class="btn btn--sm <?= !empty($navy) ? 'btn--navy' : 'btn--yellow' ?> audiobtn" data-audio data-audio-lang="<?= e($audio['lang']) ?>"<?= $audio['url'] ? ' data-audio-src="' . e($audio['url']) . '"' : '' ?> data-stop="<?= e(t('Arrêter')) ?>" aria-pressed="false" hidden>
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" aria-hidden="true"><path d="M4 9.5h3.5L13 5v14l-5.5-4.5H4z"/><path d="M16.5 8.5a5 5 0 0 1 0 7M19.2 5.8a8.8 8.8 0 0 1 0 12.4"/></svg>
  <span data-audio-label><?= e(t('Écouter')) ?> (<?= e($len) ?>)</span>
</button>
<button type="button" class="audiolink" data-audio-show data-hide="<?= e(t('Masquer le texte')) ?>" aria-controls="audiotext" aria-expanded="false" hidden><?= e(t('Voir le texte de l’audio')) ?></button>
<p class="audiotext" id="audiotext" data-audio-text hidden><span class="audiotext__k"><?= e(t('Texte lu')) ?></span><span data-audio-say><?= e($audio['text']) ?></span></p>
