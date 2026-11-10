<?php $log = Store::get('botlog', []); ?>
<div class="bo-actions" style="margin-bottom:16px">
  <?php if (Auth::isAdmin()): ?><a class="b b--l" href="/admin/assistant">Réglages de l'assistant</a><?php endif; ?>
  <?php if ($log): ?><button class="b b--danger" type="button" data-action="botlog.clear" data-confirm="Vider le journal des conversations ?">Vider le journal</button><?php endif; ?>
</div>
<?php if (!$log): ?><div class="card bo-muted">Aucune conversation enregistrée.</div><?php endif; ?>
<?php foreach (array_slice($log, 0, 100) as $c): ?>
  <details class="card conv">
    <summary><b><?= e(date('d/m/Y H:i', strtotime($c['last']))) ?></b> · <?= count($c['msgs']) ?> message(s) · <?= e(excerpt((string) ($c['msgs'][0]['q'] ?? ''), 90)) ?></summary>
    <?php foreach ($c['msgs'] as $m): ?>
      <div class="conv__q"><?= nl2br(e($m['q'])) ?></div>
      <div class="conv__a"><?= nl2br(e($m['a'])) ?><small><?= $m['src'] === 'ia' ? 'IA' : 'Réponse automatique' ?> · <?= e(date('H:i', strtotime($m['at']))) ?></small></div>
    <?php endforeach; ?>
  </details>
<?php endforeach; ?>
