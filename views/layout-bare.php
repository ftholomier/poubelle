<?php

use App\View;

/** @var array<string,mixed> $site */
/** @var string $content */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laboratoire de formes — <?= View::e($site['name'] ?? '') ?></title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= View::e(View::asset('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= View::e(View::asset('assets/css/lab.css')) ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><circle cx='16' cy='16' r='12' fill='%23c001f7'/></svg>">
</head>
<body class="lab-body">
<?= $content ?>
<script type="module" src="<?= View::e(View::asset('assets/js/lab.js')) ?>"></script>
</body>
</html>
