<?php
use App\Core\Fs;
use App\Core\Url;
/** @var array $list */
?>
<div class="adm-head"><div><h1>Archives <span class="serif">de l'ancien site</span></h1><p>Tables historiques conservées en lecture seule après la migration (factures, statistiques, parrainage…). Les mots de passe ont été masqués.</p></div></div>
<?php if (!$list): ?><div class="empty-sm">Aucune archive : la migration n'a pas encore été lancée.</div><?php else: ?>
<div class="table-wrap"><table class="tbl"><thead><tr><th>Table</th><th class="num">Taille</th></tr></thead><tbody>
  <?php foreach ($list as $t => $size): ?><tr><td><a class="t-main" href="<?= e(Url::admin('archives/' . $t)) ?>"><?= e($t) ?></a></td><td class="num"><?= e(Fs::humanSize($size)) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
