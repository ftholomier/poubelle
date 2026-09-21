<?php use App\Services\I18n; ?>
<div class="admin-head"><h1><?= e(I18n::t('error.404_title')) ?></h1></div>
<div class="admin-card"><p><?= e(I18n::t('error.404_body')) ?></p>
  <a class="btn btn-coral btn-sm" href="/admin/contenus"><?= e(I18n::t('admin.contents')) ?></a></div>
