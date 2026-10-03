<?php
/** Détail d'un don. Variables : $d, $receipts, $numbers */
use App\Admin\Base;
use App\Core\Auth;
use App\Front\Donations as Front;

$donor = $d['donor'] ?? [];
$paidRefs = array_column(array_filter($d['payments'] ?? [], fn ($p) => $p['status'] === 'paid' && empty($p['receipt'])), 'ref');
?>
<div class="cols cols--wide">
  <div class="stack">
    <div class="card">
      <div class="card__head"><h2 class="card__t"><?= e(Front::money((int) $d['amount'])) ?><?= $d['frequency'] === 'month' ? ' par mois' : '' ?></h2><span class="pill"><?= e(Front::STATUS[$d['status']] ?? $d['status']) ?></span></div>
      <div class="card__body">
        <div class="fgrid">
          <div class="f"><span class="f__k">Donateur</span><span><b><?= e(trim(($donor['first'] ?? '') . ' ' . ($donor['last'] ?? '')) ?: '—') ?></b><br><?= ($donor['email'] ?? '') !== '' ? '<a href="mailto:' . e($donor['email']) . '">' . e($donor['email']) . '</a>' : '<span class="muted">pas d’e-mail</span>' ?></span></div>
          <div class="f"><span class="f__k">Adresse</span><span><?= e(trim(($donor['address'] ?? '') . ' ' . ($donor['zip'] ?? '') . ' ' . ($donor['city'] ?? '') . ' ' . ($donor['country'] ?? ''))) ?: '<span class="muted">—</span>' ?></span></div>
          <div class="f"><span class="f__k">Moyen</span><span><?= e(Front::PROVIDERS[$d['provider']] ?? $d['provider']) ?><?= !empty($d['method_detail']) ? ' (' . e($d['method_detail']) . ')' : '' ?><?= ($d['mode'] ?? '') === 'test' ? ' <span class="pill pill--info">test</span>' : '' ?></span></div>
          <div class="f"><span class="f__k">Créé</span><span><?= e(date('d/m/Y à H:i', strtotime((string) $d['created']))) ?><?= !empty($d['by']) ? '<br><span class="xs muted">saisi par ' . e($d['by']) . '</span>' : '' ?></span></div>
          <div class="f"><span class="f__k">Reçu fiscal demandé</span><span><?= !empty($d['receipt']) ? 'oui' : 'non' ?></span></div>
          <div class="f"><span class="f__k">Référence</span><span class="xs" style="overflow-wrap:anywhere"><?= e($d['id']) ?><?= !empty($d['ext']) ? '<br>' . e(implode(' · ', array_filter(array_map('strval', $d['ext'])))) : '' ?></span></div>
        </div>
        <?php if (!empty($d['error'])): ?><p class="alert alert--error" style="margin:0">Dernière erreur : <?= e($d['error']) ?></p><?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card__head"><h2 class="card__t">Paiements</h2><span class="card__note"><?= count($d['payments'] ?? []) ?></span></div>
      <?php foreach ($d['payments'] ?? [] as $p): ?>
        <div class="card__row" style="grid-template-columns:110px 110px minmax(0,1fr) auto">
          <span class="small"><?= e(date('d/m/Y', strtotime((string) $p['at']))) ?></span>
          <b class="d"><?= e(Front::money((int) $p['amount'])) ?></b>
          <span class="small"><?= $p['status'] === 'paid' ? '<span class="ok">payé</span>' : '<span class="ko">' . e($p['status']) . '</span>' ?><?= !empty($p['receipt']) ? ' · reçu <a href="/admin/dons/recu/' . e($p['receipt']) . '" target="_blank" rel="noopener">' . e($p['receipt']) . '</a>' : '' ?><br><span class="xs muted" style="overflow-wrap:anywhere"><?= e($p['ref']) ?></span></span>
          <?php if ($p['status'] === 'paid'): ?>
            <form method="post" action="/admin/dons/<?= e($d['id']) ?>" data-confirm="Noter ce paiement comme remboursé ?|Il sort de la jauge. Le remboursement lui-même se fait chez le prestataire.|Confirmer|danger"><?= csrf_field() ?><input type="hidden" name="ref" value="<?= e($p['ref']) ?>"><button type="submit" name="action" value="rembourse" class="btn btn--sm">Remboursé</button></form>
          <?php else: ?><span></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (empty($d['payments'])): ?><div class="card__body muted">Aucun paiement reçu<?= $d['status'] === 'pending' ? ' (paiement non finalisé par le donateur)' : '' ?>.</div><?php endif; ?>
    </div>
  </div>
  <div class="stack">
    <form class="card card--pad" method="post" action="/admin/dons/<?= e($d['id']) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="mur">
      <h2 class="card__t card__t--sm">Mur des donateurs</h2>
      <div class="f"><span class="f__k">Nom affiché <i>vide = donateur discret</i></span><input type="text" name="wall_name" value="<?= e($d['wall_name'] ?? '') ?>" maxlength="40"></div>
      <label class="toggle"><input type="checkbox" name="wall_hidden" value="1"<?= !empty($d['wall_hidden']) ? ' checked' : '' ?>><span class="toggle__box"></span><span>Masquer du mur (modération)</span></label>
      <button type="submit" class="btn btn--navy" style="align-self:flex-start">Enregistrer</button>
    </form>
    <?php if ($receipts): ?>
      <form class="card card--pad" method="post" action="/admin/dons/<?= e($d['id']) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="recu">
        <h2 class="card__t card__t--sm">Reçus fiscaux</h2>
        <?php foreach ($numbers as $n): ?><a href="/admin/dons/recu/<?= e($n) ?>" target="_blank" rel="noopener"><?= e($n) ?> (PDF) ↗</a><?php endforeach; ?>
        <?php if ($paidRefs): ?>
          <p class="small muted" style="margin:0"><?= count($paidRefs) ?> paiement(s) sans reçu.</p>
          <label class="toggle"><input type="checkbox" name="send" value="1" checked><span class="toggle__box"></span><span>Envoyer le reçu par e-mail</span></label>
          <button type="submit" class="btn btn--navy" style="align-self:flex-start">Émettre un reçu</button>
        <?php elseif (!$numbers): ?><p class="small muted" style="margin:0">Aucun paiement à reçu.</p><?php endif; ?>
      </form>
    <?php endif; ?>
    <?php if ($d['frequency'] === 'month' && $d['status'] === 'active'): ?>
      <form class="card card--pad" method="post" action="/admin/dons/<?= e($d['id']) ?>" data-confirm="Arrêter ce don mensuel ?|Le prestataire arrête les prélèvements et le donateur est prévenu par e-mail.|Arrêter|danger">
        <?= csrf_field() ?>
        <h2 class="card__t card__t--sm">Don mensuel</h2>
        <p class="small muted" style="margin:0">Le donateur peut aussi l’arrêter lui-même depuis le lien reçu par e-mail.</p>
        <button type="submit" name="action" value="arreter" class="btn btn--danger" style="align-self:flex-start">Arrêter les prélèvements</button>
      </form>
    <?php endif; ?>
    <form class="card card--pad" method="post" action="/admin/dons/<?= e($d['id']) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="note">
      <h2 class="card__t card__t--sm">Note interne</h2>
      <textarea name="note" rows="3" data-wysiwyg="mini"><?= e($d['note'] ?? '') ?></textarea>
      <button type="submit" class="btn btn--sm" style="align-self:flex-start">Enregistrer la note</button>
    </form>
    <?php if (Auth::can('destroy') && !array_filter($d['payments'] ?? [], fn ($p) => $p['status'] === 'paid')): ?>
      <form method="post" action="/admin/dons/<?= e($d['id']) ?>" data-confirm="Supprimer ce don non abouti ?|Ses données sont effacées.|Supprimer|danger"><?= csrf_field() ?><button type="submit" name="action" value="supprimer" class="btn btn--danger btn--sm">Supprimer ce don non abouti</button></form>
    <?php endif; ?>
  </div>
</div>
