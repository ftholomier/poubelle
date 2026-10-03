<?php
/** Médias : image à la une, galerie, vidéos, publications intégrées. Variables : $doc */
use App\Admin\Form;
use App\Data\Media;

$gal = array_map(function ($g) {
    $m = Media::get($g['image'] ?? null) ?? [];
    return $g + ['credit' => $g['credit'] ?? ($m['credit'] ?? '')];
}, $doc['gallery'] ?? []);
?>
<div class="fpanel" data-panel="medias">
  <div class="card card--pad">
    <?= Form::image('featured_image', 'Image à la une (mosaïques, partage, en-tête)', $doc['featured_image'] ?? null) ?>
  </div>
  <div class="card">
    <div class="card__head"><h2 class="card__t">Galerie</h2><span class="card__note">Légende et crédit sous chaque photo · glissez pour réordonner</span></div>
    <div class="card__body">
      <?= Form::repeater('gallery', '', $gal, fn ($g) => '<div class="grow-row">' . Form::image('@image', 'Photo', $g['image'] ?? null) . '<div class="fgrid">' . Form::text('@caption', 'Légende', $g['caption'] ?? '', ['class' => 'f--2']) . Form::text('@credit', 'Crédit', $g['credit'] ?? '', ['missing' => trim((string) ($g['credit'] ?? '')) === '' && !empty($g['image']), 'placeholder' => 'Photographe – Journal']) . '</div></div>', ['gallery' => true, 'add' => 'Ajouter des photos depuis la médiathèque']) ?>
    </div>
  </div>
  <div class="card">
    <div class="card__head"><h2 class="card__t">Vidéos</h2><span class="card__note">YouTube, Dailymotion ou Vimeo : collez simplement le lien</span></div>
    <div class="card__body">
      <?= Form::repeater('videos', '', $doc['videos'] ?? [], fn ($v) => '<div class="fgrid">' . Form::text('@url', 'Lien de la vidéo', $v ? (video_embed($v)['link'] ?? ($v['url'] ?? '')) : '', ['class' => 'f--2', 'type' => 'url', 'placeholder' => 'https://www.youtube.com/watch?v=…', 'help' => 'YouTube, Dailymotion, Vimeo, Rutube ou fichier vidéo']) . Form::text('@title', 'Titre', $v['title'] ?? '') . '</div>', ['compact' => true, 'add' => 'Ajouter une vidéo']) ?>
    </div>
  </div>
  <div class="card">
    <div class="card__head"><h2 class="card__t">Publications intégrées</h2><span class="card__note">X, Instagram, Facebook (affichées après accord cookies)</span></div>
    <div class="card__body">
      <?= Form::repeater('embeds', '', $doc['embeds'] ?? [], fn ($em) => Form::text('@url', 'Lien de la publication', $em['url'] ?? '', ['type' => 'url', 'class' => 'f--full']) . Form::textarea('@text', 'Texte de la publication (affiché sans cookies)', $em['text'] ?? '', ['rows' => 3]), ['compact' => true, 'add' => 'Ajouter une publication']) ?>
    </div>
  </div>
  <?php if (!empty($doc['images'])): ?>
    <div class="card">
      <div class="card__head"><h2 class="card__t">Images dans le texte</h2></div>
      <div class="card__body">
        <?= Form::repeater('images', '', $doc['images'], fn ($g) => '<div class="grow-row">' . Form::image('@image', 'Image', $g['image'] ?? null) . '<div class="fgrid">' . Form::text('@caption', 'Légende', $g['caption'] ?? '', ['class' => 'f--2']) . Form::toggle('@in_text', 'Dans le texte', !empty($g['in_text'])) . '</div></div>', ['compact' => true, 'add' => 'Ajouter une image']) ?>
      </div>
    </div>
  <?php endif; ?>
</div>
