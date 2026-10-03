<?php
/** Masque « article / page ». Variables : $doc */
use App\Admin\FicheForm;
use App\Admin\Form;

$a = $doc['article'] ?? [];
?>
<div class="fpanel" data-panel="contenu">
  <div class="card card--pad">
    <?= Form::text('title', 'Titre', $doc['title'] ?? '', ['required' => true, 'class' => 'f--full', 'maxlength' => 250, 'proof' => 'title']) ?>
    <div class="fgrid">
      <?= Form::select('article.kind', 'Type d’article', $a['kind'] ?? 'article', FicheForm::KINDS) ?>
      <?= Form::text('article.season', 'Saison (bilan)', $a['season'] ?? '', ['placeholder' => '1987-1988', 'pattern' => '\d{4}-\d{4}']) ?>
    </div>
    <?= Form::text('article.heading', 'Chapeau / surtitre', $a['heading'] ?? '', ['class' => 'f--full', 'proof' => true]) ?>
    <?= Form::text('article.subtitle', 'Sous-titre', $a['subtitle'] ?? '', ['class' => 'f--full', 'proof' => true]) ?>
  </div>
</div>
