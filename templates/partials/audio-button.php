<?php
/** Bouton « Écouter » : résumé de 30 secondes (voix IA enregistrée, sinon voix du navigateur). Variables : $audio (FicheAudio::forPage) */
?>
<button type="button" class="btn btn--sm btn--yellow audiobtn" data-audio data-audio-lang="<?= e($audio['lang']) ?>"<?= $audio['url'] ? ' data-audio-src="' . e($audio['url']) . '"' : '' ?> data-stop="<?= e(t('Arrêter')) ?>" aria-pressed="false" aria-controls="audiotext" hidden>
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" aria-hidden="true"><path d="M4 9.5h3.5L13 5v14l-5.5-4.5H4z"/><path d="M16.5 8.5a5 5 0 0 1 0 7M19.2 5.8a8.8 8.8 0 0 1 0 12.4"/></svg>
  <span data-audio-label><?= e(t('Écouter (30 s)')) ?></span>
</button>
<p class="audiotext" id="audiotext" data-audio-text hidden><span class="audiotext__k"><?= e(t('Texte lu')) ?></span><span data-audio-say><?= e($audio['text']) ?></span></p>
