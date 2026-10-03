<?php
/**
 * Interactif › Kit souvenirs. Variables : $months (ym, label, match, candidates, choice), $published, $total
 */
use App\Admin\Base;
use App\Front\Kit;

$label = fn (array $s): string => ($s['m']['home'] ?? '') . ' – ' . ($s['m']['away'] ?? '') . (is_array($s['m']['sh_score'] ?? null) ? ' ' . $s['m']['sh_score'][0] . '-' . $s['m']['sh_score'][1] : '');
?>
<p class="small" style="margin:0;max-width:90ch">Chaque mois, le site fabrique un <b>kit souvenirs</b> de 4 pages en gros caractères (le grand match d’il y a N ans, « Vous les reconnaissez ? », le quiz des anciens, « Racontez-nous » avec un QR code vers le formulaire de témoignage), à imprimer pour les anciens supporters. Le match est choisi automatiquement ; vous pouvez en choisir un autre et ajouter un mot d’introduction. <a href="<?= e(Kit::base()) ?>" target="_blank" rel="noopener">Voir la page publique ↗</a></p>

<div class="cols">
  <?php foreach ($months as $mo): $cur = $mo['match']; $ch = $mo['choice']; ?>
  <section class="card" id="m-<?= e($mo['ym']) ?>">
    <div class="card__head"><h2 class="card__t"><?= e(ucfirst($mo['label'])) ?></h2><span class="pill pill--<?= !empty($ch['match']) ? 'ok' : 'info' ?>"><?= !empty($ch['match']) ? 'choisi' . (!empty($ch['by']) ? ' par ' . e($ch['by']) : '') : 'automatique' ?></span></div>
    <form method="post" action="/admin/souvenirs" class="card__body stack" style="gap:12px">
      <?= csrf_field() ?><input type="hidden" name="mois" value="<?= e($mo['ym']) ?>">
      <?php if ($cur): ?>
        <p style="margin:0"><b><?= e($label($cur['s'])) ?></b><br><span class="small muted"><?= e(date_fr($cur['dm']['date'] ?? null)) ?> · il y a <?= (int) $cur['ago'] ?> ans</span></p>
        <div class="row"><a class="btn btn--sm" href="<?= e(Kit::pdfUrl($mo['ym'])) ?>">Télécharger le PDF</a><a class="linkbtn xs" href="/admin/fiche/<?= (int) $cur['id'] ?>">Relire la fiche</a></div>
      <?php else: ?>
        <p class="small muted" style="margin:0">Aucun match de ce mois n’a assez de temps forts : choisissez-en un ci-dessous.</p>
      <?php endif; ?>
      <fieldset class="f" style="border:0;padding:0;margin:0">
        <legend class="f__k">Match du mois</legend>
        <label class="row" style="gap:8px;flex-wrap:nowrap;align-items:flex-start"><input type="radio" name="match" value="0"<?= empty($ch['match']) ? ' checked' : '' ?>><span>Automatique (le plus marquant)</span></label>
        <?php foreach ($mo['candidates'] as $cd): ?>
          <label class="row" style="gap:8px;flex-wrap:nowrap;align-items:flex-start"><input type="radio" name="match" value="<?= (int) $cd['id'] ?>"<?= (int) ($ch['match'] ?? 0) === $cd['id'] ? ' checked' : '' ?>><span><?= e($label($cd['s'])) ?> <span class="xs muted"><?= e(substr((string) $cd['dm']['date'], 0, 4)) ?> · <?= (int) $cd['ago'] ?> ans · intérêt <?= e(number_format((float) $cd['score'], 0)) ?></span></span></label>
        <?php endforeach; ?>
      </fieldset>
      <div class="f"><span class="f__k">Ou un autre match</span><input type="text" class="in in--sm" data-ac="matchs" data-ac-id="autre" placeholder="Tapez un match…" autocomplete="off"><input type="hidden" name="autre" value=""></div>
      <div class="f"><span class="f__k">Mot d’introduction <i>facultatif · 500 caractères</i></span><textarea name="intro" rows="3" maxlength="500" class="in" data-proof="text" spellcheck="true" placeholder="Ce soir-là, Bonal était plein à craquer…"><?= e((string) ($ch['intro'] ?? '')) ?></textarea></div>
      <button type="submit" class="btn btn--navy">Enregistrer</button>
    </form>
  </section>
  <?php endforeach; ?>
</div>

<section class="card" id="ils-y-etaient">
  <div class="card__head"><h2 class="card__t">Ils y étaient</h2><span class="card__note"><?= (int) $total ?> souvenir<?= $total > 1 ? 's' : '' ?> publié<?= $total > 1 ? 's' : '' ?> sur les fiches de match · <a href="/admin/contributions">Contributions</a></span></div>
  <div class="table" style="border:0">
    <table>
      <tbody>
      <?php foreach ($published as $t): ?>
        <tr>
          <td class="small" style="width:40%"><?= e(mb_strimwidth($t['text'], 0, 140, '…')) ?></td>
          <td class="small nowrap"><?= e($t['name']) ?></td>
          <td class="small"><?php if ($t['s']): ?><a class="rowlink" href="/admin/fiche/<?= (int) $t['s']['id'] ?>"><?= e($t['s']['title']) ?></a><?php endif; ?></td>
          <td class="xs nowrap"><a href="/admin/contributions/<?= e($t['ticket']) ?>"><?= e($t['ticket']) ?></a> · <?= e(Base::ago($t['at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$published): ?><tr><td colspan="4" class="muted" style="padding:20px;text-align:center">Aucun souvenir publié pour l’instant. Un témoignage reçu par le formulaire « Contribuer » se publie à la validation, sur la fiche du match choisie.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
