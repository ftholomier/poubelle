<?php
use App\Services\Leads;
use App\Services\Pros;

/** @var array $pro @var array $r @var array $details @var string $state @var array $states */
$c = $r['client'];
$ev = $r['event'];
$subject = 'Votre demande de devis : ' . (Leads::EVENT_TYPES[$ev['type'] ?? 'autre'] ?? 'événement') . (!empty($ev['date']) ? ' du ' . date_fr((string) $ev['date'], 'short') : '');
$body = "Bonjour " . ($c['first_name'] ?? '') . ",\n\nJ'ai bien reçu votre demande via " . App\Services\Settings::siteName() . " et je suis disponible pour votre événement.\n\n\n" . Pros::displayName($pro) . "\n" . ($pro['phone'] ?? '');
?>
<p><a class="link small" href="/espace-pro/demandes/">← Toutes les demandes</a></p>
<div class="pro-head">
  <div><p class="mono muted small">Demande n° <?= (int) $r['id'] ?> · reçue <?= e(ago((string) ($r['created_at'] ?? ''))) ?></p>
    <h1 class="h2"><?= e(Leads::EVENT_TYPES[$ev['type'] ?? 'autre'] ?? 'Événement') ?><?= !empty($ev['city']) ? ' <span class="serif c-coral">' . e(App\Services\Geo::inCity((string) $ev['city'])) . '</span>' : '' ?></h1></div>
</div>
<div class="pro-cols mt-2">
  <div class="box">
    <h2>Détails de la demande</h2>
    <dl class="dl">
      <?php foreach ($details as $label => $value): if ($value === '' || $value === null) { continue; } ?>
        <dt><?= e($label) ?></dt><dd><?= nl2br(e($value)) ?></dd>
      <?php endforeach; ?>
    </dl>
  </div>
  <aside class="stack">
    <div class="box box-ink">
      <h2 style="color:var(--cream)">Contacter le client</h2>
      <div class="stack">
        <?php if (!empty($c['phone'])): ?><a class="btn btn-yellow btn-block" href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $c['phone'])) ?>"><?= icon('phone', 18) ?> <?= e($c['phone']) ?></a><?php endif; ?>
        <?php if (!empty($c['email'])): ?><a class="btn btn-block" href="mailto:<?= e($c['email']) ?>?subject=<?= rawurlencode($subject) ?>&amp;body=<?= rawurlencode($body) ?>"><?= icon('mail', 18) ?> Répondre par email</a>
          <button type="button" class="link small" style="color:var(--cream)" data-copy="<?= e($c['email']) ?>">Copier l'adresse email</button><?php endif; ?>
      </div>
    </div>
    <form class="box" method="post" action="/espace-pro/demandes/<?= (int) $r['id'] ?>/">
      <?= csrf_field() ?>
      <h2>Suivi</h2>
      <p class="small muted">Pour vous organiser (visible uniquement par vous et l'équipe du site).</p>
      <div class="stack">
        <?php foreach ($states as $k => $label): ?>
          <label class="check"><input type="radio" name="state" value="<?= e($k) ?>"<?= $state === $k ? ' checked' : '' ?>> <span><?= e($label) ?></span></label>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-sm btn-ink mt-2" type="submit">Enregistrer</button>
    </form>
    <p class="small muted">Demande abusive ou suspecte ? <a href="/contact/?sujet=signalement&amp;demande=<?= (int) $r['id'] ?>">Signalez-la</a>.</p>
  </aside>
</div>
