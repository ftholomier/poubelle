<?php
/**
 * Contenus › Contrôle des compositions. Variables : $state, $seasons, $recent, $titles, $admin, $gemini, $cron
 */
use App\Services\Compos;

$queue = count($state['queue']);
$checked = array_sum(array_column($seasons, 'done'));
$total = array_sum(array_column($seasons, 'total'));
$added = array_sum(array_column($seasons, 'added'));
?>
<p class="alert alert--info" style="margin:0">Le musée compare la composition de chaque fiche de match (titulaires, remplaçants entrés, minutes des changements, buts, cartons, entraîneur) à des sources extérieures : <b>Transfermarkt</b> et <b>footballdatabase.eu</b>, lus directement (feuilles complètes : titulaires, remplaçants, minutes, buts, cartons, entraîneur) et, pour les matchs jusqu’en <?= \App\Services\Trouvailles::GALLICA_LAST_YEAR ?>, la <b>presse d’époque</b> (Gallica). Les sources marquées « par l’IA » sont seulement cherchées par Gemini avec Google : pari-et-gagne.com ne publie pas les compositions de Sochaux, worldfootball.net est protégé contre les robots (Cloudflare) et FCSM Story ne couvre que 1928-1969 ; elles ne servent qu’en complément. Chaque écart part dans <a href="/admin/trouvailles?source=compos">Trouvailles</a> sous forme de feuille corrigée à relire : rien n’entre dans une fiche sans votre validation. Un écart d’une ou deux minutes sur un changement n’est pas signalé.</p>

<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= $checked ?></b><span>matchs contrôlés</span><small>sur <?= $total ?> publiés</small></div>
  <div class="kpi"><b><?= $added ?></b><span>écarts envoyés</span><small><a href="/admin/trouvailles?source=compos">dans Trouvailles</a></small></div>
  <div class="kpi"><b><?= $queue ?></b><span>dans la file</span><small><?= $queue ? ($state['on'] ? 'en cours' : 'en pause') : 'file vide' ?></small></div>
</div>

<?php if ($admin): ?>
<section class="card">
  <div class="card__head"><h2 class="card__t">Lancer un contrôle</h2><span class="card__note">administrateurs · coût IA suivi dans <a href="/admin/couts-ia">Coûts IA</a></span></div>
  <div class="card__body stack" style="gap:12px">
    <?php if (!$gemini): ?><p class="alert" style="margin:0">Sans clé Gemini (Réglages › Assistant IA), seul Transfermarkt peut être lu : les autres sites passent par la recherche Google de Gemini.</p><?php endif; ?>
    <form method="post" action="/admin/compositions" class="stack" style="gap:10px">
      <?= csrf_field() ?>
      <div class="row" style="gap:12px;flex-wrap:wrap;align-items:flex-end">
        <label class="f" style="margin:0"><span class="f__k">Saison</span>
          <select class="input" name="saison"><option value="">Toutes les saisons</option><?php foreach (array_keys($seasons) as $s): if ($s === 'sans saison') { continue; } ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?></select>
        </label>
        <div class="checklist">
          <?php foreach (Compos::SOURCES as $k => $label): ?>
            <label><input type="checkbox" name="sources[]" value="<?= e($k) ?>"<?= in_array($k, (array) $state['sources'], true) ? ' checked' : '' ?>> <?= e($label) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <label class="small"><input type="checkbox" name="refaire" value="1"> Recontrôler les matchs déjà contrôlés</label>
      <div class="row" style="gap:8px;flex-wrap:wrap">
        <button class="btn btn--primary" type="submit" name="action" value="lancer">Mettre dans la file</button>
        <?php if ($queue && $state['on']): ?><button class="btn" type="submit" name="action" value="pause">Mettre en pause</button><?php elseif ($queue): ?><button class="btn" type="submit" name="action" value="reprendre">Reprendre</button><?php endif; ?>
        <?php if ($queue): ?><button class="btn btn--ghost" type="submit" name="action" value="vider">Vider la file</button><?php endif; ?>
      </div>
      <p class="xs muted" style="margin:0">Compter 20 à 60 secondes par match selon les sources cochées. La tâche planifiée « Contrôle des compositions » avance la file à chaque passage (Système › Tâches planifiées).</p>
    </form>
  </div>
</section>
<?php endif; ?>

<?php if ($admin): ?>
<section class="card">
  <div class="card__head"><h2 class="card__t">Accès aux sites</h2></div>
  <div class="card__body stack" style="gap:10px">
    <p class="small" style="margin:0">Les feuilles Transfermarkt des saisons 1932 à 2025 sont livrées avec le site : elles ne demandent rien à Transfermarkt, qui refuse les pages demandées par les serveurs d’hébergement. Pour un match plus récent, ouvrez son rapport sur transfermarkt.fr, enregistrez la page (Ctrl+S) et déposez le fichier sur la fiche, onglet Composition. footballdatabase.eu est lu directement.</p>
    <form method="post" action="/admin/compositions"><?= csrf_field() ?><button class="btn" name="action" value="tester" title="Le serveur essaie d’ouvrir chaque site source et dit ce qu’il reçoit">Tester l’accès aux sites</button></form>
  </div>
</section>
<?php endif; ?>

<section class="card">
  <div class="card__head"><h2 class="card__t">Derniers contrôles</h2><span class="card__note">pour une seule fiche : bouton « Contrôler la composition » dans l’onglet Composition de la fiche</span></div>
  <div class="card__body" style="padding:0">
    <?php if (!$recent): ?><p class="muted" style="padding:14px 18px;margin:0">Aucun contrôle pour l’instant.</p><?php else: ?>
    <table class="table">
      <thead><tr><th>Match</th><th>Quand</th><th>Écarts</th><th>Sources</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $id => $d): ?>
          <tr>
            <td><a href="/admin/fiche/<?= (int) $id ?>"><?= e($titles[$id]) ?></a></td>
            <td class="small"><?= e(\App\Admin\Base::ago($d['at'])) ?></td>
            <td><?= $d['added'] ? '<a href="/admin/trouvailles?source=compos#m' . (int) $id . '"><b>' . (int) $d['added'] . '</b></a>' : '0' ?></td>
            <td class="xs"><?php if ($d['error']): ?><span class="pill pill--warn">erreur</span> <?= e($d['error']) ?><?php endif; ?><?= e(implode(' · ', array_map(fn ($x) => (Compos::SOURCES[$x['key'] ?? ''] ?? '') . ' : ' . $x['note'], $d['sources']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</section>

<section class="card">
  <div class="card__head"><h2 class="card__t">Avancement par saison</h2></div>
  <div class="card__body" style="padding:0">
    <table class="table">
      <thead><tr><th>Saison</th><th>Contrôlés</th><th>Écarts envoyés</th></tr></thead>
      <tbody>
        <?php foreach ($seasons as $s => $x): ?>
          <tr><td><?= e($s) ?></td><td><?= $x['done'] ?> / <?= $x['total'] ?></td><td><?= $x['added'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
