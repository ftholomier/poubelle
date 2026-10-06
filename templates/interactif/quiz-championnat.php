<?php
/**
 * Championnat du club-house : classement public d'une saison. Variables : $season, $seasons, $rows
 * (QuizChampionship::ranking), $stats, $account (compte supporter sur cet appareil), $me (pseudo et
 * rang du visiteur, ou null), $welcome (arrivée par le lien de l'e-mail)
 */
use App\Core\View;
use App\Services\QuizChampionship;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$current = $season === QuizChampionship::season();
?>
<?= View::partial('carnet/_head', ['title' => t('Championnat du club-house'), 'intro' => t('Le classement des soirées quiz : chaque partie rapporte des points selon votre rang, que la partie compte 6 ou 30 questions. Saison {s}.', ['s' => $season]), 'crumb' => t('Championnat du club-house'), 'eyebrow' => t('Quiz du club-house')]) ?>
<div class="wrap cnwrap qc" data-qc data-lang="<?= e(\App\Services\I18n::lang()) ?>">
  <script type="application/json" id="qc-i18n"><?= json_encode(['mailed' => t('C’est parti : un lien vient d’être envoyé à cette adresse. Ouvrez-le sur votre téléphone pour valider votre place (pensez aux indésirables).'), 'saved' => t('Pseudo enregistré.'), 'error' => t('Connexion perdue, réessayez.')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <?php if ($welcome && $me): ?><p class="cnwelcome"><?= e(t('C’est validé : ce téléphone vous reconnaîtra à chaque partie, sous le nom {p}.', ['p' => $me['pseudo']])) ?></p><?php endif; ?>
  <div class="qc__grid">
    <section class="cncard qc__table">
      <div class="qc__head">
        <h2 class="cncard__t"><?= e(t('Classement {s}', ['s' => $season])) ?></h2>
        <?php if (count($seasons) > 1): ?>
        <nav class="qc__seasons" aria-label="<?= e(t('Saisons')) ?>"><?php foreach ($seasons as $s): ?><a href="<?= e(url('/interactif/quiz-live/championnat/') . '?saison=' . $s) ?>"<?= $s === $season ? ' aria-current="page"' : '' ?>><?= e($s) ?></a><?php endforeach; ?></nav>
        <?php endif; ?>
      </div>
      <p class="cnnote"><?= e(tn((int) $stats['games'], '{n} partie comptée', '{n} parties comptées', ['n' => $stats['games']])) ?> · <?= e(tn((int) $stats['players'], '{n} joueur classé', '{n} joueurs classés', ['n' => $stats['players']])) ?></p>
      <?php if (!$rows): ?>
      <p class="qc__empty"><?= e($current ? t('Pas encore de partie comptée cette saison : le premier quiz du club-house ouvrira le classement.') : t('Aucune partie comptée cette saison.')) ?></p>
      <?php else: ?>
      <div class="qc__scroll"><table class="qc__rank">
        <thead><tr><th scope="col">#</th><th scope="col"><?= e(t('Joueur')) ?></th><th scope="col" class="n"><?= e(t('Points')) ?></th><th scope="col" class="n"><?= e(t('Parties')) ?></th><th scope="col" class="n"><?= e(t('Victoires')) ?></th><th scope="col" class="n"><?= e(t('Podiums')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="<?= $r['rank'] <= 3 ? 'is-top is-top' . $r['rank'] : '' ?><?= $me && $r['id'] === $me['id'] ? ' is-me' : '' ?>"><td class="r"><?= (int) $r['rank'] ?></td><td><b><?= e($r['pseudo']) ?></b></td><td class="n"><b><?= $fmt($r['pts']) ?></b></td><td class="n"><?= $fmt($r['games']) ?></td><td class="n"><?= $fmt($r['wins']) ?></td><td class="n"><?= $fmt($r['podiums']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>
    <aside class="qc__side">
      <section class="cncard cncard--navy">
        <h2 class="cncard__t"><?= e(t('Mon championnat')) ?></h2>
        <?php if ($me): ?>
          <p class="qc__mine"><b><?= e($me['pseudo']) ?></b><br><?= $me['rank'] ? e(t('{r} · {n} pts en {g} parties', ['r' => ordinal($me['rank']['rank']), 'n' => $me['rank']['pts'], 'g' => $me['rank']['games']])) : e(t('Pas encore de partie comptée cette saison.')) ?></p>
          <?php if (!$me['ok']): ?><p class="ql__hint"><?= e(t('Pour apparaître au classement, ouvrez une fois le lien reçu par e-mail (pensez aux indésirables). Vos points sont gardés d’ici là.')) ?></p><?php endif; ?>
          <form class="qc__form" data-qc-form>
            <label for="qc-name"><?= e(t('Changer de pseudo')) ?></label>
            <div class="cnmail__row"><input id="qc-name" name="name" maxlength="20" minlength="2" required value="<?= e($me['pseudo']) ?>"><button class="btn btn--yellow" type="submit"><?= e(t('Enregistrer')) ?></button></div>
            <p class="cnmail__msg" data-qc-msg role="status"></p>
          </form>
          <p class="cnnote"><?= e(t('Ce téléphone vous reconnaît : rejoignez une partie avec son code, vous jouez sous ce pseudo. Autre appareil : redemandez un lien avec votre e-mail.')) ?> <a href="<?= e(url('/carnet/')) ?>"><?= e(t('Mon carnet du supporter')) ?></a></p>
        <?php else: ?>
          <p><?= e($account ? t('Choisissez votre pseudo : vos prochaines parties compteront.') : t('Entrez au championnat avec votre e-mail et un pseudo, ou retrouvez votre place sur ce téléphone.')) ?></p>
          <form class="qc__form" data-qc-form novalidate>
            <?php if (!$account): ?>
            <label for="qc-email"><?= e(t('Votre e-mail')) ?></label>
            <input id="qc-email" name="email" type="email" maxlength="120" required autocomplete="email" placeholder="<?= e(t('prenom@exemple.fr')) ?>">
            <?php endif; ?>
            <label for="qc-name"><?= e(t('Votre pseudo')) ?></label>
            <div class="cnmail__row"><input id="qc-name" name="name" maxlength="20" minlength="2" autocomplete="nickname" placeholder="<?= e(t('ex. Lionceau25')) ?>"><button class="btn btn--yellow" type="submit"><?= e($account ? t('Enregistrer') : t('Recevoir mon lien')) ?></button></div>
            <p class="cnmail__msg" data-qc-msg role="status"></p>
          </form>
          <?php if (!$account): ?><p class="cnnote"><?= e(t('Pas de mot de passe : un lien sécurisé, envoyé à votre e-mail, garde votre place sur votre téléphone. Déjà inscrit ? Mettez la même adresse : un nouveau lien vous est envoyé. C’est le même compte que le carnet du supporter ; votre adresse ne sert qu’à cela.')) ?></p><?php endif; ?>
        <?php endif; ?>
      </section>
      <section class="cncard">
        <h2 class="cncard__t"><?= e(t('Le barème')) ?></h2>
        <ul class="qc__scale">
          <?php foreach (QuizChampionship::SCALE as $i => $p): ?><li><?= e(t('{r} : {n} pts', ['r' => ordinal($i + 1), 'n' => $p + 1])) ?></li><?php endforeach; ?>
          <li><?= e(t('Ensuite : 1 pt de participation')) ?></li>
        </ul>
        <p class="cnnote"><?= e(t('Une partie compte à partir de {n} joueurs. À égalité de points : le plus de victoires, puis de podiums, puis le moins de parties jouées. Nouveau classement chaque saison, le 1er août.', ['n' => QuizChampionship::MIN_PLAYERS])) ?></p>
      </section>
    </aside>
  </div>
</div>
