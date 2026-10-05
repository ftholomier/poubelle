<?php
/** Notifications de l'appli. Variables : $ready, $enabled, $stats, $queue, $history, $due, $old */
use App\Admin\Base;
use App\Services\Notifications;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$o = fn (string $k, string $d = '') => (string) ($old[$k] ?? $d);
$topicName = fn (string $k) => Notifications::TOPICS[$k][0] ?? ($k === 'essai' ? 'Essai' : $k);
?>
<?php if (!$ready): ?>
  <p class="alert alert--error">Le serveur ne sait pas chiffrer les notifications (OpenSSL avec la courbe P-256, ECDH et AES-GCM, et cURL sont nécessaires). Voir Système › Réglages › Vérification du serveur.</p>
<?php elseif (!$enabled): ?>
  <p class="alert alert--error">Les notifications sont désactivées : <a href="/admin/reglages?groupe=app">Réglages › Application du musée</a>.</p>
<?php endif; ?>

<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= $fmt($stats['total']) ?></b><span>appareils abonnés</span><small><?= $fmt($stats['new30']) ?> nouveau(x) en 30 jours</small></div>
  <?php foreach (Notifications::TOPICS as $k => [$label]): ?>
    <div class="kpi"><b><?= $fmt($stats['topics'][$k] ?? 0) ?></b><span><?= e($label) ?></span><small>abonnés à ce sujet</small></div>
  <?php endforeach; ?>
  <div class="kpi"><b><?= $fmt($stats['langs']['en'] ?? 0) ?></b><span>en anglais</span><small>reçoivent la version anglaise</small></div>
</div>

<div class="cols cols--wide">
  <div class="stack">
    <form class="card" method="post" action="/admin/notifications" data-notif-form
          data-confirm="Envoyer la notification ?|Elle part tout de suite sur les téléphones des abonnés au sujet choisi. Impossible de la rattraper.|Envoyer">
      <?= csrf_field() ?>
      <div class="card__head"><h2 class="card__t">Envoyer une notification</h2></div>
      <div class="card__body stack">
        <label class="f"><span class="f__k">Titre <small class="muted" data-count="title">0/60</small></span><input class="in" name="title" maxlength="60" required value="<?= e($o('title')) ?>" placeholder="Ex. : Le Onze de légende : à vous de voter !" data-preview="title"></label>
        <label class="f"><span class="f__k">Texte <small class="muted" data-count="body">0/180</small></span><textarea class="in" name="body" rows="3" maxlength="180" placeholder="Une ou deux phrases." data-preview="body"><?= e($o('body')) ?></textarea></label>
        <label class="f"><span class="f__k">Page ouverte au clic</span><input class="in" name="url" value="<?= e($o('url', '/')) ?>" placeholder="/centenaire/" pattern="/.*|https?://.*"></label>
        <label class="f"><span class="f__k">Sujet (seuls ses abonnés la reçoivent)</span>
          <select class="in" name="topic">
            <?php foreach (Notifications::TOPICS as $k => [$label]): ?>
              <option value="<?= e($k) ?>"<?= $o('topic', 'nouvelles') === $k ? ' selected' : '' ?>><?= e($label) ?> · <?= $fmt($stats['topics'][$k] ?? 0) ?> abonné(s)</option>
            <?php endforeach; ?>
          </select>
        </label>
        <details<?= $o('title_en') !== '' ? ' open' : '' ?>>
          <summary class="small">Version anglaise (facultative : sinon, les abonnés anglophones reçoivent le français)</summary>
          <div class="stack" style="margin-top:10px">
            <label class="f"><span class="f__k">Title</span><input class="in" name="title_en" maxlength="60" value="<?= e($o('title_en')) ?>"></label>
            <label class="f"><span class="f__k">Text</span><textarea class="in" name="body_en" rows="2" maxlength="180"><?= e($o('body_en')) ?></textarea></label>
          </div>
        </details>
        <div class="notif-preview" aria-hidden="true">
          <img src="/assets/img/app/192.png" alt="" width="40" height="40">
          <div><b data-out="title"><?= e($o('title') ?: 'Titre de la notification') ?></b><span data-out="body"><?= e($o('body') ?: 'Le texte apparaît ici.') ?></span><small>Sochaux Rétro · musee.fcsochauxretro.com</small></div>
        </div>
      </div>
      <div class="card__foot">
        <button type="submit" name="action" value="envoyer" class="btn btn--navy"<?= $enabled ? '' : ' disabled' ?>>Envoyer</button>
        <span class="small muted">Pour vérifier avant : abonnez votre téléphone sur la page <a href="/appli/#notifications" target="_blank" rel="noopener">L’appli du musée</a> et utilisez « M’envoyer un essai ».</span>
      </div>
    </form>

    <div class="card">
      <div class="card__head"><h2 class="card__t">Envois automatiques</h2><a class="linkbtn" href="/admin/reglages?groupe=app">Réglages</a></div>
      <div class="card__body small">
        <p style="margin:0">Le coup d’envoi des Rétro-Direct (30 minutes avant), chaque moment du centenaire à sa parution, le kit souvenirs du mois (première semaine, à 10 h) et « Ce jour-là » le matin, pour les abonnés à ces sujets. Jamais entre 21 h 30 et 8 h, sauf un Rétro-Direct programmé ; jamais deux fois la même.</p>
        <?php if ($due): ?>
          <p style="margin:10px 0 0"><b>Prêt à partir au prochain passage de la tâche planifiée :</b></p>
          <?php foreach ($due as [$key, $topic, $msg]): ?>
            <div class="row" style="gap:8px"><span class="pill"><?= e($topicName($topic)) ?></span><?= e($msg['fr']['title']) ?></div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="muted" style="margin:10px 0 0">Rien à envoyer pour le moment.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__t">Envois</h2></div>
    <?php foreach ($queue as $j): ?>
      <div class="card__row" style="grid-template-columns:minmax(0,1fr) auto">
        <span><b><?= e($j['title']) ?></b><br><span class="xs muted"><?= e($topicName($j['topic'])) ?> · en cours : <?= $fmt($j['sent']) ?>/<?= $fmt($j['n']) ?> · <?= e($j['by']) ?></span></span>
        <form method="post" action="/admin/notifications"><?= csrf_field() ?><button type="submit" name="action" value="relancer" class="btn btn--sm">Continuer</button></form>
      </div>
    <?php endforeach; ?>
    <?php foreach ($history as $j): ?>
      <div class="card__row" style="grid-template-columns:minmax(0,1fr) auto">
        <span><b><?= e($j['title']) ?></b><?php if (($j['body'] ?? '') !== ''): ?><br><span class="small"><?= e($j['body']) ?></span><?php endif; ?><br>
          <span class="xs muted"><?= e(date('d/m/Y à H:i', strtotime((string) $j['at']))) ?> · <?= e($topicName($j['topic'])) ?> · <?= e($j['by'] ?: '—') ?> · <a href="<?= e($j['url']) ?>" target="_blank" rel="noopener"><?= e($j['url']) ?></a></span>
          <?php if (!empty($j['errors'])): ?><br><span class="xs ko">Erreurs : <?= e(implode(' · ', $j['errors'])) ?></span><?php endif; ?></span>
        <span class="small" style="text-align:right;white-space:nowrap"><b><?= $fmt($j['ok']) ?></b>/<?= $fmt($j['n']) ?> reçue(s)<br><span class="xs muted"><?= $fmt($j['opened'] ?? 0) ?> ouverture(s)<?= $j['gone'] ? ' · ' . $fmt($j['gone']) . ' disparu(s)' : '' ?><?= $j['failed'] ? ' · ' . $fmt($j['failed']) . ' échec(s)' : '' ?></span></span>
      </div>
    <?php endforeach; ?>
    <?php if (!$history && !$queue): ?><div class="card__body muted">Aucune notification envoyée pour le moment.</div><?php endif; ?>
    <div class="card__foot xs muted">« Reçue » : acceptée par le service de notifications du téléphone (Google, Apple, Mozilla, Microsoft). « Disparu » : appli désinstallée ou notifications retirées, abonnement effacé. « Ouverture » : clic sur la notification.</div>
  </div>
</div>
