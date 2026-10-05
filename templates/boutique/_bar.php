<?php /** Barre de la boutique : délai, bouton du panier. Variables : $config, $count */ use App\Shop\ShopPages; ?>
<div class="shopbar"><div class="wrap shopbar__in">
  <span>🏭 <?= e($config['delay']) ?> · Paiement sécurisé · Chaque achat soutient l’association</span>
  <a class="shopbar__cart<?= $count ? ' is-full' : '' ?>" href="<?= e(ShopPages::u('/boutique/panier/')) ?>"><span class="shopbar__bag" aria-hidden="true"></span><?= $count ? 'Mon panier' : 'Panier' ?><?php if ($count): ?> <b><?= (int) $count ?></b><?php endif; ?></a>
</div></div>
