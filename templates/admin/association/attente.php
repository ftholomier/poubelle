<?php
/**
 * Page d'attente du site de l'association : état (fermé : page d'attente montrée), aperçus,
 * ouverture, contenu (texte, photo, liste, compte à rebours, blocs). Distincte de celle du musée.
 * Variables : $schema, $data, $isDefault, $versions, $open, $base, $museumOpen, $email, $teaserFile, $high
 */
use App\Admin\Association;
use App\Admin\Base;
use App\Admin\Form;
use App\Data\Media;
use App\Vitrine\Content;
use App\Vitrine\Host;

$host = (string) parse_url($base, PHP_URL_HOST);
$fields = $schema['fields'];
$f = fn (string $k) => Association::field('data.', $k, $fields[$k], $data);
// Crédit de la photo : rappelle celui de la médiathèque (photo enregistrée), utilisé si le champ reste vide.
$creditField = function () use ($fields, $data): string {
    $spec = $fields['image_credit'];
    $img = (string) ($data['image'] ?? '');
    $lib = $img !== '' ? Content::credit((string) (Media::get($img)['credit'] ?? '')) : '';
    $spec[2]['help'] = 'Affiché sur la photo : « Photo : … ». ' . ($lib !== '' ? 'Vide : celui de la médiathèque, « ' . $lib . ' ».' : 'Vide : aucun crédit affiché (la photo n’en a pas dans la médiathèque).');
    return Association::field('data.', 'image_credit', $spec, $data);
};
[, $item, $itemFields] = $schema['lists']['items'];
$render = function ($it) use ($itemFields) {
    $it = is_array($it) ? $it : [];
    $h = '<div class="fgrid">';
    foreach ($itemFields as $k => $spec) {
        $h .= Association::field('@', $k, $spec, $it);
    }
    return $h . '</div>';
};
$confirm = $open
    ? 'Fermer le site au public ?|Les visiteurs verront cette page d’attente et le site ne sera plus indexé.|Fermer le site|danger'
    : 'Ouvrir le site au public ?|' . ($high ? 'Il reste ' . $high . ' point(s) important(s) à vérifier (tableau de bord). ' : '') . 'La page d’attente disparaît : le site devient visible de tous et indexé par les moteurs de recherche.|Ouvrir le site';
?>
<div class="card card--pad<?= $open ? '' : ' card--navy' ?>">
  <div class="row" style="justify-content:space-between;gap:16px">
    <div class="stack" style="gap:6px;max-width:760px">
      <span class="d" style="font-weight:900;font-size:24px;text-transform:uppercase;<?= $open ? '' : 'color:var(--yellow)' ?>"><?= $open ? '○ Page d’attente inactive : site ouvert' : '● Page d’attente active' ?></span>
      <span class="small"><?= $open
          ? 'Le site de l’association est ouvert au public sur <b>' . e($host) . '</b> : cette page n’est plus montrée. Fermez le site pour la remettre en place (refonte, travaux…).'
          : 'Les visiteurs de <b>' . e($host) . '</b> ne voient que cette page, et rien n’est indexé par les moteurs de recherche ; les pages légales restent accessibles. Le reste du site se prépare dans l’aperçu complet.' ?>
        Le musée a sa propre page d’attente : <a href="/admin/page-attente" style="color:inherit;text-decoration:underline">Éditorial › Page d’attente</a>.</span>
    </div>
    <div class="row" style="gap:8px">
      <a class="btn<?= $open ? '' : ' btn--light' ?>" href="<?= e(Host::PREVIEW) ?>/?apercu-attente=1" target="_blank" rel="noopener">Aperçu de la page d’attente ↗</a>
      <a class="btn<?= $open ? '' : ' btn--light' ?>" href="<?= e(Host::PREVIEW) ?>/" target="_blank" rel="noopener">Aperçu du site ↗</a>
      <form method="post" action="/admin/association/ouverture" data-confirm="<?= e($confirm) ?>">
        <?= csrf_field() ?><input type="hidden" name="open" value="<?= $open ? '0' : '1' ?>"><input type="hidden" name="back" value="attente">
        <button type="submit" class="btn <?= $open ? 'btn--ghost' : 'btn--yellow' ?>"><?= $open ? 'Fermer le site' : 'Ouvrir le site au public' ?></button>
      </form>
    </div>
  </div>
</div>

<form class="stack" data-json-form data-url="/admin/association/contenus/attente" data-lock="ecran:asso-attente" data-lock-what="la page d’attente" novalidate>
  <div class="toolbar">
    <p class="small muted grow" style="margin:0">Enregistrez, puis vérifiez le rendu (ordinateur et téléphone) avec « Aperçu de la page d’attente ».</p>
    <button type="button" class="btn" data-proofread>Vérifier l’orthographe</button>
    <button type="submit" class="btn btn--navy" data-save>Enregistrer</button>
    <span class="small muted" data-saved></span>
  </div>
  <?php if ($isDefault): ?><p class="alert" style="margin:0">Page livrée avec le site (mise à jour avec le code). Elle devient la vôtre dès le premier enregistrement.</p><?php endif; ?>

  <div class="editor">
    <div class="stack">
      <div class="card card--pad">
        <h2 class="card__t">Texte et photo</h2>
        <div class="fgrid">
          <?= $f('eyebrow') ?><?= $f('badge') ?>
          <?= $f('title') ?>
        </div>
        <div style="margin-top:16px"><?= $f('text') ?></div>
        <div class="fgrid" style="margin-top:16px">
          <?= $f('image') ?><?= $f('image_caption') ?><?= $creditField() ?>
        </div>
      </div>
      <div class="card card--pad">
        <h2 class="card__t">Ce qui vous attend</h2>
        <p class="small muted" style="margin:0 0 12px">Ce que le site proposera : quatre éléments au plus pour rester lisible. Liste vide : bloc masqué.</p>
        <div class="fgrid" style="margin-bottom:12px"><?= $f('items_title') ?></div>
        <?= Form::repeater('data.items', '', $data['items'] ?? [], $render, ['add' => 'Ajouter : ' . mb_strtolower($item), 'numbered' => true, 'dup' => true]) ?>
      </div>
    </div>

    <div class="editor__side">
      <div class="card card--pad">
        <h2 class="card__t card__t--sm">Blocs de la page</h2>
        <div class="stack" style="gap:12px">
          <?= $f('newsletter') ?>
          <?= $f('newsletter_title') ?>
          <?= $f('newsletter_text') ?>
          <hr style="border:0;border-top:1px solid rgba(14,31,77,.15);margin:2px 0;width:100%">
          <?= $f('museum') ?>
          <p class="xs muted" style="margin:0">Le musée est <?= $museumOpen ? '<b>ouvert</b> : l’encart propose « Visiter le musée ».' : '<b>fermé</b> : l’encart annonce qu’il ouvre bientôt, sans lien.' ?></p>
          <?= $f('teaser') ?>
          <?php if (!$teaserFile): ?><p class="xs muted" style="margin:0">Aucun teaser installé : la case est sans effet.</p><?php endif; ?>
          <hr style="border:0;border-top:1px solid rgba(14,31,77,.15);margin:2px 0;width:100%">
          <?= $f('contact') ?>
          <p class="xs muted" style="margin:0"><?= $email !== '' ? 'Adresse affichée : ' . e($email) . '.' : 'Aucun e-mail réglé : rien ne s’affiche (<a href="/admin/association/reglages">Réglages du site</a>).' ?></p>
          <?= $f('social') ?>
          <p class="xs muted" style="margin:0">Ceux du musée : <a href="/admin/reglages?groupe=social">Réglages › Réseaux sociaux</a>.</p>
        </div>
      </div>
      <div class="card card--pad">
        <h2 class="card__t card__t--sm">Compte à rebours</h2>
        <div class="stack" style="gap:12px">
          <?= $f('countdown') ?>
          <?= $f('countdown_date') ?>
          <?= $f('countdown_label') ?>
          <p class="xs muted" style="margin:0">Masqué de lui-même une fois la date passée.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row"><button type="submit" class="btn btn--navy" data-save>Enregistrer</button><span class="small muted" data-saved></span></div>
</form>
<?php if (!$isDefault): ?>
  <form method="post" action="/admin/association/contenus/attente/depart" data-confirm="Revenir à la page de départ ?|La page d’attente livrée avec le site remplace la vôtre (votre version reste dans l’historique ci-dessous).|Revenir au départ|danger" style="margin:0">
    <?= csrf_field() ?><button type="submit" class="btn btn--sm btn--ghost">Revenir à la page de départ</button>
  </form>
<?php endif; ?>
<?php if ($versions): ?>
  <div class="card">
    <div class="card__head"><h2 class="card__t card__t--sm">Dernières modifications</h2></div>
    <?php foreach ($versions as $v): ?><div class="card__row" style="grid-template-columns:60px minmax(0,1fr) auto"><span class="vers__n">v<?= (int) $v['n'] ?></span><span><?= e($v['by']) ?> · <?= e($v['message']) ?></span><span class="xs muted"><?= e(Base::ago($v['at'])) ?></span></div><?php endforeach; ?>
  </div>
<?php endif; ?>
