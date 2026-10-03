<?php
/** Album du centenaire. Variables : $cards (album choisi), $auto (proposition automatique si l'album est vide) */
$list = $cards ?: array_map(fn ($c) => ['id' => $c['id'], 'name' => $c['name'], 'image' => null, 'thumb' => $c['image'], 'number' => $c['n'], 'rarity' => $c['tier'], 'matches' => null, 'status' => 'publie'], $auto);
$rar = ['' => 'Automatique', 'legende' => 'Légende (rare)', 'actuel' => 'Actuel', 'classique' => 'Classique'];
?>
<form class="stack" data-json-form data-url="/admin/album" novalidate>
  <div class="toolbar">
    <p class="small muted grow" style="margin:0">Chaque fiche visitée débloque la carte d’un joueur dans l’album du visiteur. Composez l’album dans l’ordre des numéros ; la rareté « Légende » rend une carte précieuse (offerte pour un sans-faute au quiz).</p>
    <a class="btn" href="/interactif/album/" target="_blank" rel="noopener">Voir l’album ↗</a>
    <button type="submit" class="btn btn--navy" data-save>Enregistrer l’album</button><span class="small muted" data-saved></span>
  </div>
  <?php if (!$cards): ?><p class="alert" style="margin:0">L’album n’a pas encore été composé : voici la <b>proposition automatique</b> actuellement en ligne (légendes puis joueurs et entraîneurs les plus présents). Retirez, ajoutez ou réordonnez les cartes, puis enregistrez pour la valider.</p><?php endif; ?>
  <div class="rep rep--compact" data-repeater="cards">
    <?php foreach ($list as $i => $c): ?>
      <div class="rep__item" data-item>
        <div class="rep__handle" title="Glisser pour déplacer"><span data-item-n><?= sprintf('%02d', $i + 1) ?></span></div>
        <div class="rep__body" style="flex-direction:row;align-items:center;gap:12px;flex-wrap:wrap">
          <input type="hidden" data-field="id" data-type="int" value="<?= (int) $c['id'] ?>">
          <?php $src = !empty($c['image']) ? img($c['image'], 160) : ($c['thumb'] ?? null); ?>
          <?php if ($src): ?><img src="<?= e($src) ?>" alt="" style="width:40px;height:52px;object-fit:cover;border:1px solid var(--navy)"><?php endif; ?>
          <a href="/admin/fiche/<?= (int) $c['id'] ?>" target="_blank" rel="noopener" style="min-width:200px;font-weight:600"><?= e($c['name']) ?></a>
          <?php if ($c['matches'] !== null): ?><span class="xs muted"><?= (int) $c['matches'] ?> matchs</span><?php endif; ?>
          <select class="in in--sm" data-field="rarity" aria-label="Rareté" style="width:auto"><?php foreach ($rar as $k => $l): ?><option value="<?= e($k) ?>"<?= ($c['rarity'] ?? '') === $k && $cards ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </div>
        <div class="rep__tools"><button type="button" class="iconbtn" data-rep-up title="Monter" aria-label="Monter">↑</button><button type="button" class="iconbtn" data-rep-down title="Descendre" aria-label="Descendre">↓</button><button type="button" class="iconbtn" data-rep-del title="Retirer" aria-label="Retirer de l’album">✕</button></div>
      </div>
    <?php endforeach; ?>
    <template>
      <div class="rep__item" data-item>
        <div class="rep__handle" title="Glisser pour déplacer"><span data-item-n></span></div>
        <div class="rep__body" style="flex-direction:row;align-items:center;gap:12px">
          <input type="hidden" data-field="id" data-type="int" value="">
          <input type="text" class="in in--sm" data-ac="personnes" data-ac-id="id" placeholder="Nom du joueur…" aria-label="Joueur" style="flex:1">
          <select class="in in--sm" data-field="rarity" aria-label="Rareté" style="width:auto"><?php foreach ($rar as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
        </div>
        <div class="rep__tools"><button type="button" class="iconbtn" data-rep-up title="Monter" aria-label="Monter">↑</button><button type="button" class="iconbtn" data-rep-down title="Descendre" aria-label="Descendre">↓</button><button type="button" class="iconbtn" data-rep-del title="Retirer" aria-label="Retirer">✕</button></div>
      </div>
    </template>
    <button type="button" class="rep__add" data-rep-add>+ Ajouter une carte</button>
  </div>
</form>
