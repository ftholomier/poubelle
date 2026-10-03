<?php
/** Version anglaise. Variables : $doc, $enStatus */
use App\Admin\Form;
use App\Services\Translator;

$en = $doc['i18n']['en'] ?? [];
$secs = $doc['sections'] ?? [];
?>
<div class="fpanel" data-panel="en">
  <div class="card card--pad">
    <div class="row" style="justify-content:space-between">
      <h2 class="card__t">Version anglaise</h2>
      <?php if (Translator::enabled()): ?>
        <button type="submit" class="btn btn--navy btn--sm" form="translate-form"><?= $en ? 'Retraduire avec Gemini' : 'Traduire avec Gemini' ?></button>
      <?php else: ?>
        <span class="xs muted">Traduction automatique : réglez la clé Gemini (Réglages › Assistant IA).</span>
      <?php endif; ?>
    </div>
    <p class="small muted" style="margin:0"><?= match ($enStatus) {
        'none' => 'Pas encore traduite. La traduction automatique se fait en tâche de fond ; vous pouvez aussi saisir ou lancer la traduction ici.',
        'auto' => 'Traduite automatiquement par Gemini' . (!empty($en['_at']) ? ' le ' . e(date_num(substr($en['_at'], 0, 10))) : '') . '. Toute correction enregistrée ici la rend « relue » : elle ne sera plus écrasée.',
        'manual' => 'Relue et corrigée' . (!empty($en['_by']) ? ' par ' . e($en['_by']) : '') . '. Elle n’est plus modifiée automatiquement.',
        'stale' => '<b>Le texte français a changé depuis cette traduction</b> : relisez-la ou relancez la traduction.',
        default => '',
    } ?></p>
    <?= Form::text('i18n_en.title', 'Title', $en['title'] ?? '', ['class' => 'f--full', 'placeholder' => $doc['title'] ?? '']) ?>
    <?php if ($doc['type'] === 'personne'): ?><?= Form::text('i18n_en.subtitle', 'Subtitle', $en['personne']['subtitle'] ?? '', ['class' => 'f--full', 'placeholder' => $doc['personne']['subtitle'] ?? '']) ?><?php endif; ?>
    <?= Form::html('i18n_en.intro', 'Introduction', $en['intro'] ?? '') ?>
  </div>
  <?php foreach ($secs as $i => $s): ?>
    <div class="card card--pad">
      <span class="f__k">Bloc <?= $i + 1 ?> · <?= e($s['title'] ?? '') ?></span>
      <?= Form::text("i18n_en.sections.$i.title", 'Heading', $en['sections'][$i]['title'] ?? '', ['class' => 'f--full', 'placeholder' => $s['title'] ?? '']) ?>
      <?= Form::html("i18n_en.sections.$i.html", 'Text', $en['sections'][$i]['html'] ?? '') ?>
      <details><summary class="xs muted" style="cursor:pointer">Voir le texte français</summary><div class="prose small" style="padding:8px 0"><?= safe_html($s['html'] ?? '') ?></div></details>
    </div>
  <?php endforeach; ?>
  <div class="card card--pad">
    <?= Form::text('i18n_en.key_figure_text', 'Key figure caption', $en['key_figure']['text'] ?? '', ['class' => 'f--full', 'placeholder' => $doc['key_figure']['text'] ?? '']) ?>
    <?= Form::text('i18n_en.seo.title', 'SEO title', $en['seo']['title'] ?? '', ['class' => 'f--full', 'count' => 60]) ?>
    <?= Form::text('i18n_en.seo.description', 'SEO description', $en['seo']['description'] ?? '', ['class' => 'f--full', 'maxlength' => 320, 'count' => 160]) ?>
  </div>
</div>

