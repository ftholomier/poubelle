<?php
/**
 * Le flipper du musée « au stade » (plein écran, sans en-tête du site). Variables : $data (réglages
 * du jeu pour flipper.js), $title, $welcome (arrivée par le lien de l'e-mail), $pseudo (compte
 * supporter ou null).
 */
$ui = $data['ui'];
$lb = $data['labels'];
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
        <p class="fl__lead"><?= e(t('Un flipper en 3D au milieu du stade. Chaque partie est une épopée en Coupe contre des adversaires de l’histoire du club : marquez sur le mini-terrain, chaque bille perdue est un but encaissé. Trois billes pour aller le plus loin possible.')) ?></p>
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
        <p class="fl__keys"><?= e(t('Téléphone : touchez la moitié gauche ou droite de l’écran ; secouez pour bousculer. Ordinateur : clic gauche et clic droit (ou Maj, ← →) pour les batteurs ; bouton de la molette tenu puis relâché pour le lanceur, puis cliqué pour bourrer pendant la partie ; au clavier, Espace pour le lanceur et ↑ pour bourrer.')) ?></p>
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
          <dt><?= e(t('Le match')) ?></dt><dd><?= e(t('Chaque partie est une épopée en Coupe (8es, quarts, demie, finale) contre des adversaires tirés de l’histoire du club. Un match dure deux mi-temps de 45 minutes : le chrono, en bas à gauche, tourne quand une bille est en jeu. Les buts se marquent sur le mini-terrain ; chaque bille perdue est un but pour l’adversaire.')) ?></dd>
          <dt><?= e(t('Le coup de sifflet final')) ?></dt><dd><?= e(t('Victoire : tour suivant et 1 000 000 de points par but d’écart (la finale gagnée rapporte la Coupe et 5 000 000 de points). Match nul : 250 000 points, le match est à rejouer. Défaite : retour en 8es de finale, contre un nouvel adversaire.')) ?></dd>
          <dt><?= e(t('Le mini-terrain')) ?></dt><dd><?= e(t('La rampe {p} et l’accès du milieu montent la bille sur le mini-terrain, au fond du plateau. Ses deux petits batteurs bougent avec ceux du bas : passez les trois lignes de joueurs adverses qui coulissent et trompez le gardien. Chaque but rapporte 250 000 points, puis davantage, et monte le multiplicateur de bonus. La rampe {d} ramène la bille vers le batteur gauche.', ['p' => $lb['passe'], 'd' => $lb['dribble']])) ?></dd>
          <dt><?= e(t('Passes et COMBO')) ?></dt><dd><?= e(t('Les rampes {c} et {b} et les couloirs {u} sont des passes : chacune élimine un joueur adverse (allumé sur les hexagones du milieu) et remplit la jauge COMBO du tablier. Cinq passes : COMBO, 250 000 points et davantage à chaque fois. Les deux couloirs {u} pris en moins de 5 secondes : 100 000 points.', ['c' => $lb['centre'], 'b' => $lb['ballonLong'], 'u' => $lb['onetwo']])) ?></dd>
          <dt><?= e(t('Attaque')) ?></dt><dd><?= e(t('La bille qui passe dans un couloir intérieur, au-dessus des batteurs, lance l’attaque : pendant 6 secondes, chaque passe élimine deux joueurs.')) ?></dd>
          <dt><?= e(t('Poches et multiball')) ?></dt><dd><?= e(t('Une ligne adverse éliminée (attaquants, milieux, défenseurs) allume sa poche : attaquant à gauche, milieu au centre, défenseur à droite. La bille y est alors verrouillée. Trois billes verrouillées : multiball, et chaque but devient un jackpot ; les rampes {c} et {b} en rapportent aussi.', ['c' => $lb['centre'], 'b' => $lb['ballonLong']])) ?></dd>
          <dt><?= e(t('Geste technique')) ?></dt><dd><?= e(t('Le trou sur le flanc gauche : 50 000 points, la récompense allumée au centre du plateau (jackpot, super jackpot, coup d’envoi, bille en plus, temps additionnel) et un « Le saviez-vous ? » tiré des fiches du musée. La bille ressort droit dans la rampe {p}.', ['p' => $lb['passe']])) ?></dd>
          <dt><?= e(t('Super jackpot')) ?></dt><dd><?= e(t('La bille dans un couloir extérieur allume le super jackpot : le but suivant rapporte 5 000 000 de points de plus.')) ?></dd>
          <dt><?= e(t('Le souterrain')) ?></dt><dd><?= e(t('La bille lancée plonge dans un souterrain en haut du couloir de lancement et ressort tout en haut du plateau, par le trou droit du mini-terrain : à vous de jouer avec les petits batteurs.')) ?></dd>
          <dt><?= e(t('Coup d’envoi')) ?></dt><dd><?= e(t('Avant de lancer, les batteurs choisissent le tir qui clignote ({c}, {b} ou l’accès du mini-terrain). Réussissez-le dans les 12 secondes qui suivent la sortie du souterrain : 500 000 points.', ['c' => $lb['centre'], 'b' => $lb['ballonLong']])) ?></dd>
          <dt><?= e(t('La roulette')) ?></dt><dd><?= e(t('Dans le couloir {u} du milieu, la roulette tourne au passage de la bille : 2 000 points par tour. Plus la bille va vite, plus elle tourne.', ['u' => $lb['onetwo']])) ?></dd>
          <dt><?= e(t('Multiplicateurs')) ?></dt><dd><?= e(t('Chaque but monte le multiplicateur du couloir de lancement : 2X, 4X, 6X, 8X, puis 10X, qui reste acquis pour la bille suivante (HOLD). Il multiplie le bonus de fin de bille : rampes, passes, bumpers et buts.')) ?></dd>
          <dt><?= e(t('Batteurs {t}', ['t' => $lb['tackle']])) ?></dt><dd><?= e(t('Les deux petits batteurs tout en bas, au pied des couloirs extérieurs, bougent avec les grands : un tacle bien placé renvoie la bille en jeu.')) ?></dd>
          <dt><?= e(t('Les bumpers')) ?></dt><dd><?= e(t('1 000 points par coup. Tous les 50 coups : « {r} », points doublés pendant 25 secondes.', ['r' => $data['text']['lion']])) ?></dd>
          <dt><?= e(t('Bille sauvée')) ?></dt><dd><?= e(t('Pendant les 14 secondes qui suivent le lancer, une bille perdue est rendue (sans but encaissé).')) ?></dd>
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
        <p class="fl__kick"><?= e(t('Fin de la partie')) ?></p>
        <div class="fl__result" data-fl-result hidden><b data-fl-rkey></b><ol class="fl__matches" data-fl-matches></ol></div>
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
