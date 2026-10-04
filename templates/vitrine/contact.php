<?php
/** Contact. Variables : $p, $reason, $flash, $old, $email, $phone, $address */
use App\Vitrine\Forms;
use App\Vitrine\Host;
use App\Vitrine\Site;

$o = fn (string $k) => (string) ($old[$k] ?? '');
$social = Site::social();
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => $p['title'], 'lead' => $p['lead'], 'eyebrow' => 'Écrivez-nous', 'crumbs' => [[$p['title'], Host::url('/contact/')]]]) ?>
<section class="section" id="formulaire">
  <div class="wrap vform-wrap">
    <form class="vform" method="post" action="<?= e(Host::url('/contact/')) ?>" data-protect>
      <?= csrf_field() ?>
      <input type="hidden" name="_ts" value="<?= e(form_ts()) ?>">
      <div class="hp" aria-hidden="true"><label for="ct-w">Ne pas remplir</label><input type="text" id="ct-w" name="website" tabindex="-1" autocomplete="off"></div>
      <?= \App\Core\View::partial('vitrine/partials/flash', ['flash' => $flash]) ?>
      <div class="field"><label for="ct-reason">Objet</label><select id="ct-reason" name="reason"><?php foreach (Forms::REASONS as $k => $l): ?><option value="<?= e($k) ?>"<?= ($o('reason') ?: $reason) === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="vgrid2">
        <div class="field"><label for="ct-name">Nom *</label><input type="text" id="ct-name" name="name" required maxlength="120" autocomplete="name" value="<?= e($o('name')) ?>"></div>
        <div class="field"><label for="ct-email">E-mail *</label><input type="email" id="ct-email" name="email" required maxlength="160" autocomplete="email" value="<?= e($o('email')) ?>"></div>
        <div class="field"><label for="ct-org">Organisme, média, entreprise</label><input type="text" id="ct-org" name="org" maxlength="160" autocomplete="organization" value="<?= e($o('org')) ?>"></div>
        <div class="field"><label for="ct-phone">Téléphone</label><input type="tel" id="ct-phone" name="phone" maxlength="40" autocomplete="tel" value="<?= e($o('phone')) ?>"></div>
      </div>
      <div class="field"><label for="ct-msg">Message *</label><textarea id="ct-msg" name="message" required maxlength="8000" rows="7"><?= e($o('message')) ?></textarea></div>
      <label class="checkbox"><input type="checkbox" name="consent" value="1" required> <span>J’accepte que mes coordonnées soient utilisées pour me répondre (<a href="<?= e(Host::url('/confidentialite/')) ?>">confidentialité</a>). *</span></label>
      <button type="submit" class="btn btn--navy btn--block">Envoyer le message</button>
    </form>
    <aside class="vform-side">
      <div class="vbox">
        <h2 class="h-3">Coordonnées</h2>
        <?php if ($email !== ''): ?><p class="mt-20"><a class="vmail" href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p><?php endif; ?>
        <?php if ($phone !== ''): ?><p><?= e($phone) ?></p><?php endif; ?>
        <?php if ($address): ?><div class="vaddr mt-20"><?= $address ?></div><?php endif; ?>
        <?php if ($social): ?><div class="vsocial mt-20"><?php foreach ($social as $k => [$label, $href]): ?><a href="<?= e($href) ?>" rel="noopener" target="_blank" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= \App\Core\View::partial('vitrine/partials/icon', ['name' => $k]) ?></a><?php endforeach; ?></div><?php endif; ?>
      </div>
      <div class="vbox vbox--y"><h2 class="h-3">Une erreur dans le musée ?</h2><p class="mt-20" style="font-size:17px">Une date, un score, un nom à corriger : signalez-le directement depuis la fiche concernée.</p><a class="link-under" href="<?= e(Host::museum('/contribuer/?type=correction')) ?>" target="_blank" rel="noopener">Proposer une correction ↗</a></div>
    </aside>
  </div>
</section>
