<?php
/**
 * Le babyfoot du musée (plein écran, sans en-tête du site). Variables : $data (réglages du jeu pour
 * babyfoot.js), $title, $welcome (arrivée par le lien de l'e-mail), $pseudo (compte supporter ou null).
 */
$ui = $data['ui'];
$words = preg_split('/\s+/u', trim($title), 2);
$hist = $data['history'];
?>
<div class="bf" data-babyfoot>
  <script type="application/json" id="bf-data"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <div class="bf__stage" data-bf-stage>
    <canvas class="bf__canvas" data-bf-canvas aria-label="<?= e(t('La table de babyfoot')) ?>"></canvas>

    <div class="bf__hud" data-bf-hud hidden>
      <button type="button" class="bf__ic bf__pause" data-bf-pause aria-label="<?= e(t('Pause')) ?>" title="<?= e(t('Pause')) ?>">❚❚</button>
      <div class="bf__board" aria-live="polite">
        <b class="bf__team" data-bf-name="0"></b><span class="bf__sc" data-bf-score>0 – 0</span><b class="bf__team" data-bf-name="1"></b><i class="bf__clock" data-bf-clock>0’00</i>
      </div>
      <div class="bf__tr">
        <button type="button" class="bf__ic" data-bf-sound aria-pressed="true" aria-label="<?= e(t('Son')) ?>" title="<?= e($ui['soundOff']) ?>"><span aria-hidden="true">🔊</span></button>
        <button type="button" class="bf__cam" data-bf-cam title="<?= e($ui['camera']) ?>"><span aria-hidden="true">🎥</span> <?= e($ui['camera']) ?></button>
      </div>
      <div class="bf__zone bf__zone--glide" data-bf-glide="0" aria-hidden="true"><div class="bf__ring"><b data-bf-arrow>↕</b><span><?= e($ui['glide']) ?></span></div></div>
      <div class="bf__zone bf__zone--shoot" data-bf-shootzone="0" aria-hidden="true"></div>
      <button type="button" class="bf__shoot" data-bf-shoot="0"><i data-bf-gauge="0"></i><b><?= e($ui['shoot']) ?></b><small data-bf-shootsub="0"><?= e($ui['shootSimple']) ?></small></button>
      <div class="bf__zone bf__zone--glide bf__zone--p2" data-bf-glide="1" aria-hidden="true" hidden><div class="bf__ring"><b data-bf-arrow>↕</b><span><?= e($ui['glide']) ?></span></div></div>
      <button type="button" class="bf__shoot bf__shoot--p2" data-bf-shoot="1" hidden><i data-bf-gauge="1"></i><b><?= e($ui['shoot']) ?></b><small data-bf-shootsub="1"><?= e($ui['p2']) ?></small></button>
      <div class="bf__banner" data-bf-banner></div>
      <div class="bf__replay" data-bf-replay hidden><span>● <?= e($ui['replay']) ?></span><button type="button" data-bf-skip><?= e($ui['skip']) ?> ›</button><b class="bf__replaygoal"><?= e($ui['goal']) ?></b></div>
      <div class="bf__toast" data-bf-toast></div>
    </div>

    <section class="bf__ov bf__ov--home" data-bf-ov="start">
      <div class="bf__home">
        <a class="bf__back" href="<?= e(url('/interactif/')) ?>" aria-label="<?= e(t('Retour à Interactif')) ?>">‹ <?= e(t('Interactif')) ?></a>
        <div class="bf__brand">
          <img class="bf__logo" src="<?= e($data['logo']) ?>" alt="" width="96" height="108">
          <h1 class="bf__h1"><span><?= e($words[0]) ?></span><?php if (isset($words[1])): ?><small><?= e($words[1]) ?></small><?php endif; ?></h1>
          <p class="bf__tag"><?= e(t('Le babyfoot du musée {s}', ['s' => $data['site']])) ?></p>
          <?php if ($welcome): ?><p class="bf__ok"><?= e(t('C’est validé : vous jouez sous le nom {p}.', ['p' => $pseudo])) ?></p><?php endif; ?>
        </div>
        <div class="bf__modes" role="radiogroup" aria-label="<?= e(t('Façon de jouer')) ?>">
          <div class="bf__mode" data-bf-mode="ai" role="radio" aria-checked="true" tabindex="0">
            <span class="bf__mi" aria-hidden="true">🖥</span>
            <h2><?= e(t('Contre l’ordi')) ?></h2>
            <p><?= e(t('Match rapide contre l’ordinateur.')) ?></p>
            <div class="bf__chips" role="group" aria-label="<?= e(t('Niveau')) ?>">
              <?php foreach ($data['levels'] as $i => $l): ?><button type="button" data-bf-level="<?= $i ?>" aria-pressed="<?= $i === 1 ? 'true' : 'false' ?>"><?= e($l) ?></button><?php endforeach; ?>
            </div>
          </div>
          <div class="bf__mode" data-bf-mode="duo" role="radio" aria-checked="false" tabindex="0">
            <span class="bf__mi" aria-hidden="true">👥</span>
            <h2><?= e(t('À deux')) ?></h2>
            <p><?= e(t('Sur le même écran : tablette ou ordinateur posé entre vous.')) ?></p>
            <span class="bf__pill"><?= e(t('Chacun son côté')) ?></span>
          </div>
          <?php if ($hist): ?>
          <div class="bf__mode bf__mode--hist" data-bf-mode="histoire" role="radio" aria-checked="false" tabindex="0">
            <span class="bf__mi" aria-hidden="true">🏆</span>
            <h2><?= e(t('Rejoue l’histoire')) ?></h2>
            <p><?= e(t('Un grand match du musée, les vrais noms sur les figurines.')) ?></p>
            <label class="bf__sel"><span class="sr-only"><?= e(t('Match à rejouer')) ?></span>
              <select data-bf-hist>
                <?php foreach ($hist as $i => $m): ?><option value="<?= $i ?>" title="<?= e($m['title']) ?>"<?= str_starts_with($m['label'], t('Finale')) ? ' selected' : '' ?>><?= e($m['label']) ?></option><?php endforeach; ?>
              </select>
            </label>
          </div>
          <?php endif; ?>
        </div>
        <div class="bf__bar">
          <div class="bf__opp">
            <small><?= e(t('Adversaire')) ?></small>
            <div class="bf__oppsel">
              <button type="button" data-bf-opp="-1" aria-label="<?= e(t('Adversaire précédent')) ?>">◀</button>
              <span class="bf__badge" data-bf-badge></span><b data-bf-oppname></b>
              <button type="button" data-bf-opp="1" aria-label="<?= e(t('Adversaire suivant')) ?>">▶</button>
            </div>
          </div>
          <div class="bf__dur">
            <small><?= e(t('Durée')) ?></small>
            <div class="bf__seg" role="group" aria-label="<?= e(t('Durée')) ?>">
              <button type="button" data-bf-format="temps" aria-pressed="true"><?= e(t('2 × 3 min')) ?></button>
              <button type="button" data-bf-format="buts" aria-pressed="false"><?= e(t('Premier à 5')) ?></button>
            </div>
          </div>
          <div class="bf__dur">
            <small><?= e(t('Commandes')) ?></small>
            <div class="bf__seg" role="group" aria-label="<?= e(t('Commandes')) ?>">
              <button type="button" data-bf-controls="simple" aria-pressed="true" title="<?= e(t('Les barres se placent toutes seules : touchez pour tirer.')) ?>"><?= e(t('Simples')) ?></button>
              <button type="button" data-bf-controls="expert" aria-pressed="false" title="<?= e(t('Vous glissez vous-même la barre et dosez la force du tir.')) ?>"><?= e(t('Expert')) ?></button>
            </div>
          </div>
          <button type="button" class="bf__go" data-bf-play><?= e(t('Jouer')) ?> ▶</button>
        </div>
        <div class="bf__links">
          <button type="button" data-bf-go="rules"><?= e(t('Comment jouer')) ?></button>
          <button type="button" data-bf-go="scores"><?= e(t('Tableau d’honneur')) ?></button>
          <button type="button" data-bf-go="full"><?= e(t('Plein écran')) ?></button>
        </div>
      </div>
    </section>

    <section class="bf__ov" data-bf-ov="pause" hidden>
      <div class="bf__card">
        <h2 class="bf__h"><?= e(t('Pause')) ?></h2>
        <button type="button" class="bf__go" data-bf-go="resume"><?= e(t('Reprendre')) ?></button>
        <div class="bf__links">
          <button type="button" data-bf-go="rules"><?= e(t('Comment jouer')) ?></button>
          <button type="button" data-bf-go="quit"><?= e(t('Abandonner le match')) ?></button>
        </div>
      </div>
    </section>

    <section class="bf__ov" data-bf-ov="rules" hidden>
      <div class="bf__card bf__card--rules">
        <h2 class="bf__h"><?= e(t('Comment jouer')) ?></h2>
        <dl class="bf__rules">
          <dt><?= e(t('Commandes simples (d’office)')) ?></dt><dd><?= e(t('Vos barres se placent toutes seules sous la balle. Touchez la table (ou cliquez) là où vous visez dans le but : la figurine qui a la balle tire de ce côté. Le bouton « Tir », Espace ou Entrée tirent aussi, en visant le coin libre. Pas besoin d’être pile à l’heure : touchez juste avant que la balle arrive.')) ?></dd>
          <dt><?= e(t('Commandes expert')) ?></dt><dd><?= e(t('Téléphone : pouce gauche pour glisser la barre qui s’allume, pouce droit sur « Tir » (appui long = plus fort). Ordinateur : la figurine suit la souris, clic gauche pour tirer (tenu = plus fort) ; au clavier ↑ ↓ (ou Z S, W S) et Espace. Plus difficile, pour les habitués.')) ?></dd>
          <dt><?= e(t('Ordinateur')) ?></dt><dd><?= e(t('C pour changer de caméra, Échap pour la pause.')) ?></dd>
          <dt><?= e(t('À deux')) ?></dt><dd><?= e(t('Chacun garde son côté de l’écran : à gauche le joueur 1 ({a}), à droite le joueur 2. Au clavier : Z S (ou W S) et D pour le joueur 1, ↑ ↓ et ← pour le joueur 2.', ['a' => $data['us']['short']])) ?></dd>
          <dt><?= e(t('Les barres')) ?></dt><dd><?= e(t('La barre allumée est celle qui peut jouer la balle : elle change toute seule. Les barres se lèvent pour laisser passer les tirs de leur équipe. En expert, décalez la figurine au moment du tir pour croiser.')) ?></dd>
          <dt><?= e(t('Le match')) ?></dt><dd><?= e(t('Deux mi-temps de 3 minutes, ou le premier à 5 buts. Après un but, l’équipe qui l’a encaissé engage. Une balle immobile hors de portée est rendue au gardien, ou remise au centre.')) ?></dd>
          <dt><?= e(t('Rejoue l’histoire')) ?></dt><dd><?= e(t('Un grand match du musée, avec la composition du jour au dos des figurines. Coupe à égalité au bout du temps : but en or. À la fin, le vrai résultat et le lien vers la fiche du match.')) ?></dd>
          <dt><?= e(t('Tableau d’honneur')) ?></dt><dd><?= e(t('Les victoires contre l’ordinateur de la semaine, niveau par niveau, sous le pseudo du carnet du supporter. Facultatif : sans compte, on joue quand même.')) ?></dd>
        </dl>
        <button type="button" class="bf__go" data-bf-go="back"><?= e(t('Retour')) ?></button>
      </div>
    </section>

    <section class="bf__ov" data-bf-ov="scores" hidden>
      <div class="bf__card">
        <h2 class="bf__h"><?= e(t('Tableau d’honneur')) ?></h2>
        <div class="bf__seg bf__seg--lv" role="group" aria-label="<?= e(t('Niveau')) ?>">
          <?php foreach ($data['levels'] as $i => $l): ?><button type="button" data-bf-board="<?= $i ?>" aria-pressed="<?= $i === 1 ? 'true' : 'false' ?>"><?= e($l) ?></button><?php endforeach; ?>
        </div>
        <div class="bf__boards">
          <div><h3><?= e(t('Cette semaine')) ?></h3><ol class="bf__top" data-bf-week></ol></div>
          <div><h3><?= e(t('De tous les temps')) ?></h3><ol class="bf__top" data-bf-all></ol></div>
        </div>
        <p class="bf__note"><?= e(t('Les victoires contre l’ordinateur comptent, sous le pseudo du championnat du club-house (compte du carnet du supporter). Sans compte, on joue quand même.')) ?></p>
        <button type="button" class="bf__go" data-bf-go="back"><?= e(t('Retour')) ?></button>
      </div>
    </section>

    <section class="bf__ov bf__ov--end" data-bf-ov="over" hidden>
      <canvas class="bf__confetti" data-bf-confetti aria-hidden="true"></canvas>
      <div class="bf__end">
        <div class="bf__res">
          <p class="bf__kick" data-bf-kick></p>
          <b class="bf__verdict" data-bf-verdict></b>
          <div class="bf__final"><b data-bf-fname="0"></b><span data-bf-fscore></span><b data-bf-fname="1"></b></div>
          <p class="bf__real" data-bf-real></p>
          <ul class="bf__stats" data-bf-stats></ul>
          <div class="bf__row">
            <button type="button" class="bf__go" data-bf-play="again"><?= e(t('Revanche')) ?></button>
            <button type="button" class="bf__go bf__go--ghost" data-bf-share><?= e(t('Partager')) ?></button>
            <button type="button" class="bf__go bf__go--ghost" data-bf-go="home"><?= e(t('Autre match')) ?></button>
          </div>
          <p class="bf__note" data-bf-shared role="status"></p>
        </div>
        <aside class="bf__hof" data-bf-hof>
          <h3><?= e(t('Tableau d’honneur')) ?></h3>
          <p class="bf__hofsub" data-bf-hofsub></p>
          <ol class="bf__top" data-bf-top></ol>
          <p class="bf__rank" data-bf-rank></p>
          <form class="bf__join" data-bf-join hidden>
            <p><b><?= e(t('Entrez au tableau d’honneur')) ?></b> — <?= e($data['account'] ? t('choisissez votre pseudo (le même qu’au championnat du club-house).') : t('pas de mot de passe : un lien sécurisé est envoyé à votre e-mail. C’est le même compte que le carnet du supporter.')) ?></p>
            <?php if (!$data['account']): ?><input name="email" type="email" maxlength="120" required autocomplete="email" placeholder="<?= e(t('prenom@exemple.fr')) ?>" aria-label="<?= e(t('Votre e-mail')) ?>"><?php endif; ?>
            <input name="name" maxlength="20" minlength="2" required autocomplete="nickname" placeholder="<?= e(t('Votre pseudo')) ?>" aria-label="<?= e(t('Votre pseudo')) ?>">
            <button type="submit" class="bf__go"><?= e(t('Valider')) ?></button>
            <p class="bf__note" data-bf-joinmsg role="status"></p>
          </form>
          <p class="bf__note"><?= e(t('Facultatif : on y entre avec le pseudo du carnet du supporter. Sans compte, on joue quand même.')) ?></p>
        </aside>
      </div>
    </section>

    <section class="bf__ov" data-bf-ov="nogl" hidden>
      <div class="bf__card">
        <h2 class="bf__h"><?= e(t('3D indisponible')) ?></h2>
        <p class="bf__lead"><?= e(t('Cet appareil ou ce navigateur n’affiche pas la 3D (WebGL). Essayez avec un navigateur à jour : Chrome, Firefox, Safari ou Edge.')) ?></p>
        <div class="bf__links"><a href="<?= e(url('/interactif/')) ?>"><?= e(t('Les autres jeux du musée')) ?></a></div>
      </div>
    </section>
    <div class="bf__rotate" data-bf-rotate hidden><span aria-hidden="true">⟲</span> <?= e($ui['rotate']) ?> <button type="button" data-bf-rotok aria-label="<?= e(t('Fermer')) ?>">×</button></div>
    <noscript><p class="bf__nojs"><?= e(t('Le babyfoot a besoin de JavaScript.')) ?></p></noscript>
  </div>
</div>
