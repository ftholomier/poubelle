<?php /** Message après l'envoi d'un formulaire. Variables : $flash (type, msg) */ if (!empty($flash)): ?>
<div class="alert<?= ($flash['type'] ?? '') === 'ok' ? ' alert--ok' : (($flash['type'] ?? '') === 'error' ? ' alert--error' : '') ?>" role="<?= ($flash['type'] ?? '') === 'error' ? 'alert' : 'status' ?>"><?= e((string) $flash['msg']) ?></div>
<?php endif;
