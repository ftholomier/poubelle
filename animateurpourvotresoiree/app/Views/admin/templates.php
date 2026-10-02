<?php
use App\Core\Url;
/** @var array $list */
?>
<div class="adm-head"><div><h1>Modèles <span class="serif">d'emails</span></h1><p>Emails envoyés automatiquement par le site. Personnalisez leurs textes ; les éléments dynamiques s'écrivent entre doubles accolades.</p></div></div>
<div class="table-wrap"><table class="tbl"><thead><tr><th>Modèle</th><th>Objet</th><th>État</th></tr></thead><tbody>
  <?php foreach ($list as $key => $t): ?><tr><td><a class="t-main" href="<?= e(Url::admin('emailing/modeles/' . $key)) ?>"><?= e($t['label']) ?></a><span class="t-sub"><?= e($key) ?></span></td><td class="small"><?= e($t['subject']) ?></td><td><?= $t['custom'] ? '<span class="status-pill st-active">personnalisé</span>' : '<span class="muted small">d\'origine</span>' ?></td></tr><?php endforeach; ?>
</tbody></table></div>
