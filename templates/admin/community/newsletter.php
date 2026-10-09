<?php
/** Newsletter « Ce jour-là ». Variables : $stat, $state, $next, $subs (clé => abonné), $found, $q, $items */
use App\Admin\Base;
use App\Core\Auth;
use App\Core\Settings;
use App\Front\Site;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$days = ['1' => 'lundi', '2' => 'mardi', '3' => 'mercredi', '4' => 'jeudi', '5' => 'vendredi', '6' => 'samedi', '7' => 'dimanche'];
$enabled = (bool) Settings::get('newsletter.enabled', false);
?>
<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= $fmt($stat['active']) ?></b><span>abonnés confirmés</span><small>double validation par e-mail</small></div>
  <div class="kpi"><b><?= $fmt($stat['pending']) ?></b><span>en attente de confirmation</span><small>lien envoyé, pas encore cliqué</small></div>
  <div class="kpi"><b><?= $fmt($stat['unsubscribed']) ?></b><span>désinscriptions</span><small>adresses effacées</small></div>
  <div class="kpi"><b><?= $enabled ? 'Auto' : 'Off' ?></b><span><?= $enabled ? 'envoi le ' . e($days[(string) Settings::get('newsletter.weekday', 1)] ?? '') . ' à ' . (int) Settings::get('newsletter.hour', 8) . ' h' : 'envoi automatique désactivé' ?></span><small><?= $next ? 'prochain : ' . e(date('d/m à H:i', strtotime($next))) : (Auth::isAdmin() ? '<a href="/admin/reglages?groupe=newsletter">activer dans les réglages</a>' : 'réglable par un administrateur') ?></small></div>
</div>

<div class="cols cols--wide">
  <div class="stack">
    <div class="card">
      <div class="card__head"><h2 class="card__t">Prochaine lettre</h2><a class="linkbtn" href="/admin/newsletter/apercu" target="_blank" rel="noopener">Aperçu ↗</a></div>
      <div class="card__body">
        <p style="margin:0"><b>Objet :</b> <?= e(\App\Services\Newsletter::subject()) ?></p>
        <p class="small muted" style="margin:0">Les matchs de la semaine dans l’histoire du club (<?= count($items) ?> cette semaine), le compte à rebours du centenaire (J-<?= (int) Site::daysToCentenary() ?>) et un rappel aux dons. Chaque abonné la reçoit dans sa langue (FR ou EN).</p>
        <?php foreach ($items as $m): ?>
          <div class="row small" style="gap:8px"><span class="pill"><?= e(date_num((string) $m['date'])) ?></span><a href="<?= e($m['path']) ?>" target="_blank" rel="noopener"><?= e(Site::matchLabel($m)) ?></a></div>
        <?php endforeach; ?>
        <?php if (!$items): ?><p class="small muted" style="margin:0">Aucun match fiché cette semaine dans l’histoire : la lettre invite à explorer les saisons.</p><?php endif; ?>
      </div>
      <div class="card__foot">
        <form method="post" action="/admin/newsletter"><?= csrf_field() ?><button type="submit" name="action" value="test" class="btn">M’envoyer un test</button></form>
        <form method="post" action="/admin/newsletter" data-confirm="Envoyer maintenant ?|La lettre part tout de suite à <?= $fmt($stat['active']) ?> abonné(s). L’envoi automatique de cette semaine sera considéré comme fait.|Envoyer"><?= csrf_field() ?><button type="submit" name="action" value="envoyer" class="btn btn--navy"<?= $stat['active'] ? '' : ' disabled' ?>>Envoyer maintenant</button></form>
        <?php if (Auth::isAdmin()): ?><a class="btn btn--ghost" href="/admin/reglages?groupe=newsletter">Objet, texte d’introduction, jour d’envoi…</a><?php endif; ?>
      </div>
    </div>
    <div class="card card--pad">
      <h2 class="card__t card__t--sm">Ajouter un abonné</h2>
      <p class="small muted" style="margin:0 0 10px">Pour une personne qui vous a demandé de l’inscrire : elle est abonnée tout de suite, sans e-mail de confirmation. Chaque lettre garde son lien de désinscription.</p>
      <form method="post" action="/admin/newsletter" class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-end"><?= csrf_field() ?>
        <label class="f" style="flex:1 1 240px;margin:0"><span class="f__k">Adresse e-mail</span><input type="email" name="email" required maxlength="190" placeholder="prenom.nom@exemple.fr"></label>
        <label class="f" style="margin:0"><span class="f__k">Langue</span><select name="lang"><option value="fr">Français</option><option value="en">Anglais</option></select></label>
        <button type="submit" name="action" value="ajouter" class="btn btn--navy">Abonner</button>
      </form>
    </div>
    <?php if ($state): ?>
      <div class="card card--pad">
        <h2 class="card__t card__t--sm">Dernier envoi</h2>
        <p style="margin:0">Semaine <?= e((string) ($state['week'] ?? '')) ?> · commencé <?= e(Base::ago($state['started'] ?? null)) ?><?= !empty($state['finished']) ? ', terminé ' . e(Base::ago($state['finished'])) : '' ?></p>
        <p style="margin:0"><span class="ok"><?= $fmt($state['sent'] ?? 0) ?> envoyé(s)</span><?= !empty($state['failed']) ? ' · <span class="ko">' . $fmt($state['failed']) . ' échec(s)</span>' : '' ?><?= !empty($state['pending']) ? ' · ' . $fmt(count($state['pending'])) . ' en attente (envoi par lots de 150, toutes les 5 minutes)' : '' ?></p>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__t">Abonnés</h2>
      <form class="toolbar" method="get" action="/admin/newsletter"><div class="search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher une adresse…" aria-label="Rechercher un abonné"><button type="submit" aria-label="Rechercher">→</button></div></form>
    </div>
    <?php foreach ($subs as $key => $s): ?>
      <div class="card__row" style="grid-template-columns:minmax(0,1fr) auto auto">
        <span style="overflow-wrap:anywhere"><?= e((string) $s['email']) ?><br><span class="xs muted"><?= ($s['status'] ?? '') === 'active' ? 'confirmé ' . e(Base::ago($s['confirmed'] ?? $s['created'] ?? null)) : 'en attente depuis ' . e(Base::ago($s['requested'] ?? $s['created'] ?? null)) ?> · <?= e(strtoupper($s['lang'] ?? 'fr')) ?></span></span>
        <span class="pill pill--<?= ($s['status'] ?? '') === 'active' ? 'ok' : 'warn' ?>"><?= ($s['status'] ?? '') === 'active' ? 'Actif' : 'À confirmer' ?></span>
        <form method="post" action="/admin/newsletter" data-confirm="Désinscrire cette adresse ?|L’adresse sera effacée de la liste.|Désinscrire|danger"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e((string) $key) ?>"><button type="submit" name="action" value="desinscrire" class="iconbtn" title="Désinscrire" aria-label="Désinscrire">✕</button></form>
      </div>
    <?php endforeach; ?>
    <?php if (!$subs): ?><div class="card__body muted">Aucun abonné<?= $q !== '' ? ' pour cette recherche' : ' pour le moment' ?>.</div><?php endif; ?>
    <?php if ($found > count($subs)): ?><div class="card__foot small muted"><?= $fmt($found - count($subs)) ?> autres : affinez la recherche.</div><?php endif; ?>
  </div>
</div>
