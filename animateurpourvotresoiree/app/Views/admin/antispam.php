<?php
use App\Core\Url;
/** @var array $a @var array $log @var bool $turnstileKeys */
$sw = static fn (string $n, string $l) => '<label class="switch"><input type="checkbox" name="' . e($n) . '" value="1"' . (!empty($a[$n]) ? ' checked' : '') . '> ' . e($l) . '</label>';
$forms = ['devis' => 'Demande de devis', 'contact' => 'Message à un pro', 'review' => 'Avis', 'register' => 'Inscription pro', 'chat' => 'Assistant IA', 'site_contact' => 'Contact du site', 'reveal' => 'Affichage des téléphones'];
?>
<div class="adm-head"><div><h1>Anti-spam</h1><p>Protection multicouche sans captcha visible : champ piège, délai minimal, preuve de travail calculée par le navigateur, limites de débit, listes noires, analyse du contenu et des emails, IA facultative.</p></div></div>
<form class="form" method="post" action="<?= e(Url::admin('antispam')) ?>" data-dirty-check>
  <?= csrf_field() ?>
  <div class="box">
    <h2>Protections</h2>
    <div class="stack">
      <?= $sw('honeypot', 'Champ piège invisible (robots)') ?>
      <?= $sw('pow', 'Preuve de travail (calcul invisible dans le navigateur)') ?>
      <?= $sw('check_mx', 'Vérifier que le domaine de l\'email peut recevoir des messages (MX)') ?>
      <?= $sw('block_disposable', 'Refuser les adresses jetables (yopmail…)') ?>
      <?= $sw('ai_scoring', 'Analyse par l\'IA des cas douteux (si l\'IA de modération est active)') ?>
      <?= $sw('turnstile', 'Cloudflare Turnstile (captcha invisible)') ?><?= !$turnstileKeys ? '<span class="small muted">— renseignez d\'abord les clés Turnstile dans la configuration.</span>' : '' ?>
    </div>
    <div class="form-grid mt-1">
      <div class="field"><label for="as-s">Délai minimal de remplissage (s)</label><input id="as-s" type="number" name="min_seconds" min="0" max="60" value="<?= (int) ($a['min_seconds'] ?? 4) ?>"></div>
      <div class="field"><label for="as-d">Difficulté de la preuve de travail</label><input id="as-d" type="number" name="pow_difficulty" min="8" max="22" value="<?= (int) ($a['pow_difficulty'] ?? 15) ?>"><span class="hint">15 ≈ 0,1 s sur un téléphone. Chaque +1 double le calcul.</span></div>
      <div class="field"><label for="as-l">Liens maximum dans un message</label><input id="as-l" type="number" name="max_links" min="0" max="20" value="<?= (int) ($a['max_links'] ?? 2) ?>"></div>
    </div>
  </div>
  <div class="box">
    <h2>Limites d'envoi par adresse IP</h2>
    <div class="table-wrap" style="box-shadow:none"><table class="tbl"><thead><tr><th>Formulaire</th><th>Envois maximum</th><th>Par période (secondes)</th></tr></thead><tbody>
      <?php foreach ((array) ($a['rates'] ?? []) as $form => [$max, $window]): ?><tr><td><?= e($forms[$form] ?? $form) ?></td><td><input class="input" style="min-height:36px;max-width:120px" type="number" name="rates[<?= e($form) ?>][0]" value="<?= (int) $max ?>"></td><td><input class="input" style="min-height:36px;max-width:140px" type="number" name="rates[<?= e($form) ?>][1]" value="<?= (int) $window ?>"></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="box">
    <h2>Listes noires</h2>
    <div class="form-grid">
      <?php foreach (['ips' => 'Adresses IP / plages (CIDR)', 'emails' => 'Emails', 'domains' => 'Domaines d\'email', 'words' => 'Mots ou expressions interdits'] as $k => $l): ?>
        <div class="field"><label for="bl-<?= $k ?>"><?= e($l) ?></label><textarea id="bl-<?= $k ?>" name="block_<?= $k ?>" rows="6" class="code" placeholder="une valeur par ligne"><?= e(implode("\n", (array) ($a['block'][$k] ?? []))) ?></textarea></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="form-actions"><button class="btn btn-coral" type="submit">Enregistrer</button></div>
</form>
<div class="box mt-3">
  <h2>Derniers envois filtrés</h2>
  <?php if (!$log): ?><p class="muted small">Aucun envoi suspect enregistré.</p><?php else: ?>
  <div class="table-wrap" style="box-shadow:none"><table class="tbl"><thead><tr><th>Date</th><th>Formulaire</th><th>Décision</th><th>Raisons</th></tr></thead><tbody>
    <?php foreach ($log as $row): $ctx = (array) ($row['ctx'] ?? []); ?><tr><td class="small"><?= e(date_fr((string) ($row['t'] ?? ''), 'datetime')) ?></td><td class="small"><?= e($forms[$ctx['form'] ?? ''] ?? (string) ($ctx['form'] ?? '')) ?></td><td><span class="score <?= ($ctx['score'] ?? 0) >= 70 ? 'hi' : (($ctx['score'] ?? 0) >= 30 ? 'mid' : 'lo') ?>"><?= e((string) ($ctx['decision'] ?? $row['msg'] ?? '')) ?> · <?= (int) ($ctx['score'] ?? 0) ?></span></td><td class="small"><span class="t-ex"><?= e(implode(' ; ', (array) ($ctx['reasons'] ?? []))) ?></span></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
