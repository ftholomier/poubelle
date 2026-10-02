<?php
use App\Services\Leads;
use App\Services\Pros;

/** @var array $pro @var array $m */
$subject = 'Re : votre message sur ' . App\Services\Settings::siteName();
?>
<p><a class="link small" href="/espace-pro/messages/">← Tous les messages</a></p>
<div class="pro-head">
  <div><p class="mono muted small">Message reçu le <?= e(date_fr((string) ($m['created_at'] ?? ''), 'datetime')) ?></p>
    <h1 class="h2"><?= e($m['name']) ?></h1></div>
</div>
<div class="pro-cols mt-2">
  <div class="box">
    <div class="prose"><?= nl2br(e($m['message'])) ?></div>
    <dl class="dl mt-2">
      <?php foreach (['Événement' => Leads::EVENT_TYPES[$m['event_type'] ?? ''] ?? '', 'Date' => !empty($m['event_date']) ? date_fr((string) $m['event_date'], 'long') : '', 'Lieu' => $m['place'] ?? '', 'Invités' => $m['guests'] ?? ''] as $label => $value): if ($value === '') { continue; } ?>
        <dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
      <?php endforeach; ?>
    </dl>
  </div>
  <aside class="box box-ink">
    <h2 style="color:var(--cream)">Répondre</h2>
    <div class="stack">
      <?php if (!empty($m['phone'])): ?><a class="btn btn-yellow btn-block" href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $m['phone'])) ?>"><?= icon('phone', 18) ?> <?= e($m['phone']) ?></a><?php endif; ?>
      <?php if (!empty($m['email'])): ?><a class="btn btn-block" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode($subject) ?>&amp;body=<?= rawurlencode('Bonjour ' . $m['name'] . ",\n\n\n\n" . Pros::displayName($pro)) ?>"><?= icon('mail', 18) ?> Répondre par email</a>
        <button type="button" class="link small" style="color:var(--cream)" data-copy="<?= e($m['email']) ?>">Copier l'adresse email</button><?php endif; ?>
    </div>
  </aside>
</div>
