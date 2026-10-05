<?php
/** Boutique › Commande (administrateurs). Variables : $o, $previews, $base, $who */
?>
<link rel="stylesheet" href="<?= asset('admin/boutique.css') ?>">
<?= \App\Core\View::partial('shop/commande', ['o' => $o, 'previews' => $previews, 'base' => $base, 'who' => $who]) ?>
