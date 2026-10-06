<?php
/** En-tête des pages du carnet. Variables : $title, $intro, $crumb */
?>
<section class="mhead skhead">
  <div class="wrap mhead__inner" style="padding-bottom:clamp(28px,4vw,48px)">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><a href="<?= e(url('/interactif/')) ?>"><?= e(t('Interactif')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e($crumb) ?></span></nav>
    <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Participer')) ?> · <?= e(t('Carnet du supporter')) ?></span>
    <h1 class="mhead__title"><?= e($title) ?></h1>
    <?php if ($intro !== ''): ?><p class="mhead__intro"><?= e($intro) ?></p><?php endif; ?>
  </div>
</section>
<script type="application/json" id="cn-i18n"><?= json_encode([
    'sent' => t('Si cette adresse a un carnet, un lien vient d’y être envoyé : ouvrez-le sur cet appareil. Pensez à regarder dans les indésirables.'),
    'first' => t('Créez d’abord votre carnet.'),
    'saved' => t('Enregistré.'), 'see' => t('Voir mon carnet'), 'closed' => t('Page publique fermée.'),
    'delete' => t('Supprimer définitivement votre carnet et tous ses matchs ?'),
    'remindOn' => t('C’est noté.'), 'devices' => t('Notifications : {n} appareil(s)'),
    'noPush' => t('Activez d’abord les notifications du musée sur cet appareil :'), 'appli' => t('page L’appli'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
