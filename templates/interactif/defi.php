<?php
/**
 * Défi du jour. Variables : $date, $period (jour|mois|saison), $value, $rows (DailyQuiz::ranking),
 * $account (compte supporter sur cet appareil), $me (pseudo, résultat du jour, rang, série ; ou null),
 * $welcome (arrivée par le lien de l'e-mail), $players (classés aujourd'hui)
 */
use App\Core\View;
use App\Services\DailyQuiz;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$labels = ['jour' => t('Aujourd’hui'), 'mois' => t('Ce mois-ci'), 'saison' => t('La saison')];
$base = url('/interactif/defi/');
$share = base_url() . $base;
$i18n = [
    'qn' => t('Question {i} / {n}'), 'good' => t('Bonne réponse !'), 'bad' => t('Raté…'), 'late' => t('Temps écoulé'),
    'pts' => t('+{n} points'), 'next' => t('Question suivante'), 'end' => t('Voir mon résultat'), 'score' => t('{n} points'),
    'error' => t('Connexion perdue, réessayez.'), 'mailed' => t('Un lien vient d’être envoyé à cette adresse : ouvrez-le sur ce téléphone pour jouer le défi du jour au classement (pensez aux indésirables).'),
    'copied' => t('Résultat copié : collez-le où vous voulez.'), 'shareTitle' => t('Le défi du jour Sochaux Rétro'),
    'shareText' => t('Défi du jour Sochaux Rétro · {d}'), 'goodOf' => t('{g}/{n} bonnes réponses'), 'guest' => t('Partie en invité : elle n’entre pas au classement.'),
];
?>
<?= View::partial('carnet/_head', ['title' => t('Le défi du jour'), 'intro' => t('Dix questions sur l’histoire du FCSM, les mêmes pour tout le monde aujourd’hui. Un seul essai par jour : plus vous répondez vite, plus vous marquez.'), 'crumb' => t('Le défi du jour'), 'eyebrow' => date_fr($date)]) ?>
<div class="wrap cnwrap qc df-wrap">
  <script type="application/json" id="df-i18n"><?= json_encode($i18n + ['date' => date_fr($date), 'url' => $share], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <?php if ($welcome && $me): ?><p class="cnwelcome"><?= e(t('C’est validé : ce téléphone vous reconnaîtra chaque jour, sous le nom {p}.', ['p' => $me['pseudo']])) ?></p><?php endif; ?>
  <div class="qc__grid">
    <section class="df" data-df data-lang="<?= e(\App\Services\I18n::lang()) ?>" data-member="<?= $me ? '1' : '0' ?>" data-done="<?= $me && $me['result'] ? '1' : '0' ?>">
      <div class="df__intro" data-df-step="intro"<?= $me && $me['result'] ? ' hidden' : '' ?>>
        <?php if ($me): ?>
          <p class="ql__me"><?= e(t('Vous jouez sous le nom')) ?> <b><?= e($me['pseudo']) ?></b><?php if ($me['streak']['cur'] > 0): ?> · <?= e(tn($me['streak']['cur'], '{n} jour d’affilée', '{n} jours d’affilée')) ?><?php endif; ?></p>
          <button type="button" class="qbtn" data-df-start><?= e(t('Lancer le défi')) ?></button>
        <?php else: ?>
          <form class="ql__form" data-df-form>
            <?php if (!$account): ?>
            <label for="df-email"><?= e(t('Votre e-mail')) ?></label>
            <input id="df-email" name="email" type="email" maxlength="120" required autocomplete="email" placeholder="<?= e(t('prenom@exemple.fr')) ?>">
            <?php endif; ?>
            <label for="df-name"><?= e(t('Votre pseudo')) ?></label>
            <input id="df-name" name="name" maxlength="20" minlength="2" required autocomplete="nickname" placeholder="<?= e(t('ex. Lionceau25')) ?>">
            <button type="submit" class="qbtn"><?= e(t('Jouer pour le classement')) ?></button>
            <p class="ql__err" data-df-err role="alert" hidden></p>
            <p class="ql__hint"><?= e($account ? t('Ce pseudo sera aussi le vôtre au championnat du club-house.') : t('Pas de mot de passe : un lien sécurisé, envoyé à votre e-mail, garde votre place sur ce téléphone et vous la rend sur un autre. C’est le même compte que le carnet du supporter et le championnat du club-house.')) ?></p>
          </form>
          <button type="button" class="ql__link df__guest" data-df-guest><?= e(t('Jouer sans compte (sans classement)')) ?></button>
        <?php endif; ?>
        <ul class="df__rules">
          <li><?= e(t('10 questions, 20 secondes chacune : de 500 à 1 000 points par bonne réponse selon votre rapidité.')) ?></li>
          <li><?= e(t('Les mêmes questions pour tout le monde, nouvelles chaque jour à minuit.')) ?></li>
          <li><?= e(t('Un seul essai par jour : pas de retour en arrière, le temps tourne même si vous quittez la page.')) ?></li>
        </ul>
      </div>
      <div class="df__play" data-df-step="play" hidden>
        <p class="ql__num" data-df-num></p>
        <h2 class="ql__q" data-df-q aria-live="polite"></h2>
        <div class="ql__timer" data-df-timer><span></span></div>
        <div class="ql__answers" data-df-answers></div>
        <div class="df__verdict" data-df-verdict hidden>
          <p class="ql__msg" data-df-msg></p>
          <p class="df__fact" data-df-fact></p>
          <button type="button" class="qbtn qbtn--sm" data-df-next></button>
        </div>
      </div>
      <div class="df__end" data-df-step="end"<?= $me && $me['result'] ? '' : ' hidden' ?>>
        <p class="ql__num"><?= e(t('Votre défi du jour')) ?></p>
        <b class="ql__final" data-df-score><?= $me && $me['result'] ? e(t('{n} points', ['n' => $fmt($me['result']['pts'])])) : '' ?></b>
        <p class="df__good" data-df-good><?= $me && $me['result'] ? e(t('{g}/{n} bonnes réponses', ['g' => $me['result']['good'], 'n' => DailyQuiz::COUNT])) : '' ?></p>
        <p class="df__grid" data-df-grid aria-hidden="true"><?php if ($me && $me['result']): ?><?php foreach (str_split($me['result']['grid']) as $g): ?><span class="<?= $g === '1' ? 'is-good' : '' ?>"></span><?php endforeach; ?><?php endif; ?></p>
        <p class="df__rank" data-df-rank><?= $me && $me['rank'] ? e(t('{r} sur {n} aujourd’hui', ['r' => ordinal($me['rank']['rank']), 'n' => $players])) : '' ?><?= $me && $me['streak']['cur'] > 1 ? ' · ' . e(t('{n} jours d’affilée', ['n' => $me['streak']['cur']])) : '' ?></p>
        <button type="button" class="qbtn qbtn--sm" data-df-share data-grid="<?= e($me['result']['grid'] ?? '') ?>" data-pts="<?= (int) ($me['result']['pts'] ?? 0) ?>"><?= e(t('Partager mon résultat')) ?></button>
        <p class="ql__hint" data-df-share-msg role="status"></p>
        <p class="df__tomorrow"><?= e(t('Nouveau défi demain, à minuit.')) ?></p>
      </div>
    </section>
    <aside class="qc__side">
      <section class="cncard qc__table">
        <div class="qc__head">
          <h2 class="cncard__t"><?= e(t('Classement')) ?></h2>
          <nav class="qc__seasons" aria-label="<?= e(t('Classement')) ?>"><?php foreach ($labels as $k => $l): ?><a href="<?= e($base . ($k === 'jour' ? '' : '?classement=' . $k)) ?>"<?= $k === $period ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></nav>
        </div>
        <?php if (!$rows): ?>
          <p class="qc__empty"><?= e(t('Personne encore : à vous d’ouvrir le classement !')) ?></p>
        <?php else: ?>
        <div class="qc__scroll"><table class="qc__rank">
          <thead><tr><th scope="col">#</th><th scope="col"><?= e(t('Joueur')) ?></th><th scope="col" class="n"><?= e(t('Points')) ?></th><th scope="col" class="n"><?= e($period === 'jour' ? t('Bonnes') : t('Jours')) ?></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr class="<?= $r['rank'] <= 3 ? 'is-top is-top' . $r['rank'] : '' ?><?= $me && $r['id'] === $me['id'] ? ' is-me' : '' ?>"><td class="r"><?= (int) $r['rank'] ?></td><td><b><?= e($r['pseudo']) ?></b></td><td class="n"><b><?= $fmt($r['pts']) ?></b></td><td class="n"><?= $period === 'jour' ? (int) $r['good'] . '/' . DailyQuiz::COUNT : $fmt($r['days']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php endif; ?>
        <p class="cnnote"><?= e(t('Les joueurs avec un compte entrent au classement. Le championnat du club-house, lui, se joue en salle :')) ?> <a href="<?= e(url('/interactif/quiz-live/championnat/')) ?>"><?= e(t('voir le championnat')) ?></a>.</p>
      </section>
    </aside>
  </div>
</div>
