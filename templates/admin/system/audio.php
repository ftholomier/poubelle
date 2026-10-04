<?php
/**
 * Fiches audio. Variables : $stats, $plan, $aiText, $texts (textes à rédiger par l'IA : n, batch, model), $batch, $direct, $jobs, $states, $recent, $ready, $model, $voice, $spent, $admin, $enabled,
 * $pages (pages de synthèse : stats, last, running, auto, estimate, all, upper, voices, voiceEstimate, voiceAll, activated)
 */
use App\Admin\Base;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$eur = fn (float $v) => \App\Services\AiCosts::fmt($v);
$todo = count($plan['text']) + count($plan['voice']);
$kind = ['texte' => 'Résumés rédigés par l’IA', 'voix' => 'Voix IA'];
?>
<?php if (!$enabled): ?><p class="alert" style="margin:0">Le bouton « Écouter » est désactivé sur le site (<a href="/admin/reglages?groupe=audio">Réglages › Fiches audio</a>).</p><?php endif; ?>

<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= $fmt($stats['fiches']) ?></b><span>fiches qui se racontent</span><small>explication complète (<?= e(rtrim(rtrim(number_format(\App\Services\FicheAudio::maxMinutes(), 1, ',', ''), '0'), ',')) ?> min au plus), voix du navigateur : gratuit</small></div>
  <div class="kpi"><b><?= $fmt($stats['fr_voice']) ?></b><span>voix IA en français</span><small><?= $stats['bytes'] ? e(Base::size((int) $stats['bytes'])) . ' sur le serveur' : 'aucune pour l’instant' ?><?= ($wav = $stats['wav'] + $pages['stats']['wav']) ? ' · ' . $fmt($wav) . ' encore en WAV, converties en MP3 par la tâche planifiée' : '' ?></small></div>
  <div class="kpi"><b><?= $fmt($stats['en_voice']) ?></b><span>voix IA en anglais</span><small>fiches traduites seulement</small></div>
  <div class="kpi"><b><?= $fmt($stats['ai_text'] + $stats['manual']) ?></b><span>textes rédigés</span><small><?= $fmt($stats['ai_text']) ?> par l’IA · <?= $fmt($stats['manual']) ?> à la main</small></div>
  <div class="kpi"><b><?= e($spent) ?></b><span>dépensé en audio</span><small><a href="/admin/couts-ia">détail dans Coûts IA</a></small></div>
</div>

<div class="cols cols--wide">
  <section class="card">
    <div class="card__head"><h2 class="card__t">Tout le musée en voix IA</h2><span class="card__note">traitement groupé de Google : moitié prix</span></div>
    <div class="card__body">
      <?php if (!$ready): ?>
        <p class="small" style="margin:0">La voix IA nécessite une clé Gemini<?= $admin ? ' (<a href="/admin/reglages?groupe=ai">Réglages › Assistant IA</a>)' : '' ?>. En attendant, chaque fiche est lue par la voix du navigateur du visiteur, gratuitement.</p>
      <?php elseif (!$todo): ?>
        <p class="small" style="margin:0"><span class="ok">✓</span> Toutes les fiches publiées ont leur voix IA à jour.</p>
      <?php else: ?>
        <p class="small" style="margin:0"><b><?= $fmt($todo) ?> fiche<?= $todo > 1 ? 's' : '' ?></b> sans voix IA à jour<?= $aiText ? ', dont ' . $fmt(count($plan['text'])) . ' dont l’IA rédigera d’abord le résumé' : '' ?>. Coût estimé : <b><?= e($eur($batch['eur'])) ?></b> en traitement groupé (<?= e($eur($direct['eur'])) ?> fiche par fiche), soit environ <?= e($eur($batch['per'])) ?> par fiche de <?= (int) round($batch['seconds']) ?> secondes.</p>
        <p class="xs muted" style="margin:0">Voix <b><?= e($voice) ?></b>, modèle <?= e((string) $model) ?>. Google traite la demande en quelques heures (24 h au plus) ; la tâche planifiée range ensuite les voix et chaque fiche bascule toute seule de la voix du navigateur à la voix IA.</p>
        <?php if ($admin): ?>
          <form method="post" action="/admin/audio" class="stack" style="gap:10px" data-confirm="Lancer le traitement groupé ?|20 fiches pour essayer, ou toutes (coût estimé : <?= e($eur($batch['eur'])) ?>), comptées dans Coûts IA.|Lancer">
            <?= csrf_field() ?><input type="hidden" name="action" value="lancer">
            <div class="row">
              <label class="row" style="gap:6px"><input type="checkbox" name="langues[]" value="fr" checked> Français</label>
              <label class="row" style="gap:6px"><input type="checkbox" name="langues[]" value="en" checked> Anglais (fiches traduites)</label>
              <label class="row" style="gap:6px" title="Refaire aussi les voix IA déjà à jour (changement de voix, par exemple)"><input type="checkbox" name="refaire" value="1"> Tout refaire</label>
            </div>
            <div class="row">
              <label class="row" style="gap:6px"><input type="radio" name="nombre" value="essai" checked> Essayer d’abord sur 20 fiches (quelques centimes)</label>
              <label class="row" style="gap:6px"><input type="radio" name="nombre" value="tout"> Toutes les fiches</label>
            </div>
            <div class="row"><button type="submit" class="btn btn--navy">Lancer le traitement groupé</button><span class="xs muted">toutes les fiches : ≈ <?= e($eur($batch['eur'])) ?></span></div>
          </form>
        <?php else: ?>
          <p class="xs muted" style="margin:0">Le lancement est réservé aux administrateurs. Chaque fiche peut aussi recevoir sa voix IA depuis son éditeur (carte « Écouter »).</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
  <section class="card">
    <div class="card__head"><h2 class="card__t">Réécrire les textes avec l’IA</h2><span class="card__note">sans voix IA : lus par la voix du navigateur, gratuite</span></div>
    <div class="card__body">
      <p class="small" style="margin:0">L’IA raconte chaque fiche comme un historien : une accroche, le décor, le récit en paragraphes (coulisses, anecdotes, hommes), une conclusion ; uniquement des faits de la fiche, dans la durée maximale réglée. Les textes écrits à la main ne sont jamais remplacés.</p>
      <?php if (!$ready): ?>
        <p class="small" style="margin:0">Il faut une clé Gemini<?= $admin ? ' (<a href="/admin/reglages?groupe=ai">Réglages › Assistant IA</a>)' : '' ?>.</p>
      <?php elseif (!$texts['n']): ?>
        <p class="small" style="margin:0"><span class="ok">✓</span> Tous les textes sont rédigés (par l’IA ou à la main). « Tout refaire » ci-dessous les réécrit quand même.</p>
      <?php else: ?>
        <p class="small" style="margin:0"><b><?= $fmt($texts['n']) ?> texte<?= $texts['n'] > 1 ? 's' : '' ?></b> à rédiger (automatiques ou plus à jour). Modèle <?= e((string) $texts['model']) ?> ; coût estimé : <b><?= e($eur($texts['batch']['eur'])) ?></b> (traitement groupé, moitié prix).</p>
      <?php endif; ?>
      <?php if ($ready && $admin): ?>
        <form method="post" action="/admin/audio" class="stack" style="gap:10px" data-confirm="Faire réécrire les textes par l’IA ?|20 fiches pour essayer, ou toutes (coût estimé : <?= e($eur($texts['batch']['eur'])) ?>), comptées dans Coûts IA.|Lancer">
          <?= csrf_field() ?><input type="hidden" name="action" value="lancer"><input type="hidden" name="quoi" value="textes">
          <div class="row">
            <label class="row" style="gap:6px"><input type="checkbox" name="langues[]" value="fr" checked> Français</label>
            <label class="row" style="gap:6px"><input type="checkbox" name="langues[]" value="en" checked> Anglais (fiches traduites)</label>
            <label class="row" style="gap:6px" title="Réécrire aussi les textes déjà rédigés par l’IA (jamais ceux écrits à la main)"><input type="checkbox" name="refaire" value="1"<?= $texts['n'] ? '' : ' checked' ?>> Tout refaire</label>
          </div>
          <div class="row">
            <label class="row" style="gap:6px"><input type="radio" name="nombre" value="essai" checked> Essayer d’abord sur 20 fiches</label>
            <label class="row" style="gap:6px"><input type="radio" name="nombre" value="tout"> Toutes les fiches</label>
          </div>
          <div class="row"><button type="submit" class="btn btn--navy">Réécrire les textes</button><span class="xs muted">Résultat en quelques heures ; à relire dans l’éditeur de chaque fiche (carte « Écouter la fiche »).</span></div>
        </form>
      <?php endif; ?>
    </div>
  </section>
  <section class="card">
    <div class="card__head"><h2 class="card__t">Pages de synthèse racontées par l’IA</h2><span class="card__note">face-à-face, saisons, bilans, records, chiffres</span></div>
    <div class="card__body">
      <p class="small" style="margin:0">Chaque page de synthèse a son bouton « Écouter ». L’IA la raconte comme un historien, à partir de ses chiffres et des fiches de ses grands matchs (premier et dernier match, plus belles victoires, finales, buteurs, séries, bilan de la saison…), en français et en anglais<?= $pages['voices'] ? ', puis la voix IA enregistre le récit' : '' ?>. Tant que son récit manque ou que les chiffres ont changé, le récit automatique, gratuit, est lu par la voix du navigateur.</p>
      <p class="small" style="margin:0"><?php if ($pages['stats']['fr'] + $pages['stats']['en'] === 0): ?>Aucun récit rédigé par l’IA pour l’instant<?php else: ?><b><?= $fmt($pages['stats']['fr']) ?></b> récit<?= $pages['stats']['fr'] > 1 ? 's' : '' ?> en français et <b><?= $fmt($pages['stats']['en']) ?></b> en anglais rédigés par l’IA, dont <b><?= $fmt($pages['stats']['voice_fr'] + $pages['stats']['voice_en']) ?></b> lus par la voix IA<?= $pages['stats']['bytes'] ? ' (' . e(Base::size((int) $pages['stats']['bytes'])) . ')' : '' ?><?php endif; ?><?php if ($pages['last']): ?> ; au dernier calcul (<?= e(Base::ago(date('c', (int) $pages['last']['at']))) ?>), <?= $fmt($pages['last']['pages']) ?> pages se racontent<?= $pages['last']['todo'] !== null ? ', ' . $fmt($pages['last']['todo']) . ' récit' . ($pages['last']['todo'] > 1 ? 's' : '') . ' à rédiger' : '' ?><?php endif; ?>.</p>
      <p class="small" style="margin:0">Coût estimé<?= $pages['upper'] ? ', au plus' : '' ?> : <b><?= e($eur($pages['estimate']['eur'])) ?></b> pour les récits<?= $pages['voices'] ? ', <b>' . e($eur($pages['voiceEstimate']['eur'])) . '</b> pour les voix IA' : '' ?> (traitement groupé, moitié prix ; tout refaire : <?= e($eur($pages['all']['eur'] + ($pages['voices'] ? $pages['voiceAll']['eur'] : 0))) ?>). <?php if (!$pages['auto']): ?>Rédaction automatique de nuit désactivée<?= $admin ? ' (<a href="/admin/reglages?groupe=audio">Réglages › Fiches audio</a>)' : '' ?>.<?php elseif ($pages['activated']): ?>Chaque nuit, les récits manquants ou dépassés sont rédigés<?= $pages['voices'] ? ' puis enregistrés en voix IA' : '' ?> automatiquement.<?php else: ?><b>Rien n’est encore lancé :</b> essayez d’abord sur une page ; la rédaction de nuit commencera après « Lancer pour tout le musée ».<?php endif; ?></p>
      <?php if ($pages['running']): ?><p class="small" style="margin:0"><span class="pill pill--info">En cours</span> Un traitement groupé de récits est en cours (tableau ci-dessous).</p><?php endif; ?>
      <?php if (!$ready): ?>
        <p class="small" style="margin:0">Il faut une clé Gemini<?= $admin ? ' (<a href="/admin/reglages?groupe=ai">Réglages › Assistant IA</a>)' : '' ?>.</p>
      <?php elseif ($admin): ?>
        <form method="post" action="/admin/audio" class="row" style="gap:10px;align-items:flex-end" data-confirm="Essayer sur cette page ?|Le récit est rédigé tout de suite<?= $pages['voices'] ? ', puis enregistré par la voix IA (une à deux minutes)' : '' ?>, au tarif normal : moins d’un centime pour le récit<?= $pages['voices'] ? ', quelques centimes pour la voix' : '' ?>.|Essayer">
          <?= csrf_field() ?><input type="hidden" name="action" value="pages-essai">
          <label class="stack" style="gap:4px;flex:1 1 260px"><span class="xs muted">Adresse de la page à essayer</span><input type="text" name="page" value="/face-a-face/nancy/" required placeholder="/face-a-face/nancy/, /matchs/1987-1988/, /chiffres/…"></label>
          <?php if ($pages['voices']): ?><label class="row" style="gap:6px"><input type="checkbox" name="voix" value="1" checked> avec la voix IA</label><?php endif; ?>
          <button type="submit" class="btn">Essayer sur cette page</button>
        </form>
        <form method="post" action="/admin/audio" class="row" style="gap:12px" data-confirm="Faire rédiger les récits des pages par l’IA ?|Les récits manquants ou dépassés, en français et en anglais<?= $pages['voices'] ? ', puis leurs voix IA' : '' ?> (coût estimé : <?= e($eur($pages['estimate']['eur'] + ($pages['voices'] ? $pages['voiceEstimate']['eur'] : 0))) ?>, compté dans Coûts IA). Le calcul prend une dizaine de secondes.|Lancer">
          <?= csrf_field() ?><input type="hidden" name="action" value="pages">
          <label class="row" style="gap:6px" title="Réécrire aussi les récits déjà rédigés et à jour"><input type="checkbox" name="refaire" value="1"> Tout refaire</label>
          <button type="submit" class="btn btn--navy"><?= $pages['activated'] ? ($pages['voices'] ? 'Rédiger et enregistrer maintenant' : 'Rédiger les récits maintenant') : 'Lancer pour tout le musée' ?></button>
          <span class="xs muted">Résultat en quelques heures<?= $pages['activated'] ? '' : ' ; la rédaction de nuit prend le relais ensuite' ?>.</span>
        </form>
      <?php endif; ?>
    </div>
  </section>
  <section class="card">
    <div class="card__head"><h2 class="card__t">Comment ça marche</h2></div>
    <div class="card__body small">
      <p style="margin:0"><b>Gratuit, par défaut :</b> chaque fiche propose « Écouter ». Le texte est tiré de la fiche (date, score, buteurs, carrière, puis le texte de la fiche), dans la durée maximale réglée, et lu par la voix du navigateur du visiteur. Rédigé par l’IA, il explique toute la fiche : une fiche courte reste courte.</p>
      <p style="margin:0"><b>Voix IA :</b> Gemini lit le texte d’une voix naturelle, enregistrée une fois pour toutes. Depuis l’éditeur d’une fiche (carte « Écouter »), ou pour tout le musée ici, en traitement groupé. Une fiche modifiée retrouve sa voix IA la nuit suivante<?= $admin ? ' (<a href="/admin/reglages?groupe=audio">Réglages › Fiches audio</a>)' : '' ?>.</p>
      <p style="margin:0"><b>Texte lu :</b> modifiable dans chaque fiche, ou rédigé par l’IA. Il s’affiche sous le bouton pendant l’écoute (accessibilité).</p>
    </div>
  </section>
</div>

<section class="card">
  <div class="card__head"><h2 class="card__t">Traitements groupés</h2><span class="card__note">la tâche planifiée les envoie, les suit et range les résultats</span></div>
  <div class="table" style="border:0">
    <table>
      <thead><tr><th>Lancé</th><th>Contenu</th><th>Fiches</th><th>État</th><th>Avancement</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($jobs as $j): [$label, $tone] = $states[$j['state']] ?? [$j['state'], 'info']; ?>
        <tr>
          <td class="small nowrap"><?= e(Base::ago(date('c', (int) $j['created']))) ?><br><span class="xs muted"><?= e((string) $j['by']) ?></span></td>
          <td class="small"><?= e(!empty($j['pages']) ? ($j['kind'] === 'voix' ? 'Voix IA des pages de synthèse' : 'Récits des pages de synthèse') : ($kind[$j['kind']] ?? $j['kind'])) ?></td>
          <td class="t-num"><?= count($j['keys']) ?></td>
          <td><span class="pill pill--<?= e($tone) ?>"><?= e($label) ?></span></td>
          <td class="small"><?= (int) $j['done'] ?> / <?= count($j['keys']) ?><?= $j['errors'] ? ' · <span class="ko">' . (int) $j['errors'] . ' en échec</span>' : '' ?><?= $j['message'] !== '' ? '<br><span class="xs muted">' . e((string) $j['message']) . '</span>' : '' ?></td>
          <td><?php if ($admin && in_array($j['state'], ['attente', 'envoye'], true)): ?><form method="post" action="/admin/audio" data-confirm="Annuler ce traitement ?|Les fiches garderont la voix du navigateur.|Annuler le traitement|danger"><?= csrf_field() ?><input type="hidden" name="action" value="annuler"><input type="hidden" name="job" value="<?= e($j['id']) ?>"><button type="submit" class="linkbtn xs">Annuler</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$jobs): ?><tr><td colspan="6" class="muted" style="padding:20px;text-align:center">Aucun traitement groupé pour l’instant.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($recent): ?>
  <section class="card">
    <div class="card__head"><h2 class="card__t">Dernières voix IA</h2></div>
    <div class="table" style="border:0">
      <table>
        <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td style="width:44px"><button type="button" class="iconbtn" data-audio-url="<?= e($r['url']) ?>" title="Écouter" aria-label="Écouter « <?= e($r['title']) ?> »">▶</button></td>
            <td><a class="rowlink" href="/admin/fiche/<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a></td>
            <td class="xs muted nowrap"><?= e($r['lang']) ?> · <?= e((string) $r['voice']) ?><?= $r['dur'] ? ' · ' . number_format((float) $r['dur'], 0) . ' s' : '' ?></td>
            <td class="xs muted nowrap"><?= e(Base::ago($r['at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
