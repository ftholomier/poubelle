<?php
/** @var array $upcoming @var array $past @var array $cfg @var string $icsUrl @var array $preview @var string $csrf */
$wdNames = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
$byDay = (array) ($cfg['slots_by_day'] ?? []);
?>
<div class="grid2 grid2--wide">
  <section class="card">
    <div class="card__head"><h2>À venir</h2><span class="muted"><?= count($upcoming) ?></span></div>
    <?php if (!$upcoming): ?>
      <p class="muted">Aucun rendez-vous à venir.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Quand</th><th>Qui</th><th class="hide-sm">Contact</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($upcoming as $b): ?>
            <tr>
              <td><b><?= e(date_fr($b['date'], true, false)) ?></b><br><small><?= e($b['time']) ?> · <?= (int) $b['duration'] ?> min · <?= e($b['mode']) ?></small></td>
              <td><b><?= e($b['name']) ?></b><?= $b['sms'] ? '<br><small class="muted">Rappel SMS demandé</small>' : '' ?></td>
              <td class="hide-sm"><small><a href="mailto:<?= e($b['email']) ?>"><?= e($b['email']) ?></a><br><?php if ($b['phone']): ?><a href="<?= e(tel_href($b['phone'])) ?>"><?= e($b['phone']) ?></a><?php endif; ?></small></td>
              <td>
                <form method="post" action="/admin/rendez-vous/<?= e($b['id']) ?>/annuler" data-confirm="Annuler ce rendez-vous ? Le créneau redevient disponible.">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <label class="check small"><input type="checkbox" name="notify" value="1" checked> prévenir par e-mail</label>
                  <button class="link-danger" type="submit">Annuler</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <?php if ($past): ?>
      <details class="details"><summary>Historique (<?= count($past) ?>)</summary>
        <ul class="list"><?php foreach ($past as $b): ?><li><div class="list__row"><span class="list__date"><b><?= e(date('d/m', strtotime($b['date']))) ?></b><small><?= e($b['time']) ?></small></span><span class="list__main"><b><?= e($b['name']) ?></b><small><?= e($b['mode']) ?><?= $b['status'] !== 'confirmed' ? ' · annulé' : '' ?></small></span></div></li><?php endforeach; ?></ul>
      </details>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card__head"><h2>Synchroniser avec ton agenda</h2></div>
    <p class="small">Ajoute ce lien privé dans Google Agenda (« Autres agendas › À partir de l’URL »), Apple Calendrier ou Outlook : tes rendez-vous s’y affichent automatiquement.</p>
    <div class="copy"><input readonly value="<?= e($icsUrl) ?>" aria-label="Lien de l’agenda"><button type="button" class="btn btn--ghost" data-copy>Copier</button></div>
    <p class="muted small">Garde ce lien pour toi : il donne accès aux noms et coordonnées de tes rendez-vous.</p>
    <h3 class="h3">Créneaux proposés en ce moment</h3>
    <?php if (!$preview): ?><p class="muted small">Aucun créneau disponible : vérifie les réglages ci-dessous.</p><?php endif; ?>
    <div class="slots-preview">
      <?php foreach ($preview as $d): ?>
        <div><b><?= e($d['label']) ?></b><span><?php foreach ($d['slots'] as $sl): ?><i class="<?= $sl['free'] ? '' : 'is-off' ?>"><?= e($sl['t']) ?></i><?php endforeach; ?></span></div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<section class="card">
  <div class="card__head"><h2>Disponibilités</h2></div>
  <form method="post" action="/admin/rendez-vous/reglages" class="form form--cols">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="check span2"><input type="checkbox" name="enabled" value="1"<?= !empty($cfg['enabled']) ? ' checked' : '' ?>> Réservation en ligne activée (option 1 de la page « On prend RdV ? »)</label>
    <fieldset class="span2"><legend>Jours ouverts</legend>
      <div class="chips"><?php foreach ($wdNames as $n => $label): ?><label class="chip"><input type="checkbox" name="weekdays[]" value="<?= $n ?>"<?= in_array($n, array_map('intval', $cfg['weekdays'] ?? []), true) ? ' checked' : '' ?>><span><?= $label ?></span></label><?php endforeach; ?></div>
    </fieldset>
    <label class="span2">Créneaux (communs à tous les jours)<input name="slots" value="<?= e(implode(', ', $cfg['slots'] ?? [])) ?>" placeholder="09:00, 10:30, 14:00"></label>
    <details class="details span2"><summary>Créneaux différents selon le jour (facultatif)</summary>
      <div class="form form--cols">
        <?php foreach ($wdNames as $n => $label): ?><label><?= $label ?><input name="slots_by_day[<?= $n ?>]" value="<?= e(implode(', ', $byDay[(string) $n] ?? [])) ?>" placeholder="laisser vide = créneaux communs"></label><?php endforeach; ?>
      </div>
    </details>
    <label>Jours affichés (1 à 10)<input type="number" name="days_shown" min="1" max="10" value="<?= (int) ($cfg['days_shown'] ?? 5) ?>"></label>
    <label>Durée d’un rendez-vous (min)<input type="number" name="duration" min="10" max="240" value="<?= (int) ($cfg['duration'] ?? 45) ?>"></label>
    <label>Délai de prévenance (heures)<input type="number" name="min_notice_hours" min="0" max="240" value="<?= (int) ($cfg['min_notice_hours'] ?? 12) ?>"></label>
    <label>Horizon de réservation (jours)<input type="number" name="max_days_ahead" min="5" max="180" value="<?= (int) ($cfg['max_days_ahead'] ?? 30) ?>"></label>
    <label class="span2">Jours fermés (congés, salons…) — une date AAAA-MM-JJ par ligne<textarea name="blocked_dates" rows="3"><?= e(implode("\n", $cfg['blocked_dates'] ?? [])) ?></textarea></label>
    <label>Format 1<input name="modes[]" value="<?= e($cfg['modes'][0] ?? 'En visio') ?>"></label>
    <label>Format 2<input name="modes[]" value="<?= e($cfg['modes'][1] ?? 'En vrai') ?>"></label>
    <label>Adresse des rendez-vous « en vrai »<input name="address" value="<?= e($cfg['address'] ?? '') ?>" placeholder="Envoyée dans la confirmation"></label>
    <label>Lien visio (Meet, Zoom, Teams…)<input name="visio_link" type="url" value="<?= e($cfg['visio_link'] ?? '') ?>" placeholder="https://meet.google.com/…"></label>
    <div class="span2"><button class="btn btn--red" type="submit">Enregistrer les disponibilités</button></div>
  </form>
</section>
