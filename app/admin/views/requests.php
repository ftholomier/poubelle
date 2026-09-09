<?php
/** Demandes entrantes : contact, réservations, rappels de disponibilités. */

use App\Admin;
use App\Csrf;
use App\Requests;
use App\Router;
use App\Text;
use App\View;

echo View::admin('_layout_start', get_defined_vars());

$typeLabels = ['contact' => 'Contact', 'reserve' => 'Réservation', 'lead' => 'Rappel dispos'];
?>
<div class="screen">
  <section class="panel" style="margin-top:24px">
    <div class="panel__head">
      <h2><?= \count($requests) ?> demande(s)</h2>
      <span class="panel__file">content/requests.json · conservation 24 mois</span>
    </div>
    <div class="panel__scroll">
      <?php if ($requests === []): ?>
        <div class="panel__body muted">Aucune demande pour l'instant.</div>
      <?php else: ?>
        <div class="row row--head row--requests">
          <span>PERSONNE</span><span>SUJET</span><span>REÇUE</span><span>STATUT</span><span style="text-align:right">ACTION</span>
        </div>
        <?php foreach ($requests as $request):
            $ref = (string) ($request['ref'] ?? '');
            $status = (string) ($request['status'] ?? 'new'); ?>
          <div class="row row--requests">
            <div>
              <div class="row__title"><?= Text::e((string) ($request['name'] ?? '—')) ?></div>
              <div class="row__sub">
                <a href="mailto:<?= Text::e((string) ($request['email'] ?? '')) ?>"><?= Text::e((string) ($request['email'] ?? '')) ?></a>
                <?= !empty($request['phone']) ? ' · ' . Text::e((string) $request['phone']) : '' ?>
              </div>
            </div>
            <div>
              <div class="row__value"><?= Text::e((string) ($request['subject'] ?? '')) ?></div>
              <div class="row__sub">
                <?= Text::e($typeLabels[(string) ($request['type'] ?? 'contact')] ?? '') ?> · <?= Text::e($ref) ?>
                <?= !empty($request['startDate']) ? ' · entrée ' . Text::e((string) $request['startDate']) : '' ?>
              </div>
              <?php if (!empty($request['message'])): ?>
                <div class="row__sub" style="margin-top:8px;white-space:pre-line;opacity:.8"><?= Text::e(mb_substr((string) $request['message'], 0, 400)) ?></div>
              <?php endif; ?>
            </div>
            <div class="row__value"><?= Text::e(Admin::humanDate((string) ($request['at'] ?? ''))) ?></div>
            <form method="post" action="<?= Text::e(Router::adminUrl()) ?>">
              <?= Csrf::field('admin') ?>
              <input type="hidden" name="action" value="request-status">
              <input type="hidden" name="ref" value="<?= Text::e($ref) ?>">
              <select class="field field--sm" name="status" onchange="this.form.submit()" style="background:<?= Text::e(Requests::statusColor($status)) ?>">
                <?php foreach (Requests::STATUSES as $value => $label): ?>
                  <option value="<?= Text::e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= Text::e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <noscript><button class="btn btn--sm btn--outline" type="submit">OK</button></noscript>
            </form>
            <div class="row__actions">
              <form method="post" action="<?= Text::e(Router::adminUrl()) ?>" data-confirm="Supprimer définitivement cette demande ?">
                <?= Csrf::field('admin') ?>
                <input type="hidden" name="action" value="request-delete">
                <input type="hidden" name="ref" value="<?= Text::e($ref) ?>">
                <button class="btn btn--sm btn--danger" type="submit">Supprimer</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>
<?= View::admin('_layout_end') ?>
