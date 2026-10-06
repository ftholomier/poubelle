<?php
/**
 * Interactif › Quiz du club-house. Variables : $games (QuizLive::all), $new (code de la partie qui vient
 * d'être créée), $season, $ranking (QuizChampionship::ranking), $stats, $banned (joueurs exclus)
 */
use App\Admin\QuizClub;

$phases = ['lobby' => ['Salle d’attente', 'info'], 'question' => ['Question en cours', 'ok'], 'reveal' => ['Réponse affichée', 'ok'], 'board' => ['Classement', 'ok'], 'end' => ['Terminée', 'brouillon']];
?>
<p class="alert alert--info" style="margin:0">Une soirée quiz au club-house ou au local de l’association : la télé (ou le vidéoprojecteur) affiche les questions, chacun répond depuis son téléphone en scannant le QR code. Classement après chaque question, podium à la fin. Sans inscription ni application, sans IA : les questions viennent du quiz du site et des fiches de match (score, année, adversaire, buteur).</p>

<div class="cols cols--wide">
  <section class="card">
    <div class="card__head"><h2 class="card__t">Nouvelle partie</h2></div>
    <form method="post" action="/admin/quiz-club-house" class="card__body stack" style="gap:14px">
      <?= csrf_field() ?><input type="hidden" name="action" value="creer">
      <div class="fgrid">
        <div class="f"><span class="f__k"><label for="ql-n">Questions</label></span>
          <select id="ql-n" class="in" name="questions"><?php foreach ([6, 10, 12, 15, 20, 25, 30] as $n): ?><option value="<?= $n ?>"<?= $n === 12 ? ' selected' : '' ?>><?= $n ?> questions</option><?php endforeach; ?></select>
          <span class="f__help">12 questions ≈ 15 minutes.</span></div>
        <div class="f"><span class="f__k"><label for="ql-d">Temps pour répondre</label></span>
          <select id="ql-d" class="in" name="duree"><?php foreach (\App\Services\QuizLive::DURATIONS as $d): ?><option value="<?= $d ?>"<?= $d === 20 ? ' selected' : '' ?>><?= $d ?> secondes</option><?php endforeach; ?></select></div>
      </div>
      <div class="fgrid">
        <div class="f"><span class="f__k"><label for="ql-o">Origine des questions</label></span>
          <select id="ql-o" class="in" name="origine">
            <option value="mix" selected>Moitié quiz du site, moitié fiches de match</option>
            <option value="fiches">Fiches de match seulement (toujours nouvelles)</option>
            <option value="site">Quiz du site seulement</option>
          </select>
          <span class="f__help">Les questions des fiches sont tirées au hasard parmi les grands matchs : chaque partie est différente.</span></div>
        <div class="f"><span class="f__k"><label for="ql-l">Langue</label></span>
          <select id="ql-l" class="in" name="langue"><option value="fr" selected>Français</option><option value="en">Anglais</option></select></div>
      </div>
      <label class="small"><input type="checkbox" name="amicale" value="1"> Partie amicale : elle ne compte pas au championnat (essai, démonstration)</label>
      <div><button type="submit" class="btn btn--navy">Créer la partie</button></div>
    </form>
  </section>
  <section class="card">
    <div class="card__head"><h2 class="card__t">Le soir du quiz</h2></div>
    <div class="card__body">
      <ol class="small" style="margin:0;padding-left:18px;line-height:1.7">
        <li>Sur l’ordinateur relié à la télé, cliquez « Ouvrir le grand écran », puis ⤢ pour le plein écran.</li>
        <li>Les joueurs scannent le QR code (ou vont sur <b>/interactif/quiz-live/</b>) et tapent le code à 5 chiffres.</li>
        <li>Barre d’espace (ou le bouton jaune) : lancer, afficher la réponse, le classement, la question suivante.</li>
        <li>Un pseudo déplacé ? Cliquez dessus dans la salle d’attente pour le retirer.</li>
      </ol>
      <p class="xs muted" style="margin:12px 0 0">Le lien du grand écran est secret : il permet de piloter la partie. Une partie est effacée 24 h après sa dernière activité.</p>
    </div>
  </section>
</div>

<section class="card">
  <div class="card__head"><h2 class="card__t">Parties</h2></div>
  <div class="table" style="border:0">
    <table>
      <thead><tr><th>Code</th><th>Créée</th><th>Questions</th><th>Joueurs</th><th>État</th><th>Championnat</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($games as $g): [$st, $tone] = $phases[$g['phase']] ?? ['—', 'info']; ?>
        <tr<?= $g['code'] === $new ? ' style="background:var(--butter,#FFF3C2)"' : '' ?>>
          <td><b style="font-size:18px;letter-spacing:.12em"><?= e($g['code']) ?></b><?= $g['lang'] === 'en' ? ' <span class="pill pill--info">EN</span>' : '' ?></td>
          <td class="small nowrap"><?= e(date('d/m H:i', $g['created'])) ?><?= $g['by'] !== '' ? '<br><span class="xs muted">par ' . e($g['by']) . '</span>' : '' ?></td>
          <td class="small"><?= (int) $g['n'] ?> × <?= (int) $g['duration'] ?> s<?= $g['idx'] >= 0 && $g['phase'] !== 'end' ? '<br><span class="xs muted">question ' . ((int) $g['idx'] + 1) . '</span>' : '' ?></td>
          <td class="small"><?= (int) $g['players'] ?></td>
          <td><span class="pill pill--<?= e($tone) ?>"><?= e($st) ?></span></td>
          <td class="small"><?= $g['friendly'] ? 'amicale' : ($g['counted'] === true ? 'comptée' : ($g['counted'] === false ? 'non comptée' : 'comptera')) ?></td>
          <td class="nowrap">
            <a class="btn btn--navy btn--sm" href="<?= e(QuizClub::screenUrl($g)) ?>" target="_blank" rel="noopener">Ouvrir le grand écran ↗</a>
            <form method="post" action="/admin/quiz-club-house" style="display:inline" data-confirm="Effacer la partie <?= e($g['code']) ?> ?|Les joueurs ne pourront plus répondre.|Effacer|danger">
              <?= csrf_field() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="code" value="<?= e($g['code']) ?>">
              <button type="submit" class="linkbtn xs ko">Effacer</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$games): ?><tr><td colspan="7" class="muted" style="padding:20px;text-align:center">Aucune partie en cours.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card" id="championnat">
  <div class="card__head"><h2 class="card__t">Championnat <?= e($season) ?></h2><span class="card__note"><?= (int) $stats['games'] ?> partie<?= $stats['games'] > 1 ? 's' : '' ?> comptée<?= $stats['games'] > 1 ? 's' : '' ?> · <?= (int) $stats['players'] ?> joueur<?= $stats['players'] > 1 ? 's' : '' ?> classé<?= $stats['players'] > 1 ? 's' : '' ?> · <a href="<?= e(url('/interactif/quiz-live/championnat/')) ?>" target="_blank" rel="noopener">page publique ↗</a></span></div>
  <p class="card__body small" style="margin:0">Seuls les joueurs qui ont laissé leur e-mail (compte du carnet du supporter, lien sécurisé) comptent ; les invités jouent sans points. Points par partie selon le rang : <?= e(implode(', ', array_map(fn ($p) => (string) ($p + 1), \App\Services\QuizChampionship::SCALE))) ?>, puis 1 point de participation ; une partie compte à partir de <?= \App\Services\QuizChampionship::MIN_PLAYERS ?> joueurs. Nouveau classement chaque 1er août. Aucun e-mail n’est affiché ici.</p>
  <div class="table" style="border:0">
    <table>
      <thead><tr><th>#</th><th>Pseudo</th><th class="t-num">Points</th><th class="t-num">Parties</th><th class="t-num">Victoires</th><th class="t-num">Podiums</th><th></th></tr></thead>
      <tbody>
      <?php foreach (array_slice($ranking, 0, 100) as $r): ?>
        <tr>
          <td><b><?= (int) $r['rank'] ?></b></td><td><?= e($r['pseudo']) ?></td><td class="t-num"><b><?= (int) $r['pts'] ?></b></td><td class="t-num"><?= (int) $r['games'] ?></td><td class="t-num"><?= (int) $r['wins'] ?></td><td class="t-num"><?= (int) $r['podiums'] ?></td>
          <td class="nowrap">
            <form method="post" action="/admin/quiz-club-house" style="display:inline" data-confirm="Remplacer le pseudo « <?= e($r['pseudo']) ?> » ?|Il devient « Joueur 1234 » ; le joueur pourra en choisir un autre.|Remplacer">
              <?= csrf_field() ?><input type="hidden" name="action" value="renommer"><input type="hidden" name="joueur" value="<?= e($r['id']) ?>"><button type="submit" class="linkbtn xs">Pseudo déplacé</button>
            </form>
            <form method="post" action="/admin/quiz-club-house" style="display:inline" data-confirm="Retirer « <?= e($r['pseudo']) ?> » du classement ?|Il pourra encore jouer, mais ses parties ne compteront plus.|Retirer|danger">
              <?= csrf_field() ?><input type="hidden" name="action" value="exclure"><input type="hidden" name="joueur" value="<?= e($r['id']) ?>"><button type="submit" class="linkbtn xs ko">Retirer du classement</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$ranking): ?><tr><td colspan="7" class="muted" style="padding:20px;text-align:center">Pas encore de partie comptée cette saison.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($banned): ?>
  <div class="card__body small">Retirés du classement :
    <?php foreach ($banned as $b): ?>
      <form method="post" action="/admin/quiz-club-house" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="reintegrer"><input type="hidden" name="joueur" value="<?= e($b['id']) ?>"><b><?= e($b['pseudo']) ?></b> <button type="submit" class="linkbtn xs">réintégrer</button></form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
