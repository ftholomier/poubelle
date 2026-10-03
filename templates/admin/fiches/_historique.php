<?php
/** Historique des versions. Variables : $doc, $versions */
use App\Admin\Base;
use App\Core\Auth;

$canRestore = Auth::can('restore');
?>
<div class="fpanel" data-panel="historique">
  <div class="card" data-versions data-id="<?= (int) $doc['id'] ?>">
    <?php foreach ($versions as $i => $v): ?>
      <details class="vers"<?= $i === 0 ? ' open' : '' ?>>
        <summary class="vers__item"><span class="vers__n">v<?= (int) $v['n'] ?></span><span class="stack" style="gap:0"><span style="font-size:16px;font-weight:500"><?= e($v['message']) ?></span><span class="xs muted"><?= e($v['by']) ?> · <?= e(Base::ago($v['at'])) ?> · <?= e(date('d/m/Y H:i', strtotime($v['at']))) ?></span></span><span class="d" style="font-weight:800;font-size:13px;text-transform:uppercase;color:var(--blue)"><?= $i === 0 ? 'Actuelle' : '' ?></span></summary>
        <div style="padding:12px 16px;background:var(--cream);border-bottom:1px solid rgba(14,31,77,.12);display:flex;flex-direction:column;gap:6px">
          <?php foreach ($v['diff'] ?? [] as $d): ?><div class="diff"><b><?= e((string) $d[0]) ?></b><span><del><?= e((string) $d[1]) ?></del> → <ins><?= e((string) $d[2]) ?></ins></span></div><?php endforeach; ?>
          <?php if ($i > 0 || count($versions) > 1): ?>
            <?php if ($canRestore && $i > 0): ?>
              <button type="submit" class="btn btn--sm" form="restore-form" formaction="/admin/fiche/<?= (int) $doc['id'] ?>/version/<?= (int) $v['n'] ?>/restaurer" data-confirm-title="Restaurer la v<?= (int) $v['n'] ?> ?">↺ Restaurer cette version</button>
            <?php elseif ($i > 0): ?>
              <span class="xs muted">La restauration est réservée aux administrateurs.</span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
    <?php if (!$versions): ?><div class="card__body muted">Aucune version enregistrée.</div><?php endif; ?>
  </div>
</div>
