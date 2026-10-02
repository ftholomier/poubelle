<?php
/** @var array $pro @var array $items @var bool $enabled */
$avg = (float) ($pro['rating']['avg'] ?? 0);
$n = (int) ($pro['rating']['count'] ?? 0);
?>
<div class="pro-head">
  <div><p class="mono muted small">Avis clients</p><h1 class="h2">Ce que disent <span class="serif c-coral">vos clients</span></h1></div>
  <div class="rating-summary"><span class="big"><?= $n ? number_format($avg, 1, ',', '') : '–' ?></span><span><span class="c-coral" style="font-size:22px"><?= str_repeat('★', (int) round($avg)) ?><?= str_repeat('☆', 5 - (int) round($avg)) ?></span><br><span class="muted small"><?= $n ?> avis publiés</span></span></div>
</div>

<?php if (!$enabled): ?>
  <div class="alert alert-info mt-2">Les avis clients sont momentanément désactivés sur le site.</div>
<?php endif; ?>

<form class="box mt-2" method="post" action="/espace-pro/avis/inviter">
  <?= csrf_field() ?>
  <h2>Inviter vos clients ✉️</h2>
  <p class="small">Saisissez l'email de vos derniers clients : ils reçoivent une invitation personnalisée et leur avis portera la mention <span class="verified">✓ Client vérifié</span>.</p>
  <div class="field"><label for="inv-emails">Emails (séparés par des virgules ou des retours à la ligne, 10 maximum)</label>
    <textarea id="inv-emails" name="emails" rows="3" placeholder="marie.dupont@email.fr, jean@email.fr"></textarea></div>
  <button class="btn btn-coral btn-sm" type="submit"<?= ($pro['status'] ?? '') !== 'active' ? ' disabled' : '' ?>>Envoyer les invitations</button>
</form>

<h2 class="section-title mt-3">Avis reçus</h2>
<?php if (!$items): ?>
  <div class="empty">Aucun avis pour l'instant. Invitez vos derniers clients : c'est le meilleur moyen d'en obtenir !</div>
<?php else: ?>
  <div class="reviews">
    <?php foreach ($items as $r): ?>
      <article class="review" id="avis-<?= (int) $r['id'] ?>">
        <div class="row-wrap" style="justify-content:space-between">
          <span class="stars"><?= str_repeat('★', (int) $r['rating']) ?><?= str_repeat('☆', 5 - (int) $r['rating']) ?></span>
          <?php if ($r['status'] === 'pending'): ?><span class="status-pill st-pending">En relecture</span><?php endif; ?>
        </div>
        <?php if (!empty($r['title'])): ?><h3 class="mt-1" style="font-size:18px"><?= e($r['title']) ?></h3><?php endif; ?>
        <p><?= nl2br(e($r['body'])) ?></p>
        <div class="who"><?= e($r['author_name']) ?> · <?= e(date_fr((string) ($r['created_at'] ?? ''), 'short')) ?><?= !empty($r['verified_client']) ? ' · <span class="verified">✓ Client vérifié</span>' : '' ?></div>
        <?php if ($r['status'] === 'approved'): ?>
          <form method="post" action="/espace-pro/avis/repondre" class="reply-form mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="review" value="<?= (int) $r['id'] ?>">
            <div class="field"><label for="rp-<?= (int) $r['id'] ?>">Votre réponse publique</label>
              <textarea id="rp-<?= (int) $r['id'] ?>" name="reply" rows="3" maxlength="1500" placeholder="Merci pour votre confiance !"><?= e($r['reply']['text'] ?? '') ?></textarea></div>
            <button class="btn btn-xs btn-ink" type="submit"><?= !empty($r['reply']) ? 'Modifier la réponse' : 'Publier la réponse' ?></button>
          </form>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
