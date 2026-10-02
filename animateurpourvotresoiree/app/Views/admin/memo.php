<?php /** @var array $memo */ ?>
<div class="adm-head"><div><h1>Mémo <span class="serif">de l'équipe</span></h1><p>Notes internes partagées entre administrateurs<?= !empty($memo['updated_at']) ? ' · modifié ' . e(ago($memo['updated_at'])) . (!empty($memo['updated_by']) ? ' par ' . e($memo['updated_by']) : '') : '' ?>.</p></div></div>
<form method="post" class="form" data-dirty-check>
  <?= csrf_field() ?>
  <textarea name="html" data-editor="full" id="memo-html"><?= e((string) ($memo['html'] ?? '')) ?></textarea>
  <div class="form-actions"><button class="btn btn-coral" type="submit">Enregistrer le mémo</button></div>
</form>
