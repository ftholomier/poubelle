<?php
/** Propositions de bénévolat. Variables : $rows, $status, $poles, $counts */
use App\Admin\Base;
use App\Vitrine\Forms;
?>
<div class="toolbar">
  <div class="chips">
    <a class="chip<?= $status === '' ? ' is-on' : '' ?>" href="/admin/association/benevoles">En cours</a>
    <?php foreach (Forms::VOLUNTEER_STATUS as $k => $l): ?><a class="chip<?= $status === $k ? ' is-on' : '' ?>" href="/admin/association/benevoles?statut=<?= e($k) ?>"><?= e($l) ?> <em>· <?= (int) ($counts[$k] ?? 0) ?></em></a><?php endforeach; ?>
  </div>
  <span class="grow"></span>
  <a class="btn" href="/admin/association/benevoles/export.csv">Exporter (CSV)</a>
</div>
<?php foreach ($rows as $v): $st = $v['status'] ?? 'nouveau'; ?>
  <div class="card" id="<?= e($v['id']) ?>">
    <div class="card__head"><h2 class="card__t"><?= e(trim(($v['first'] ?? '') . ' ' . ($v['last'] ?? ''))) ?></h2><span class="pill pill--<?= $st === 'nouveau' ? 'warn' : ($st === 'actif' ? 'ok' : 'info') ?>"><?= e(Forms::VOLUNTEER_STATUS[$st] ?? $st) ?></span></div>
    <div class="card__body">
      <div class="fgrid">
        <div class="f"><span class="f__k">Coordonnées</span><span><a href="mailto:<?= e($v['email'] ?? '') ?>"><?= e($v['email'] ?? '') ?></a><?= !empty($v['phone']) ? '<br>' . e($v['phone']) : '' ?><?= !empty($v['city']) ? '<br>' . e($v['city']) : '' ?></span></div>
        <div class="f"><span class="f__k">Reçue</span><span><?= e(Base::ago($v['at'] ?? null)) ?><?= ($v['origin'] ?? '') === 'apercu' ? '<br><span class="xs muted">essai depuis l’aperçu</span>' : '' ?></span></div>
        <div class="f"><span class="f__k">Pôles</span><span><?= e(implode(', ', array_map(fn ($k) => $poles[$k] ?? $k, (array) ($v['poles'] ?? [])))) ?: '<span class="muted">—</span>' ?></span></div>
        <div class="f"><span class="f__k">Disponibilité</span><span><?= e(Forms::AVAILABILITY[$v['availability'] ?? ''] ?? '—') ?></span></div>
        <?php if (!empty($v['skills'])): ?><div class="f f--full"><span class="f__k">Compétences</span><span><?= e($v['skills']) ?></span></div><?php endif; ?>
        <?php if (!empty($v['message'])): ?><div class="f f--full"><span class="f__k">Message</span><span style="white-space:pre-wrap"><?= e($v['message']) ?></span></div><?php endif; ?>
      </div>
      <form class="row" method="post" action="/admin/association/benevoles" style="gap:8px">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($v['id']) ?>"><input type="hidden" name="statut" value="<?= e($status) ?>">
        <?php foreach (Forms::VOLUNTEER_STATUS as $k => $l): if ($k === $st) continue; ?><button type="submit" name="do" value="<?= e($k) ?>" class="btn btn--sm<?= $k === 'actif' ? ' btn--navy' : '' ?>"><?= e($l) ?></button><?php endforeach; ?>
      </form>
      <form class="stack" method="post" action="/admin/association/benevoles" style="gap:6px">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($v['id']) ?>"><input type="hidden" name="do" value="note"><input type="hidden" name="statut" value="<?= e($status) ?>">
        <textarea name="note" rows="2" placeholder="Note interne (suivi, missions confiées…)" aria-label="Note interne"><?= e((string) ($v['note'] ?? '')) ?></textarea>
        <div class="row"><button type="submit" class="btn btn--sm">Enregistrer la note</button></div>
      </form>
      <form method="post" action="/admin/association/benevoles" data-confirm="Supprimer cette proposition ?|Elle sera effacée avec les coordonnées de la personne.|Supprimer|danger"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($v['id']) ?>"><input type="hidden" name="do" value="supprimer"><button type="submit" class="btn btn--sm btn--ghost">Supprimer</button></form>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="empty">Aucune proposition<?= $status !== '' ? ' de ce type' : ' en cours' ?></div><?php endif; ?>
<p class="xs muted" style="margin:0">Les propositions restées sans suite sont effacées automatiquement après deux ans (RGPD) ; celles des bénévoles actifs sont conservées.</p>
