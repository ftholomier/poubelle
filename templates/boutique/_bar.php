<?php /** Barre de la boutique : délai, panier. Variables : $config, $count */ use App\Shop\ShopPages; ?>
<div class="shopbar"><div class="wrap shopbar__in">
  <span>🏭 <?= e($config['delay']) ?> · Paiement sécurisé · Chaque achat soutient l’association</span>
  <a class="shopbar__cart" href="<?= e(ShopPages::u('/boutique/panier/')) ?>">Panier<?= $count ? ' <b>' . (int) $count . '</b>' : '' ?></a>
</div></div>
