<?php $st = Store::get('fb_state', []); ?>
<div class="card">
  <h2>Synchronisation</h2>
  <p class="bo-muted"><?= !empty($st['at']) ? 'Dernière synchronisation : ' . e(date('d/m/Y à H:i', strtotime($st['at']))) . ' (' . (int) ($st['added'] ?? 0) . ' ajoutée(s), ' . (int) ($st['updated'] ?? 0) . ' mise(s) à jour).' : 'Jamais synchronisée.' ?></p>
  <p class="bo-muted">Les publications de la page deviennent des actualités du site, mélangées aux autres par date et sans distinction. Une actu importée puis supprimée ici n'est plus réimportée. Une actu créée dans le back-office peut être publiée sur la page (case à cocher de l'actu).</p>
  <button class="b b--y" type="button" data-action="facebook.sync"<?= Facebook::configured() ? '' : ' disabled' ?>>Synchroniser maintenant</button>
</div>
