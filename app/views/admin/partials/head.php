<?php
/** @var array $settings @var string $title */
use App\Security\Csrf;
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title ?? 'Administration') ?> — <?= e($settings['site']['name'] ?? 'Back-office') ?></title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('/assets/css/admin.css')) ?>">
<script type="application/json" id="admin-config">
<?= json_encode(['token' => Csrf::token('admin')], JSON_HEX_TAG) ?>
</script>
