<?php
/**
 * Le flipper du musée (plein écran, sans en-tête du site). Variables : $data (réglages du jeu pour
 * flipper.js), $title, $welcome (arrivée par le lien de l'e-mail), $pseudo (compte supporter ou null).
 */
$ui = $data['ui'];
$letters = implode('-', str_split($data['letters']));
$digits = implode('-', str_split($data['digits']));
?>
<div class="fl" data-flipper>
  <script type="application/json" id="fl-data"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <header class="fl__top">
    <a class="fl__back" href="<?= e(url('/interactif/')) ?>" aria-label="<?= e(t('Retour à Interactif')) ?>"><span aria-hidden="true">‹</span> <img src="<?= e($data['logo']) ?>" alt="" width="26" height="30"></a>
    <b class="fl__title"><?= e($title) ?></b>
    <div class="fl__btns">
      <button type="button" class="fl__ic" data-fl-pause aria-label="<?= e(t('Pause')) ?>" title="<?= e(t('Pause')) ?>">❚❚</button>
      <button type="button" class="fl__ic" data-fl-sound aria-pressed="true" aria-label="<?= e(t('Son')) ?>" title="<?= e($ui['soundOff']) ?>"><span aria-hidden="true">🔊</span></button>
      <button type="button" class="fl__ic" data-fl-full aria-label="<?= e(t('Plein écran')) ?>" title="<?= e(t('Plein écran')) ?>">⛶</button>
    </div>
  </header>
  <div class="fl__dmd" data-fl-dmd aria-live="polite">
    <span class="fl__dl" data-fl-dl></span><span class="fl__dr" data-fl-dr></span>
    <b class="fl__big" data-fl-big><?= e($title) ?></b>
    <div class="fl__line"><span class="fl__small" data-fl-small></span></div>
  </div>
  <div class="fl__stage" data-fl-stage>
    <canvas class="fl__canvas" data-fl-canvas aria-label="<?= e(t('Le plateau du flipper')) ?>"></canvas>
    <div class="fl__touch fl__touch--l" data-fl-touch="L" aria-hidden="true"><span><?= e(t('Batteur gauche')) ?></span></div>
    <div class="fl__touch fl__touch--r" data-fl-touch="R" aria-hidden="true"><span><?= e(t('Batteur droit')) ?></span></div>
    <button type="button" class="fl__plunger" data-fl-plunger hidden><i data-fl-gauge></i><b><?= e(t('Lanceur')) ?></b><small><?= e(t('Appuyez, tenez, lâchez')) ?></small></button>
    <button type="button" class="fl__nudge" data-fl-nudge title="<?= e(t('Bousculer (trop : TILT)')) ?>"><?= e(t('Bousculer')) ?></button>
    <div class="fl__players" data-fl-players hidden></div>

    <section class="fl__ov" data-fl-ov="start">
      <div class="fl__card">
        <img class="fl__logo" src="<?= e($data['logo']) ?>" alt="" width="70" height="80">
        <h1 class="fl__h"><?= e($title) ?></h1>
        <p class="fl__lead"><?= e(t('Un vrai flipper en 3D : bumpers, rampe, cibles, multiball. Trois billes pour entrer au tableau d’honneur.')) ?></p>
        <?php if ($welcome): ?><p class="fl__ok"><?= e(t('C’est validé : vous jouez sous le nom {p}.', ['p' => $pseudo])) ?></p><?php endif; ?>
        <div class="fl__seg" role="group" aria-label="<?= e(t('Nombre de joueurs')) ?>">
          <span><?= e(t('Joueurs')) ?></span>
          <?php foreach ([1, 2, 3, 4] as $n): ?><button type="button" data-fl-n="<?= $n ?>" aria-pressed="<?= $n === 1 ? 'true' : 'false' ?>"><?= $n ?></button><?php endforeach; ?>
        </div>
        <button type="button" class="fl__go" data-fl-play><?= e(t('Jouer')) ?></button>
        <div class="fl__links">
          <button type="button" data-fl-go="rules"><?= e(t('Règles du jeu')) ?></button>
          <button type="button" data-fl-go="scores"><?= e(t('Tableau d’honneur')) ?></button>
        </div>
        <p class="fl__keys"><?= e(t('Téléphone : touchez la moitié gauche ou droite de l’écran ; secouez pour bousculer. Ordinateur : clic gauche et clic droit (ou Maj, ← →) pour les batteurs ; bouton de la molette (ou Espace) tenu puis relâché pour le lanceur ; ↑ pour bousculer.')) ?></p>
      </div>
    </section>

    <section class="fl__ov" data-fl-ov="pause" hidden>
      <div class="fl__card">
        <h2 class="fl__h"><?= e(t('Pause')) ?></h2>
        <button type="button" class="fl__go" data-fl-go="resume"><?= e(t('Reprendre')) ?></button>
        <div class="fl__links">
          <button type="button" data-fl-go="rules"><?= e(t('Règles du jeu')) ?></button>
          <button type="button" data-fl-go="quit"><?= e(t('Abandonner la partie')) ?></button>
        </div>
      </div>
    </section>

    <section class="fl__ov" data-fl-ov="rules" hidden>
      <div class="fl__card fl__card--rules">
        <h2 class="fl__h"><?= e(t('Règles du jeu')) ?></h2>
        <dl class="fl__rules">
          <dt><?= e(t('Coup d’envoi')) ?></dt><dd><?= e(t('Tirez le lanceur et faites entrer la bille dans le couloir qui clignote : 500 000 points. Les batteurs déplacent la lumière.')) ?></dd>
          <dt><?= e(t('Couloirs {d}', ['d' => $digits])) ?></dt><dd><?= e(t('Allumez les 4 couloirs du haut (l’année de naissance du club) : le bonus de fin de bille est multiplié, jusqu’à ×5.')) ?></dd>
          <dt><?= e(t('Cibles {l}', ['l' => $letters])) ?></dt><dd><?= e(t('Abattez toutes les lettres : 100 000 points et le centre de formation se rallume. La deuxième fois : une bille en plus.')) ?></dd>
          <dt><?= e(t('Épopée en Coupe')) ?></dt><dd><?= e(t('Chaque passage sur la rampe fait gagner un tour : 32es, 16es, 8es, quarts, demie. En finale, la bille dans la Coupe lance un multiball à 3 billes : chaque rampe vaut un jackpot. Un jackpot, et la Coupe est gagnée.')) ?></dd>
          <dt><?= e(t('La Coupe')) ?></dt><dd><?= e(t('Le trou au centre : 25 000 points et un « Le saviez-vous ? » tiré des fiches du musée.')) ?></dd>
          <dt><?= e(t('Les bumpers')) ?></dt><dd><?= e(t('1 000 points par coup. Tous les 60 coups : « {r} », points doublés pendant 25 secondes.', ['r' => $data['text']['lion']])) ?></dd>
          <dt><?= e(t($data['text']['kickback'])) ?></dt><dd><?= e(t('Allumé, il renvoie en jeu la bille perdue dans le couloir de gauche.')) ?></dd>
          <dt><?= e(t('Bille sauvée')) ?></dt><dd><?= e(t('Pendant les 10 secondes qui suivent le lancer, une bille perdue est rendue.')) ?></dd>
          <dt>TILT</dt><dd><?= e(t('Bousculer aide la bille ; trop bousculer, et c’est le TILT : la bille est perdue, sans bonus.')) ?></dd>
        </dl>
        <button type="button" class="fl__go" data-fl-go="back"><?= e(t('Retour')) ?></button>
      </div>
    </section>

    <section class="fl__ov" data-fl-ov="scores" hidden>
      <div class="fl__card">
        <h2 class="fl__h"><?= e(t('Tableau d’honneur')) ?></h2>
        <div class="fl__boards">
          <div><h3><?= e(t('Cette semaine')) ?></h3><ol class="fl__top10" data-fl-week></ol></div>
          <div><h3><?= e(t('De tous les temps')) ?></h3><ol class="fl__top10" data-fl-all></ol></div>
        </div>
        <p class="fl__note"><?= e(t('Le tableau d’honneur se joue avec le compte du carnet du supporter, sous le pseudo du championnat du club-house. Sans compte, on joue quand même.')) ?></p>
        <button type="button" class="fl__go" data-fl-go="back"><?= e(t('Retour')) ?></button>
      </div>
    </section>

    <section class="fl__ov" data-fl-ov="over" hidden>
      <div class="fl__card">
        <p class="fl__kick"><?= e(t('Fin de partie')) ?></p>
        <b class="fl__final" data-fl-final></b>
        <ol class="fl__multi" data-fl-multi></ol>
        <p class="fl__rec" data-fl-rec></p>
        <p class="fl__rank" data-fl-rank></p>
        <ul class="fl__missions" data-fl-missions></ul>
        <p class="fl__fact"><b><?= e(t('Le saviez-vous ?')) ?></b> <span data-fl-fact></span></p>
        <div class="fl__row">
          <button type="button" class="fl__go" data-fl-play="again"><?= e(t('Rejouer')) ?></button>
          <button type="button" class="fl__go fl__go--ghost" data-fl-share><?= e(t('Partager')) ?></button>
        </div>
        <p class="fl__note" data-fl-shared role="status"></p>
        <form class="fl__join" data-fl-join hidden>
          <p><b><?= e(t('Entrez au tableau d’honneur')) ?></b> — <?= e($data['account'] ? t('choisissez votre pseudo (le même qu’au championnat du club-house).') : t('pas de mot de passe : un lien sécurisé est envoyé à votre e-mail. C’est le même compte que le carnet du supporter.')) ?></p>
          <?php if (!$data['account']): ?><input name="email" type="email" maxlength="120" required autocomplete="email" placeholder="<?= e(t('prenom@exemple.fr')) ?>" aria-label="<?= e(t('Votre e-mail')) ?>"><?php endif; ?>
          <input name="name" maxlength="20" minlength="2" required autocomplete="nickname" placeholder="<?= e(t('Votre pseudo')) ?>" aria-label="<?= e(t('Votre pseudo')) ?>">
          <button type="submit" class="fl__go"><?= e(t('Valider')) ?></button>
          <p class="fl__note" data-fl-joinmsg role="status"></p>
        </form>
        <h3 class="fl__h3"><?= e(t('Tableau d’honneur de la semaine')) ?></h3>
        <ol class="fl__top10" data-fl-top></ol>
        <div class="fl__links"><a href="<?= e(url('/interactif/')) ?>"><?= e(t('Les autres jeux du musée')) ?></a></div>
      </div>
    </section>

    <section class="fl__ov" data-fl-ov="nogl" hidden>
      <div class="fl__card">
        <h2 class="fl__h"><?= e(t('3D indisponible')) ?></h2>
        <p class="fl__lead"><?= e(t('Cet appareil ou ce navigateur n’affiche pas la 3D (WebGL). Essayez avec un navigateur à jour : Chrome, Firefox, Safari ou Edge.')) ?></p>
        <div class="fl__links"><a href="<?= e(url('/interactif/')) ?>"><?= e(t('Les autres jeux du musée')) ?></a></div>
      </div>
    </section>
    <noscript><p class="fl__nojs"><?= e(t('Le flipper a besoin de JavaScript.')) ?></p></noscript>
  </div>
</div>
