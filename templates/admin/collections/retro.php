<?php
/**
 * Interactif › Rétro-Direct. Variables : $upcoming, $past, $edit, $suggestions, $days, $now, $peak, $etais
 */
use App\Admin\Base;
use App\Admin\Retro;
use App\Services\RetroDirect;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$label = fn (array $s): string => ($s['m']['home'] ?? '') . ' – ' . ($s['m']['away'] ?? '');
$score = fn (array $s): string => is_array($s['m']['sh_score'] ?? null) ? $s['m']['sh_score'][0] . '-' . $s['m']['sh_score'][1] : '';
$when = function (array $e): string {
    $d = date_fr($e['date'], true);
    $h = (int) substr($e['time'], 0, 2) . ' h' . (substr($e['time'], 3) === '00' ? '' : ' ' . substr($e['time'], 3));
    return $d . ' · ' . $h;
};
$states = ['avenir' => ['À venir', 'info'], 'direct' => ['En direct', 'ok'], 'termine' => ['Terminé', 'brouillon']];
$next = $upcoming[0] ?? null;
$q = $days !== 90 ? '&jours=' . $days : '';
$f = $edit ?? ['id' => 0, 'date' => '', 'time' => '20:00', 'intro' => '', 'intro_en' => '', 's' => null];
?>
<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= $next ? e($next['state'] === 'direct' ? 'En direct' : date('d/m', $next['start'])) : '—' ?></b><span><?= $next && $next['state'] === 'direct' ? 'en ce moment' : 'prochain direct' ?></span><small><?= $next ? e($label($next['s']) . ' · ' . $next['time']) : 'rien de programmé' ?></small></div>
  <div class="kpi"><b><?= count($upcoming) ?></b><span>au programme</span><small>directs à venir</small></div>
  <div class="kpi"><b><?= count($past) ?></b><span>déjà joués</span><small><?= $peak ? 'record : ' . $fmt($peak) . ' spectateurs connectés' : 'aucun pour l’instant' ?></small></div>
  <div class="kpi"><b><?= $fmt($etais) ?></b><span>« J’y étais ! »</span><small>supporters présents au stade à l’époque</small></div>
</div>

<section class="card" id="programme">
    <div class="card__head"><h2 class="card__t">Au programme</h2><span class="card__note"><a href="<?= e(url('/interactif/retro-direct/')) ?>" target="_blank" rel="noopener">voir la page publique ↗</a></span></div>
    <div class="table" style="border:0">
      <table>
        <thead><tr><th>Coup d’envoi</th><th>Match</th><th>État</th><th>Public</th><th></th></tr></thead>
        <tbody>
        <?php foreach (array_merge($upcoming, $past) as $e): [$st, $tone] = $states[$e['state']]; $s = $e['s']; $stt = $e['stats']; ?>
          <tr>
            <td class="small nowrap"><?= e($when($e)) ?><?= $e['by'] !== '' ? '<br><span class="xs muted">par ' . e($e['by']) . '</span>' : '' ?></td>
            <td><a class="rowlink" href="/admin/fiche/<?= (int) $e['id'] ?>"><?= e($label($s)) ?></a> <span class="small muted"><?= e($score($s)) ?></span><br><span class="xs muted"><?= e(trim(($s['m']['label'] ?? '') . ' · ' . date_fr($s['m']['date'] ?? null), ' ·')) ?></span><?= $e['intro'] !== '' ? '<br><span class="xs">' . e(mb_strimwidth($e['intro'], 0, 110, '…')) . '</span>' : '' ?></td>
            <td><span class="pill pill--<?= e($tone) ?>"><?= e($st) ?></span></td>
            <td class="small nowrap"><?php if ($stt): ?><?= $e['state'] === 'direct' ? $fmt($stt['viewers']) . ' en ligne<br>' : '' ?>pic <?= $fmt($stt['peak']) ?><br><span class="xs muted"><?php foreach (RetroDirect::REACTIONS as $k => $em): ?><?= $em ?> <?= $fmt($stt['reactions'][$k]) ?> <?php endforeach; ?></span><?php else: ?><span class="xs muted">—</span><?php endif; ?></td>
            <td class="nowrap">
              <a class="linkbtn xs" href="<?= e(RetroDirect::url($s)) ?>" target="_blank" rel="noopener">Voir</a>
              <a class="linkbtn xs" href="/admin/retro-direct?modifier=<?= (int) $e['id'] ?>|<?= e($e['date']) ?><?= e($q) ?>#programmer">Modifier</a>
              <form method="post" action="/admin/retro-direct" style="display:inline" data-confirm="Retirer ce direct du programme ?|<?= e($label($s)) ?>, <?= e($when($e)) ?>. Le match reste à revivre en accéléré.|Retirer|danger">
                <?= csrf_field() ?><input type="hidden" name="action" value="retirer"><input type="hidden" name="id" value="<?= (int) $e['id'] ?>"><input type="hidden" name="date" value="<?= e($e['date']) ?>"><input type="hidden" name="jours" value="<?= (int) $days ?>">
                <button type="submit" class="linkbtn xs ko">Retirer</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$upcoming && !$past): ?><tr><td colspan="5" class="muted" style="padding:20px;text-align:center">Aucun direct programmé : choisissez un anniversaire ci-dessous, ou un match dans « Programmer un match ».</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
</section>

<div class="cols cols--wide">
  <section class="card" id="programmer">
    <div class="card__head"><h2 class="card__t"><?= $edit ? 'Modifier le direct' : 'Programmer un match' ?></h2><?php if ($edit): ?><span class="card__note"><a href="/admin/retro-direct<?= $days !== 90 ? '?jours=' . $days : '' ?>">annuler</a></span><?php endif; ?></div>
    <form method="post" action="/admin/retro-direct" class="card__body stack" style="gap:14px" data-retro-form>
      <?= csrf_field() ?><input type="hidden" name="action" value="programmer"><input type="hidden" name="jours" value="<?= (int) $days ?>">
      <?php if ($edit): ?><input type="hidden" name="ancienne_date" value="<?= e($edit['date']) ?>"><?php endif; ?>
      <div class="f">
        <span class="f__k"><label for="rd-match">Match</label> <b aria-hidden="true">*</b></span>
        <input id="rd-match" type="text" class="in" data-ac="matchs" data-ac-id="id" autocomplete="off" placeholder="Tapez « Sochaux Marseille 1986 »…" value="<?= e($edit ? $edit['s']['title'] : '') ?>"<?= $edit ? ' readonly' : '' ?> required>
        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
        <span class="f__help" data-rd-hint>Il faut au moins <?= RetroDirect::MIN_EVENTS ?> temps forts avec leur minute dans la fiche.</span>
      </div>
      <div class="fgrid">
        <div class="f"><span class="f__k"><label for="rd-date">Date du direct</label> <b aria-hidden="true">*</b></span><input id="rd-date" type="date" class="in" name="date" value="<?= e($f['date']) ?>" min="<?= e(date('Y-m-d')) ?>" required></div>
        <div class="f"><span class="f__k"><label for="rd-time">Coup d’envoi</label> <b aria-hidden="true">*</b></span><input id="rd-time" type="time" class="in" name="heure" value="<?= e($f['time']) ?>" required></div>
      </div>
      <div class="f">
        <span class="f__k"><label for="rd-intro">Présentation</label> <i>facultatif · 400 caractères</i></span>
        <textarea id="rd-intro" class="in" name="intro" rows="3" maxlength="400" data-proof="text" spellcheck="true" placeholder="Il y a 40 ans, l’OM de Papin et de Giresse venait à Bonal…"><?= e($f['intro']) ?></textarea>
      </div>
      <div class="f">
        <span class="f__k"><label for="rd-intro-en">Présentation en anglais</label> <i>facultatif</i></span>
        <textarea id="rd-intro-en" class="in" name="intro_en" rows="2" maxlength="400" lang="en" data-proof="text" spellcheck="true"><?= e($f['intro_en']) ?></textarea>
      </div>
      <div class="row"><button type="submit" class="btn btn--navy"><?= $edit ? 'Enregistrer' : 'Programmer le direct' ?></button><span class="xs muted">Le direct apparaît aussitôt sur la page Rétro-Direct, dans le bandeau du site 7 jours avant, et dans l’agenda (.ics).</span></div>
    </form>
  </section>
  <section class="card">
    <div class="card__head"><h2 class="card__t">Comment ça marche</h2></div>
    <div class="card__body small">
      <p style="margin:0"><b>Le jour J :</b> à l’heure du coup d’envoi, la page du match déroule la rencontre minute par minute : temps forts, buts (le score change à la bonne minute), remplacements, cartons, 15 minutes de mi-temps, prolongation et tirs au but s’il y en a eu. Tout vient de la fiche du match : aucune IA, aucun coût.</p>
      <p style="margin:0"><b>Avant :</b> compte à rebours sans le score, présentation, brèves d’avant-match, compositions, bouton « Ajouter à mon agenda ». Le direct s’annonce dans le bandeau du site 7 jours avant.</p>
      <p style="margin:0"><b>Pendant :</b> compteur de spectateurs connectés et réactions ⚽ 👏 😱. <b>Après :</b> réactions d’après-match, et le match reste à revivre en accéléré (comme tous les matchs qui ont leurs temps forts).</p>
      <p style="margin:0"><b>Pour un beau direct :</b> vérifiez dans la fiche les minutes des temps forts et des buts, les entrées en jeu et une belle photo à la une.</p>
    </div>
  </section>
</div>

<section class="card" id="anniversaires">
  <div class="card__head">
    <h2 class="card__t">Anniversaires à venir</h2>
    <span class="card__note">10, 20, 25, 30, 40, 50… ans, jour pour jour · les plus marquants d’abord retenus
      · <?php foreach (Retro::DAYS as $d): ?><?= $d === $days ? '<b>' . $d . ' j</b>' : '<a href="/admin/retro-direct?jours=' . $d . '#anniversaires">' . $d . ' j</a>' ?> <?php endforeach; ?></span>
  </div>
  <div class="table" style="border:0">
    <table>
      <thead><tr><th>Date</th><th>Il y a</th><th>Match</th><th class="t-num">Temps forts</th><th class="t-num">Intérêt</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($suggestions as $x): $s = $x['s']; ?>
        <tr>
          <td class="small nowrap"><?= e(date_fr($x['date'], true)) ?></td>
          <td class="small nowrap"><b><?= (int) $x['ago'] ?> ans</b></td>
          <td><a class="rowlink" href="/admin/fiche/<?= (int) $x['id'] ?>"><?= e($label($s)) ?></a> <span class="small muted"><?= e($score($s)) ?></span><br><span class="xs muted"><?= e(trim(($s['m']['label'] ?? '') . ' · ' . ($s['m']['round'] ?? '') . ' · ' . date_fr($s['m']['date'] ?? null), ' ·')) ?></span></td>
          <td class="t-num"><?= (int) $x['hl'] ?></td>
          <td class="t-num"><?= e(number_format((float) $x['score'], 0)) ?></td>
          <td class="nowrap">
            <form method="post" action="/admin/retro-direct" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="programmer"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>"><input type="hidden" name="date" value="<?= e($x['date']) ?>"><input type="hidden" name="heure" value="20:00"><input type="hidden" name="jours" value="<?= (int) $days ?>">
              <button type="submit" class="btn btn--sm">Programmer à 20 h</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$suggestions): ?><tr><td colspan="6" class="muted" style="padding:20px;text-align:center">Aucun anniversaire rond dans les <?= (int) $days ?> prochains jours.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
