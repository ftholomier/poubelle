<?php
$stages = (array) settings('pipeline.stages', []);
$isDraft = ($row['status'] ?? '') === 'brouillon';
$fields = [
    'E-mail' => $row['email'] ?? '',
    'Téléphone' => $row['phone'] ?? '',
    'Secteur souhaité' => $row['area'] ?? '',
    'Situation actuelle' => $row['situation'] ?? '',
    'Disponibilité' => $row['availability'] ?? '',
    'Expérience' => $row['experience'] ?? '',
    'Nous a connus par' => $row['source'] ?? '',
];
?>
<div class="topbar">
  <h1><?= e($row['name'] ?: '(nom non renseigné)') ?></h1>
  <div class="row">
    <a class="btn btn--sm btn--ghost" href="<?= e(url('admin/candidatures')) ?><?= $isDraft ? '?drafts=1' : '' ?>">← Retour</a>
    <?php if (!empty($row['email'])): ?>
      <button class="btn btn--sm" type="button"
              data-mailer="application"
              data-mailer-id="<?= e($row['id']) ?>"
              data-mailer-email="<?= e($row['email']) ?>"
              data-mailer-name="<?= e($row['name'] ?: 'ce candidat') ?>"
              data-mailer-area="<?= e($row['area'] ?: 'votre secteur') ?>"
              data-mailer-template="<?= ($row['status'] ?? '') === 'brouillon' ? 'relance' : 'rdv' ?>">
        <?= icon('mail') ?> Écrire
      </button>
    <?php endif; ?>
    <?php if (!empty($row['phone'])): ?>
      <a class="btn btn--sm btn--ghost" href="tel:<?= e(preg_replace('/\D/', '', (string) $row['phone'])) ?>"><?= icon('phone') ?> Appeler</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($isDraft): ?>
  <div class="flash flash--error">
    Tunnel abandonné à l’étape <?= (int) ($row['max_step'] ?? 1) ?>/4 — le candidat n’a jamais validé l’envoi.
    Ces coordonnées restent exploitables pour une relance.
  </div>
<?php endif; ?>

<div class="grid grid--2" style="align-items:start">
  <div>
    <div class="panel">
      <div class="panel__head"><h2>Informations</h2>
        <span class="badge"><?= e(fr_date($row['submitted_at'] ?? $row['created_at'] ?? '', true)) ?></span>
      </div>
      <table class="data" style="min-width:0">
        <tbody>
          <?php foreach ($fields as $label => $val): ?>
            <tr>
              <th style="width:40%;color:var(--muted);font-weight:500;font-size:.85rem"><?= e($label) ?></th>
              <td><?= $val !== '' ? e($val) : '<span style="color:var(--muted)">—</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!empty($row['cv'])): ?>
            <tr>
              <th style="color:var(--muted);font-weight:500;font-size:.85rem">CV</th>
              <td><a class="btn btn--sm btn--ghost" href="<?= e(url('admin/candidatures/' . $row['id'] . '/cv')) ?>"><?= icon('download') ?> <?= e($row['cv_name'] ?: 'Télécharger') ?></a></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if (!empty($row['goal'])): ?>
        <div class="note" style="margin-top:16px">
          <div class="note__meta">Objectif déclaré</div>
          <?= nl2br(e($row['goal'])) ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($row['message'])): ?>
        <div class="note">
          <div class="note__meta">Message pour l’équipe</div>
          <?= nl2br(e($row['message'])) ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($row['simulation']) && is_array($row['simulation'])): ?>
        <div class="note">
          <div class="note__meta">Simulation réalisée sur le site</div>
          <?= e(nb($row['simulation']['sales'] ?? 0)) ?> ventes/an à <?= e(euro($row['simulation']['price'] ?? 0)) ?>
          → <strong><?= e(euro($row['simulation']['net'] ?? 0)) ?></strong> estimés
          <?php if (!empty($row['simulation']['tier'])): ?> (palier <?= e($row['simulation']['tier']) ?>)<?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel__head"><h2>Suivi interne</h2></div>
      <?php foreach (array_reverse((array) ($row['notes'] ?? [])) as $n): ?>
        <div class="note">
          <div class="note__meta"><?= e($n['author'] ?? '') ?> — <?= e(fr_date($n['at'] ?? '', true)) ?></div>
          <?= nl2br(e($n['text'] ?? '')) ?>
        </div>
      <?php endforeach; ?>
      <?php if (empty($row['notes'])): ?>
        <p style="color:var(--muted);font-size:.88rem">Aucune note pour l’instant.</p>
      <?php endif; ?>

      <form method="post" style="margin-top:16px">
        <?= Csrf::field() ?>
        <div class="field">
          <label for="note">Ajouter une note</label>
          <textarea class="textarea" id="note" name="note" placeholder="Compte-rendu d’appel, points à creuser, secteur validé…"></textarea>
        </div>
        <button class="btn" type="submit">Enregistrer la note</button>
      </form>
    </div>
  </div>

  <div>
    <div class="panel">
      <div class="panel__head"><h2>Pipeline</h2></div>
      <form method="post">
        <?= Csrf::field() ?>
        <div class="field">
          <label for="stage">Étape du recrutement</label>
          <select class="select" id="stage" name="stage" onchange="this.form.submit()">
            <?php foreach ($stages as $s): ?>
              <option value="<?= e($s['key']) ?>" <?= ($row['stage'] ?? 'nouveau') === $s['key'] ? 'selected' : '' ?>><?= e($s['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn--ghost btn--sm" type="submit">Mettre à jour</button>
      </form>
    </div>

    <div class="panel">
      <div class="panel__head"><h2>Traçabilité</h2></div>
      <table class="data" style="min-width:0;font-size:.85rem">
        <tbody>
          <tr><th style="color:var(--muted);font-weight:500">Référence</th><td style="font-family:ui-monospace,monospace"><?= e($row['id'] ?? '') ?></td></tr>
          <tr><th style="color:var(--muted);font-weight:500">Première visite</th><td><?= e(fr_date($row['created_at'] ?? '', true)) ?></td></tr>
          <tr><th style="color:var(--muted);font-weight:500">Dernière activité</th><td><?= e(fr_date($row['updated_at'] ?? $row['submitted_at'] ?? $row['created_at'] ?? '', true)) ?></td></tr>
          <tr><th style="color:var(--muted);font-weight:500">Étape atteinte</th><td><?= (int) ($row['max_step'] ?? 4) ?> / 4</td></tr>
        </tbody>
      </table>
    </div>

    <div class="panel">
      <div class="panel__head"><h2>Zone sensible</h2></div>
      <p style="font-size:.86rem;color:var(--muted);margin-bottom:14px">
        La suppression efface définitivement la candidature, ses notes et le CV associé.
      </p>
      <form method="post" action="<?= e(url('admin/candidatures/' . $row['id'] . '/supprimer')) ?>"
            onsubmit="return confirm('Supprimer définitivement cette candidature ?')">
        <?= Csrf::field() ?>
        <button class="btn btn--danger btn--sm" type="submit">Supprimer la candidature</button>
      </form>
    </div>
  </div>
</div>

<?php partial('admin-mailer'); ?>
