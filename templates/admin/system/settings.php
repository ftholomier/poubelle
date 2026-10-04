<?php
/**
 * Réglages d'un groupe (et page d'attente). Variables : $group, $schema, $values, $options, $status
 */
use App\Admin\Form;
use App\Core\Settings;

$isWaiting = $group === 'waiting';
?>
<?php if ($isWaiting): ?>
  <div class="card card--pad <?= $status['enabled'] ? 'card--navy' : '' ?>">
    <div class="row" style="justify-content:space-between">
      <div class="stack" style="gap:4px">
        <span class="d" style="font-weight:900;font-size:24px;text-transform:uppercase;<?= $status['enabled'] ? 'color:var(--yellow)' : '' ?>"><?= $status['enabled'] ? '● Page d’attente active' : '○ Site ouvert à tous' ?></span>
        <span class="small"><?= $status['enabled'] ? 'Les visiteurs voient uniquement la page d’attente et rien n’est indexé par les moteurs de recherche. Vous, connecté au back-office, voyez le site normalement (un bandeau jaune le rappelle). Les pages légales restent accessibles.' : 'Activez la page d’attente pour fermer temporairement le site public (travaux, lancement…).' ?></span>
      </div>
      <div class="row" style="gap:8px">
        <a class="btn<?= $status['enabled'] ? ' btn--light' : '' ?>" href="/?apercu-attente=1" target="_blank" rel="noopener">Aperçu de la page ↗</a>
        <?php if ($status['teaser']): ?><a class="btn<?= $status['enabled'] ? ' btn--light' : '' ?>" href="/?apercu-attente=1&amp;teaser=1" target="_blank" rel="noopener">Aperçu avec le teaser ↗</a><?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php if ($group === 'ai'): ?>
  <p class="alert <?= $status['ready'] ? 'alert--ok' : '' ?>" style="margin:0"><?= $status['ready'] ? 'Clé Gemini enregistrée.' . (!empty($options['_error']) ? ' <b class="ko">La liste des modèles n’a pas pu être chargée : ' . e($options['_error']) . '</b>' : ' ' . count($options['gemini_generate_models']) . ' modèle(s) disponible(s) pour cette clé.') : 'Saisissez la clé API Gemini (Google AI Studio) puis enregistrez : la liste des modèles disponibles se charge automatiquement.' ?></p>
<?php elseif ($group === 'audio'): ?>
  <p class="alert alert--info" style="margin:0">Suivi des voix IA, estimation du coût et traitement groupé de tout le musée : <a href="/admin/audio">Système › Fiches audio</a>. Fiche par fiche : carte « Écouter » de l’éditeur.</p>
<?php elseif ($group === 'couts'): ?>
  <p class="alert alert--info" style="margin:0">Dépense en temps réel, relevés mensuels à faire rembourser et barème des modèles : <a href="/admin/couts-ia">Système › Coûts IA</a>.</p>
<?php elseif ($group === 'mail'): ?>
  <p class="alert <?= $status['from'] ? 'alert--ok' : 'alert--error' ?>" style="margin:0"><?= $status['from'] ? 'Les e-mails partent de ' . e($status['from']) . '.' : 'Aucune adresse d’expédition : aucun e-mail ne peut partir (contact, contributions, dons, invitations).' ?></p>
<?php elseif ($group === 'donations'): ?>
  <p class="alert <?= $status['methods'] ? 'alert--ok' : '' ?>" style="margin:0">Mode <b><?= $status['test'] ? 'test' : 'production' ?></b> · moyens de paiement actifs : <?= e(implode(', ', $status['methods']) ?: 'aucun (activez les dons et saisissez les clés)') ?>. Adresses des webhooks : <code><?= e(base_url()) ?>/api/dons/stripe/webhook</code> et <code><?= e(base_url()) ?>/api/dons/paypal/webhook</code>.</p>
<?php endif; ?>

<form class="card card--pad" data-json-form data-url="/admin/reglages" autocomplete="off" novalidate>
  <input type="hidden" name="_group" value="<?= e($group) ?>">
  <div class="fgrid fgrid--2">
  <?php foreach ($schema['fields'] as $k => $f):
      $type = $f['type'] ?? 'text';
      $o = ['help' => isset($f['help']) ? e($f['help']) : null, 'show_if' => $f['show_if'] ?? null];
      $v = $values[$k] ?? null;
      switch ($type) {
          case 'bool':
              echo Form::toggle($k, $f['label'], (bool) $v, $o + ['class' => 'f--full']);
              break;
          case 'wysiwyg':
              echo Form::html($k, $f['label'], (string) $v, $o + ['mini' => !in_array($k, ['text', 'intro_text', 'system_prompt', 'thanks_email', 'extra', 'intro'], true)]);
              break;
          case 'number':
              echo Form::number($k, $f['label'], $v, $o + ['decimal' => isset($f['step']) && $f['step'] < 1, 'min' => $f['min'] ?? null, 'max' => $f['max'] ?? null]);
              break;
          case 'select':
              $opts = $f['options'] ?? ($options[$f['options_from'] ?? ''] ?? []);
              if (!empty($f['options_from'])) {
                  echo '<div class="f" data-models="' . e(['gemini_embedding_models' => 'embed', 'gemini_tts_models' => 'tts'][$f['options_from']] ?? 'generate') . '">';
                  echo Form::select($k, $f['label'], (string) $v, $opts, ['empty' => $f['empty'] ?? ($f['options_from'] === 'gemini_embedding_models' ? 'Aucun (recherche plein texte)' : 'Automatique (meilleur modèle disponible)'), 'help' => $o['help']]);
                  echo '</div>';
              } else {
                  echo Form::select($k, $f['label'], (string) $v, $opts, $o + ['strict' => true]);
              }
              break;
          case 'secret':
              echo '<div class="f"><span class="f__k"><label for="s-' . e($k) . '">' . e($f['label']) . '</label>' . ($v ? ' <span class="pill pill--ok">enregistrée</span>' : '') . '</span>'
                  . '<input id="s-' . e($k) . '" type="password" name="' . e($k) . '" value="" autocomplete="new-password" placeholder="' . ($v ? '•••••••• (laisser vide pour ne pas changer)' : 'Non renseignée') . '">'
                  . (!empty($f['help']) ? '<span class="f__help">' . e($f['help']) . '</span>' : '')
                  . ($v ? '<label class="toggle small"><input type="checkbox" name="_delete.' . e($k) . '"><span class="toggle__box"></span><span>Effacer la valeur enregistrée</span></label>' : '')
                  . '<span class="f__help">Chiffrée sur le serveur, jamais réaffichée ni envoyée au navigateur.</span></div>';
              break;
          case 'image':
              echo Form::image($k, $f['label'], (string) $v ?: null, $o);
              break;
          case 'date':
              echo Form::text($k, $f['label'], (string) $v, $o + ['type' => 'date']);
              break;
          case 'datetime':
              echo Form::text($k, $f['label'], (string) $v, $o + ['type' => 'datetime-local']);
              break;
          case 'email':
          case 'url':
              echo Form::text($k, $f['label'], (string) $v, $o + ['type' => $type, 'placeholder' => $type === 'url' ? 'https://' : '']);
              break;
          default:
              echo Form::text($k, $f['label'], is_scalar($v) ? (string) $v : '', $o);
      }
  endforeach; ?>
  </div>
  <div class="row">
    <button type="submit" class="btn btn--navy" data-save>Enregistrer</button>
    <?php if ($group === 'ai' && Settings::hasValue('ai.gemini_api_key')): ?><button type="button" class="btn" data-models-refresh>Recharger la liste des modèles</button><?php endif; ?>
    <span class="small muted" data-saved></span>
  </div>
</form>

