<?php
/** Un message de contact : lecture, réponse, suivi. Variables : $m, $users */
use App\Admin\Base;
use App\Admin\Community;
use App\Core\Auth;
use App\Front\Community as Front;

$s = $m['status'] ?? 'nouveau';
?>
<div class="cols cols--wide">
  <div class="stack">
    <div class="card">
      <div class="card__head"><h2 class="card__t"><?= e(($m['site'] ?? '') === 'association' ? 'Site de l’association · ' . (\App\Vitrine\Forms::REASONS[$m['reason']] ?? 'Message') : (Front::REASONS[$m['reason']] ?? 'Message')) ?></h2><span class="pill pill--<?= ['nouveau' => 'warn', 'lu' => 'info', 'traite' => 'ok'][$s] ?? 'warn' ?>"><?= e(Community::M_STATUS[$s] ?? $s) ?></span></div>
      <div class="card__body">
        <div class="fgrid">
          <div class="f"><span class="f__k">De</span><span><b><?= e($m['name']) ?></b><?= !empty($m['org']) ? '<br>' . e($m['org']) : '' ?></span></div>
          <div class="f"><span class="f__k">Coordonnées</span><span><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?= !empty($m['phone']) ? '<br>' . e($m['phone']) : '' ?></span></div>
          <div class="f"><span class="f__k">Reçu</span><span><?= e(date('d/m/Y à H:i', strtotime((string) $m['at']))) ?><br><span class="xs muted">langue : <?= e(strtoupper($m['lang'] ?? 'fr')) ?></span></span></div>
          <?php if (!empty($m['page'])): ?><div class="f"><span class="f__k">Depuis la page</span><span class="small" style="overflow-wrap:anywhere"><?= e($m['page']) ?></span></div><?php endif; ?>
        </div>
        <div style="white-space:pre-wrap;background:var(--cream);border:2px solid var(--navy);padding:14px;font-size:16px"><?= e($m['message']) ?></div>
      </div>
    </div>
    <?php foreach ($m['replies'] ?? [] as $r): ?>
      <div class="card card--pad" style="border-left:8px solid var(--yellow)">
        <span class="small"><b><?= e($r['by']) ?></b> a répondu <?= e(Base::ago($r['at'])) ?><?= empty($r['sent']) ? ' · <span class="ko">e-mail non parti</span>' : '' ?></span>
        <div><?= rich_inline($r['text']) ?></div>
      </div>
    <?php endforeach; ?>
    <form class="card card--pad" method="post" action="/admin/messages/<?= e($m['id']) ?>">
      <?= csrf_field() ?>
      <h2 class="card__t card__t--sm">Répondre par e-mail</h2>
      <div class="f"><span class="f__k">Votre réponse à <?= e($m['email']) ?></span><textarea name="reply" rows="8" data-wysiwyg="mini" placeholder="Bonjour <?= e($m['name']) ?>, merci pour votre message…"></textarea></div>
      <div class="row"><button type="submit" name="action" value="repondre" class="btn btn--navy">Envoyer la réponse</button><span class="f__help">Le message d’origine est cité sous votre réponse ; le statut passe à « Traité ».</span></div>
    </form>
  </div>
  <div class="stack">
    <form class="card card--pad" method="post" action="/admin/messages/<?= e($m['id']) ?>">
      <?= csrf_field() ?>
      <h2 class="card__t card__t--sm">Suivi</h2>
      <div class="f"><span class="f__k">Suivi par</span>
        <select name="to"><option value="">Personne</option><?php foreach ($users as $u): if (!empty($u['disabled'])) { continue; } ?><option<?= ($m['assigned'] ?? '') === $u['name'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
      </div>
      <div class="f"><span class="f__k">Note interne</span><textarea name="note" rows="4" data-wysiwyg="mini" placeholder="Visible uniquement par l’équipe"><?= e($m['note'] ?? '') ?></textarea></div>
      <div class="row">
        <button type="submit" name="action" value="assigner" class="btn btn--navy">Enregistrer</button>
        <?php if ($s !== 'traite'): ?><button type="submit" name="action" value="traite" class="btn">✓ Marquer traité</button><?php else: ?><button type="submit" name="action" value="lu" class="btn">Rouvrir</button><?php endif; ?>
      </div>
    </form>
    <?php if (Auth::can('destroy')): ?>
      <form class="card card--pad" method="post" action="/admin/messages/<?= e($m['id']) ?>" data-confirm="Supprimer ce message ?|Il sera effacé définitivement, avec les coordonnées de son auteur.|Supprimer|danger">
        <?= csrf_field() ?>
        <button type="submit" name="action" value="supprimer" class="btn btn--danger">Supprimer le message</button>
      </form>
    <?php endif; ?>
  </div>
</div>
