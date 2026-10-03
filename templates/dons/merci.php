<?php
/** Retour de paiement. Variables : $don (ou null), $state (ok|pending|failed|unknown), $manage */
use App\Front\Donations;

$first = $don['donor']['first'] ?? '';
$monthly = ($don['frequency'] ?? '') === 'month';
$amount = $don ? Donations::money((int) $don['amount']) . ($monthly ? ' ' . t('par mois') : '') : '';
$mask = function (string $email): string {
    [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($u, 0, 1) . str_repeat('•', max(2, min(6, mb_strlen($u) - 1))) . '@' . $d;
};
$share = base_url() . url('/faire-un-don/');
?>
<section class="dmerci bg-navy">
  <div class="wrap dmerci__in">
    <?php if ($state === 'ok'): ?>
      <span class="eyebrow eyebrow--lg"><?= e(t('Don confirmé')) ?><?= ($don['mode'] ?? '') === 'test' ? ' · ' . e(t('mode test')) : '' ?></span>
      <h1 class="dmerci__title"><?= e(t('Merci,')) ?> <span class="yellow"><?= e($first ?: t('Lionceau')) ?> !</span></h1>
      <p class="dhero__lead"><?= e(t('Votre don de {montant} aide Sochaux Rétro à préserver l’histoire du FCSM avant ses 100 ans.', ['montant' => $amount])) ?></p>
      <dl class="dmerci__facts">
        <div><dt><?= e(t('Montant')) ?></dt><dd><?= e($amount) ?></dd></div>
        <div><dt><?= e(t('Paiement')) ?></dt><dd><?= e(t(Donations::PROVIDERS[$don['provider']] ?? $don['provider'])) ?></dd></div>
        <div><dt><?= e(t('Confirmation')) ?></dt><dd><?= e($mask((string) ($don['donor']['email'] ?? ''))) ?></dd></div>
      </dl>
      <p class="dmerci__note">
        <?php if (!empty($don['receipt'])): ?><?= e($monthly ? t('Votre reçu fiscal annuel vous sera envoyé par e-mail en janvier.') : t('Votre reçu fiscal est joint à l’e-mail de confirmation.')) ?> <?php endif; ?>
        <?= e($monthly ? t('Vous pouvez arrêter votre don mensuel à tout moment depuis votre lien personnel.') : t('Votre lien personnel permet de retrouver votre don et de choisir votre nom sur le mur des donateurs.')) ?>
        <a href="<?= e($manage) ?>"><?= e(t('Gérer mon don')) ?> →</a>
      </p>
    <?php elseif ($state === 'pending'): ?>
      <span class="eyebrow eyebrow--lg"><?= e(t('Paiement en cours')) ?></span>
      <h1 class="dmerci__title"><?= e(t('Encore un')) ?> <span class="yellow"><?= e(t('instant…')) ?></span></h1>
      <p class="dhero__lead"><?= e(t('Nous attendons la confirmation de votre banque. Vous recevrez un e-mail dès qu’elle arrive : inutile de payer une seconde fois.')) ?></p>
      <p><a class="btn btn--yellow" href="" data-reload><?= e(t('Actualiser')) ?></a></p>
    <?php elseif ($state === 'failed'): ?>
      <span class="eyebrow eyebrow--lg"><?= e(t('Paiement non abouti')) ?></span>
      <h1 class="dmerci__title"><?= e(t('Le paiement')) ?> <span class="yellow"><?= e(t('n’a pas abouti.')) ?></span></h1>
      <p class="dhero__lead"><?= e(t('Aucun montant n’a été prélevé. Vous pouvez réessayer, avec le même moyen de paiement ou un autre.')) ?></p>
      <p><a class="btn btn--yellow" href="<?= e(url('/faire-un-don/')) ?>#don">♥ <?= e(t('Réessayer')) ?></a></p>
    <?php else: ?>
      <span class="eyebrow eyebrow--lg"><?= e(t('Faire un don')) ?></span>
      <h1 class="dmerci__title"><?= e(t('Merci pour')) ?> <span class="yellow"><?= e(t('votre soutien !')) ?></span></h1>
      <p class="dhero__lead"><?= e(t('Si vous venez de faire un don, vous recevrez sa confirmation par e-mail dans quelques minutes.')) ?></p>
    <?php endif; ?>
  </div>
</section>
<?php if ($state === 'ok' || $state === 'unknown'): ?>
<section class="wrap section dnext">
  <h2 class="h-2"><?= e(t('Et maintenant ?')) ?></h2>
  <div class="dnext__grid">
    <a class="dnext__card" href="<?= e(url('/centenaire/')) ?>"><span class="eyebrow"><?= e(t('Centenaire')) ?></span><b><?= e(t('100 ans, 100 moments')) ?></b><span><?= e(t('Votez pour votre Onze de légende.')) ?></span></a>
    <a class="dnext__card" href="<?= e(url('/interactif/quiz/')) ?>"><span class="eyebrow"><?= e(t('Interactif')) ?></span><b><?= e(t('Le grand quiz')) ?></b><span><?= e(t('Êtes-vous incollable sur le FCSM ?')) ?></span></a>
    <div class="dnext__card dnext__card--share"><span class="eyebrow"><?= e(t('Faites passer le mot')) ?></span><b><?= e(t('Partagez la collecte')) ?></b>
      <span class="dnext__share">
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($share) ?>" target="_blank" rel="noopener">Facebook</a>
        <a href="https://twitter.com/intent/tweet?url=<?= rawurlencode($share) ?>&amp;text=<?= rawurlencode(t('J’ai soutenu le musée Sochaux Rétro pour les 100 ans du FCSM !')) ?>" target="_blank" rel="noopener">X</a>
        <a href="https://wa.me/?text=<?= rawurlencode(t('J’ai soutenu le musée Sochaux Rétro pour les 100 ans du FCSM !') . ' ' . $share) ?>" target="_blank" rel="noopener">WhatsApp</a>
      </span>
    </div>
  </div>
</section>
<?php endif; ?>
<?php if ($state === 'pending'): ?><span data-pending-reload hidden></span><?php endif; ?>
