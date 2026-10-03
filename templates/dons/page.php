<?php
/**
 * Faire un don (maquette « Faire un don »).
 * Variables : $dc, $gauge, $wall, $methods, $test, $receipts, $min, $max, $flash, $old
 */
$tiers = $dc['tiers'];
$o = fn (string $k, string $d = '') => (string) ($old[$k] ?? $d);
$freq = $o('frequency', 'once') === 'month' ? 'month' : 'once';
$def = $tiers ? min(max(0, $dc['default_tier']), count($tiers) - 1) : 0;
$custom = preg_replace('/\D/', '', $o('custom'));
$sel = (int) $o('amount', (string) ($tiers[$def]['amount'] ?? 30));
$amount = $custom !== '' ? (int) $custom : $sel;
$fmt = fn (int $n) => number_format($n, 0, ',', "\u{202f}") . "\u{a0}€";
$title = explode('|', $dc['title']);
$open = (bool) $methods;
$method = isset($methods[$o('method')]) ? $o('method') : (string) array_key_first($methods ?: ['stripe' => '']);
$impactOf = function (int $amount, bool $isCustom) use ($tiers, $sel): string {
    if (!$isCustom) {
        foreach ($tiers as $t) {
            if ($t['amount'] === $sel) {
                return $t['impact'] . '.';
            }
        }
    }
    $n = intdiv($amount, 10);
    return $n >= 1 ? t($n > 1 ? 'Environ {n} affiches numérisées grâce à vous.' : 'Environ {n} affiche numérisée grâce à vous.', ['n' => $n]) : t('Chaque euro compte pour les archives.');
};
$i18n = [
    'cta' => t('♥ Je donne {n} €'),
    'month' => t(' / mois'),
    'monthPrefix' => t('Chaque mois : '),
    'one' => t('Environ {n} affiche numérisée grâce à vous.'),
    'many' => t('Environ {n} affiches numérisées grâce à vous.'),
    'small' => t('Chaque euro compte pour les archives.'),
    'min' => t('Le montant minimum est de {n} €.', ['n' => $min]),
    'max' => t('Au-delà de {n} € en ligne, contactez-nous : nous vous proposerons un chèque ou un virement.', ['n' => number_format($max, 0, ',', ' ')]),
    'error' => t('Le service de paiement ne répond pas pour le moment. Réessayez dans quelques minutes.'),
];
?>
<section class="dhero bg-navy" id="don">
  <div class="wrap dhero__grid">
    <div class="dhero__text">
      <span class="eyebrow eyebrow--lg"><?= e(t('Faire un don')) ?> · <?= e(t($gauge['label'])) ?></span>
      <h1 class="dhero__title"><?= e($title[0]) ?><?php if (isset($title[1])): ?><br><span class="yellow"><?= e($title[1]) ?></span><?php endif; ?><?php if (isset($title[2])): ?><br><?= e($title[2]) ?><?php endif; ?></h1>
      <p class="dhero__lead"><?= e($dc['lead']) ?></p>
      <?php if ($gauge['goal'] > 0): ?>
      <div class="dgauge">
        <div class="dgauge__nums">
          <span class="dgauge__raised" data-count><?= e($fmt($gauge['raised'])) ?></span>
          <span class="dgauge__of"><?= e(t('sur {goal}', ['goal' => $fmt($gauge['goal'])])) ?> · <?= e($gauge['donors'] ? t($gauge['donors'] > 1 ? '{n} donateurs' : '{n} donateur', ['n' => number_format($gauge['donors'], 0, ',', "\u{202f}")]) : t('soyez le premier donateur !')) ?></span>
        </div>
        <div class="dgauge__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e((string) $gauge['pct']) ?>" aria-label="<?= e(t('Avancement de la collecte')) ?>"><div data-gauge-bar style="width:<?= e((string) $gauge['pct']) ?>%"></div></div>
        <span class="dgauge__note"><?= e(t('{pct} % de l’objectif atteint · mise à jour à chaque don.', ['pct' => str_replace('.', ',', (string) $gauge['pct'])])) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <form class="dcard" method="post" action="<?= e(url('/faire-un-don/')) ?>" data-don-form data-api="<?= e(url('/api/dons/session')) ?>" data-i18n="<?= e(json_encode($i18n, JSON_UNESCAPED_UNICODE)) ?>" data-min="<?= (int) $min ?>" data-max="<?= (int) $max ?>" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="_ts" value="<?= e(form_ts()) ?>">
      <div class="hp" aria-hidden="true"><label>Site web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <?php if ($open && $test): ?>
        <p class="dcard__test"><b><?= e(t('Mode test')) ?></b> · <?= e(t('aucun prélèvement réel. Carte de test : 4242 4242 4242 4242, date future, code 123.')) ?></p>
      <?php endif; ?>
      <?php if ($flash): ?><p class="alert alert--<?= $flash['type'] === 'error' ? 'error' : ($flash['type'] === 'ok' ? 'ok' : 'info') ?>" role="status"><?= e($flash['msg']) ?></p><?php endif; ?>
      <p class="alert alert--error" data-don-error role="alert" hidden></p>

      <fieldset class="dfreq">
        <legend class="sr-only"><?= e(t('Fréquence du don')) ?></legend>
        <label class="dfreq__opt"><input type="radio" name="frequency" value="once"<?= $freq === 'once' ? ' checked' : '' ?>><span><?= e(t('Une fois')) ?></span></label>
        <label class="dfreq__opt"><input type="radio" name="frequency" value="month"<?= $freq === 'month' ? ' checked' : '' ?>><span><?= e(t('Chaque mois')) ?></span></label>
      </fieldset>

      <fieldset class="dtiers">
        <legend class="sr-only"><?= e(t('Montant du don')) ?></legend>
        <?php foreach ($tiers as $t): ?>
          <label class="dtier<?= $custom === '' && $t['amount'] === $sel ? ' is-on' : '' ?>">
            <input type="radio" name="amount" value="<?= (int) $t['amount'] ?>" data-impact="<?= e($t['impact']) ?>"<?= $custom === '' && $t['amount'] === $sel ? ' checked' : '' ?>>
            <span class="dtier__amount"><?= (int) $t['amount'] ?> €</span>
            <span class="dtier__impact"><?= e($t['impact']) ?></span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <label class="dcustom">
        <span><?= e(t('Autre montant')) ?></span>
        <input name="custom" value="<?= e($custom) ?>" inputmode="numeric" pattern="[0-9]*" maxlength="5" placeholder="<?= e(t('ex. 25')) ?>" autocomplete="off" data-custom aria-label="<?= e(t('Autre montant en euros')) ?>">
        <b aria-hidden="true">€</b>
      </label>

      <div class="dimpact" data-impact-line aria-live="polite"><?= e(($freq === 'month' ? t('Chaque mois : ') : '') . $impactOf($amount, $custom !== '')) ?></div>

      <?php if ($open): ?>
      <div class="dstep2" data-step2>
        <span class="dstep2__title"><?= e(t('Plus qu’une étape : vos coordonnées')) ?></span>
        <div class="cfield2">
          <label class="cfield"><span><?= e(t('Prénom')) ?></span><input name="first" required maxlength="60" autocomplete="given-name" value="<?= e($o('first')) ?>"></label>
          <label class="cfield"><span><?= e(t('Nom')) ?></span><input name="last" maxlength="80" autocomplete="family-name" value="<?= e($o('last')) ?>" data-last></label>
        </div>
        <label class="cfield"><span><?= e(t('E-mail')) ?></span><input name="email" type="email" required maxlength="160" autocomplete="email" placeholder="vous@exemple.fr" value="<?= e($o('email')) ?>"></label>
        <?php if ($receipts): ?>
          <label class="ccheck"><input type="checkbox" name="receipt" value="1" data-receipt<?= $o('receipt') ? ' checked' : '' ?>> <span><?= e(t('Je souhaite un reçu fiscal : mon don ouvre droit à une réduction d’impôt de 66 %.')) ?></span></label>
          <div class="dstep2__receipt" data-receipt-fields<?= $o('receipt') ? '' : ' hidden' ?>>
            <label class="cfield"><span><?= e(t('Adresse')) ?></span><input name="address" maxlength="160" autocomplete="street-address" value="<?= e($o('address')) ?>"></label>
            <div class="cfield2">
              <label class="cfield"><span><?= e(t('Code postal')) ?></span><input name="zip" maxlength="12" autocomplete="postal-code" value="<?= e($o('zip')) ?>"></label>
              <label class="cfield"><span><?= e(t('Commune')) ?></span><input name="city" maxlength="80" autocomplete="address-level2" value="<?= e($o('city')) ?>"></label>
            </div>
            <label class="cfield"><span><?= e(t('Pays')) ?></span><input name="country" maxlength="60" autocomplete="country-name" value="<?= e($o('country', 'France')) ?>"></label>
          </div>
        <?php endif; ?>
        <label class="ccheck"><input type="checkbox" name="wall" value="1" data-wall<?= $o('wall') ? ' checked' : '' ?>> <span><?= e(t('Afficher mon nom sur le mur des donateurs')) ?></span></label>
        <label class="cfield" data-wall-field<?= $o('wall') ? '' : ' hidden' ?>><span><?= e(t('Nom affiché')) ?></span><input name="wall_name" maxlength="40" value="<?= e($o('wall_name')) ?>" placeholder="<?= e(t('ex. Famille M., Un Lionceau depuis 1981')) ?>"></label>
        <?php if (count($methods) > 1): ?>
          <fieldset class="dmethods">
            <legend><?= e(t('Moyen de paiement')) ?></legend>
            <?php foreach ($methods as $k => $label): ?>
              <label class="dmethod"><input type="radio" name="method" value="<?= e($k) ?>"<?= $k === $method ? ' checked' : '' ?>><span><?= $k === 'stripe' ? '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M2.5 9.5h19" stroke="currentColor" stroke-width="2.4"/></svg>' : '<b class="dmethod__pp">Pay<i>Pal</i></b>' ?><?= $k === 'stripe' ? e($label) : '' ?></span></label>
            <?php endforeach; ?>
          </fieldset>
        <?php else: ?>
          <input type="hidden" name="method" value="<?= e($method) ?>">
        <?php endif; ?>
      </div>
      <button type="submit" class="dcta" data-cta>♥ <?= e(t('Je donne')) ?> <span data-cta-amount><?= (int) $amount ?></span> €<span data-cta-month<?= $freq === 'month' ? '' : ' hidden' ?>><?= e(t(' / mois')) ?></span></button>
      <div class="dthanks" data-thanks role="status" hidden><?= e(t('Merci, Lionceau ! ♥ Redirection vers le paiement sécurisé…')) ?></div>
      <span class="dcard__safe"><?= e(t('Paiement sécurisé')) ?> · <?= e($receipts ? t('reçu envoyé par e-mail') : t('confirmation envoyée par e-mail')) ?></span>
      <p class="dcard__rgpd"><?= e(t('Vos coordonnées servent uniquement à traiter votre don et à vous écrire à son sujet. Elles ne sont jamais cédées.')) ?> <a href="<?= e(url('/confidentialite/')) ?>"><?= e(t('Confidentialité')) ?></a></p>
      <?php else: ?>
      <button type="button" class="dcta" disabled>♥ <?= e(t('Dons en ligne bientôt ouverts')) ?></button>
      <p class="dcard__closed"><?= e(t('La collecte en ligne ouvre très bientôt. Pour donner dès maintenant par chèque ou par virement, écrivez-nous :')) ?> <a href="<?= e(url('/contact/') . '?objet=autre') ?>"><?= e(t('nous contacter')) ?> →</a></p>
      <?php endif; ?>
    </form>
  </div>
</section>

<section class="wrap section dsteps">
  <h2 class="h-section"><?= e(t('Où va votre don')) ?></h2>
  <div class="dsteps__grid">
    <?php foreach ($dc['steps'] as $i => $s): ?>
      <div class="dstep" data-reveal>
        <div class="dstep__media"><?php if (!empty($s['image'])): ?><img src="<?= e(img($s['image'], 800)) ?>" srcset="<?= e(srcset($s['image'], [480, 800])) ?>" sizes="(min-width: 960px) 400px, 100vw" alt="" loading="lazy"><?php else: ?><span class="dstep__ph" aria-hidden="true"><?= ['▤', '▣', '◎'][$i % 3] ?></span><?php endif; ?></div>
        <div class="dstep__body">
          <span class="dstep__n"><?= sprintf('%02d', $i + 1) ?></span>
          <span class="dstep__t"><?= e($s['title']) ?></span>
          <span class="dstep__d"><?= e($s['text']) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="dwall bg-yellow">
  <div class="wrap dwall__in">
    <div class="dwall__head"><h2 class="h-2"><?= e(t('Le mur des donateurs')) ?></h2><span class="dwall__hint"><?= e($dc['wall_text']) ?></span></div>
    <div class="dwall__names">
      <?php foreach ($wall['names'] as $i => $name): ?>
        <span class="dname<?= $i % 4 === 0 ? ' dname--navy' : '' ?><?= $i % 3 === 0 ? ' dname--lg' : '' ?>"><?= e($name) ?></span>
      <?php endforeach; ?>
      <?php if ($wall['anonymous'] > 0): ?>
        <span class="dname dname--anon"><?= e(t($wall['anonymous'] > 1 ? '+ {n} donateurs discrets' : '+ {n} donateur discret', ['n' => $wall['anonymous']])) ?></span>
      <?php endif; ?>
      <?php if (!$wall['names'] && !$wall['anonymous']): ?>
        <a class="dname dname--empty" href="#don"><?= e(t('Votre nom ici ?')) ?></a>
      <?php endif; ?>
    </div>
  </div>
</section>
