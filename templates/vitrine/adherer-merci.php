<?php
/** Après l'adhésion. Variables : $a (null si inconnue), $address */
use App\Vitrine\Host;
use App\Vitrine\Membership;
use App\Vitrine\Site;

$state = $a === null ? 'unknown' : match ($a['status']) { 'paid' => 'ok', 'offline' => 'offline', 'pending' => 'pending', default => 'failed' };
?>
<section class="section vthanks">
  <div class="wrap vthanks__in">
    <?php if ($state === 'ok'): ?>
      <span class="vthanks__i" aria-hidden="true">★</span>
      <h1 class="h-section">Bienvenue, <?= e($a['member']['first']) ?> !</h1>
      <p class="lead">Votre adhésion <?= (int) $a['year'] ?> est enregistrée et payée (<?= e($a['label']) ?>, <?= e(Membership::money((int) $a['amount'])) ?>). Un e-mail de confirmation vous a été envoyé.</p>
    <?php elseif ($state === 'offline'): ?>
      <span class="vthanks__i" aria-hidden="true">✎</span>
      <h1 class="h-section">Merci, <?= e($a['member']['first']) ?> !</h1>
      <p class="lead">Votre demande d’adhésion est enregistrée. Il reste à envoyer votre règlement de <b><?= e(Membership::money((int) $a['amount'])) ?></b> par chèque à l’ordre de <?= e(Site::name()) ?><?= $address ? ', à l’adresse ci-dessous' : '' ?>, ou à le remettre à un membre du bureau.</p>
      <div class="vbox vrecap">
        <h2 class="h-3">Récapitulatif à joindre</h2>
        <dl class="vinfo mt-20">
          <div><dt>Référence</dt><dd><?= e($a['id']) ?></dd></div>
          <div><dt>Adhérent</dt><dd><?= e(trim($a['member']['first'] . ' ' . $a['member']['last'])) ?></dd></div>
          <div><dt>Formule</dt><dd><?= e($a['label']) ?> · <?= e(Membership::money((int) $a['amount'])) ?></dd></div>
        </dl>
        <?php if ($address): ?><div class="vaddr mt-20"><?= $address ?></div><?php endif; ?>
        <button type="button" class="btn btn--ghost btn--sm mt-20" data-print>Imprimer ce récapitulatif</button>
      </div>
    <?php elseif ($state === 'pending'): ?>
      <span class="vthanks__i" aria-hidden="true">…</span>
      <h1 class="h-section">Paiement en cours de confirmation</h1>
      <p class="lead">Le prestataire de paiement ne nous a pas encore confirmé votre règlement. Vous recevrez un e-mail dès que ce sera fait ; inutile de recommencer.</p>
    <?php elseif ($state === 'failed'): ?>
      <span class="vthanks__i" aria-hidden="true">!</span>
      <h1 class="h-section">Le paiement n’a pas abouti</h1>
      <p class="lead">Aucun montant n’a été prélevé. Vous pouvez réessayer, ou choisir le règlement par chèque.</p>
      <a class="btn btn--navy" href="<?= e(Host::url('/nous-soutenir/adherer/')) ?>">Réessayer</a>
    <?php else: ?>
      <h1 class="h-section">Merci pour votre soutien</h1>
      <p class="lead">Si vous venez d’adhérer, un e-mail de confirmation vous parviendra très vite.</p>
    <?php endif; ?>
    <div class="row gap-14 mt-40">
      <a class="btn btn--yellow" href="<?= e(Host::museum('/')) ?>" target="_blank" rel="noopener">Visiter le musée en ligne ↗</a>
      <a class="btn btn--ghost" href="<?= e(Host::url('/nous-soutenir/benevolat/')) ?>">Devenir bénévole</a>
    </div>
  </div>
</section>
