<?php
/** Détail d'une contribution et décisions. Variables : $c, $cols */
use App\Admin\Base;
use App\Admin\Community;
use App\Core\Auth;
use App\Data\Index;
use App\Front\Community as Front;

$st = $c['status'] ?? 'nouveau';
$t = Front::TYPES[$c['type']] ?? ['•', $c['type'], ''];
$files = $c['files'] ?? [];
$hasImages = (bool) array_filter($files, fn ($f) => str_starts_with((string) ($f['mime'] ?? ''), 'image/') || ($f['mime'] ?? '') === 'application/pdf');
$credit = $c['credit'] ?: $c['name'];
$linked = !empty($c['fiche_id']) ? Index::get((int) $c['fiche_id']) : null;
?>
<div class="cols cols--wide">
  <div class="stack">
    <div class="card">
      <div class="card__head">
        <h2 class="card__t"><?= e($t[0] . ' ' . $t[1]) ?></h2>
        <span class="pill pill--<?= ['nouveau' => 'warn', 'info' => 'info', 'valide' => 'ok', 'refuse' => 'ko'][$st] ?? 'warn' ?>"><?= e(Community::C_STATUS[$st] ?? $st) ?></span>
      </div>
      <div class="card__body">
        <div class="fgrid">
          <div class="f"><span class="f__k">Contributeur</span><span><?= e($c['name'] ?? '') ?><br><a href="mailto:<?= e($c['email'] ?? '') ?>"><?= e($c['email'] ?? '') ?></a></span></div>
          <div class="f"><span class="f__k">Reçue</span><span><?= e(date('d/m/Y à H:i', strtotime((string) $c['at']))) ?><br><span class="xs muted">langue : <?= e(strtoupper($c['lang'] ?? 'fr')) ?></span></span></div>
          <div class="f"><span class="f__k">Crédit souhaité</span><span><?= e($c['credit'] ?: '—') ?></span></div>
          <?php if (!empty($c['date']) || !empty($c['place'])): ?><div class="f"><span class="f__k">Date · lieu</span><span><?= e(trim(($c['date'] ?? '') . ' · ' . ($c['place'] ?? ''), ' ·')) ?></span></div><?php endif; ?>
        </div>
        <?php if (!empty($c['fiche'])): ?><div class="f"><span class="f__k">Fiche concernée (saisie du contributeur)</span><span><?= preg_match('#^(https?://|/)#', $c['fiche']) ? '<a href="' . e($c['fiche']) . '" target="_blank" rel="noopener">' . e($c['fiche']) . ' ↗</a>' : e($c['fiche']) ?></span></div><?php endif; ?>
        <div class="f"><span class="f__k">Description</span><div style="white-space:pre-wrap;background:var(--cream);border:2px solid var(--navy);padding:12px;font-size:16px"><?= e($c['description'] ?? '') ?: '<span class="muted">(aucune description)</span>' ?></div></div>
        <?php if ($linked): ?><p class="alert alert--ok" style="margin:0">Reliée à la fiche <a href="/admin/fiche/<?= (int) $linked['id'] ?>"><b><?= e($linked['title']) ?></b></a>.</p><?php endif; ?>
        <?php if (!empty($c['handled'])): ?><p class="small muted" style="margin:0">Traitée par <?= e($c['handled']['by']) ?> <?= e(Base::ago($c['handled']['at'])) ?><?= !empty($c['handled']['note']) ? ' · motif : ' . e(plain_text($c['handled']['note'])) : '' ?></p><?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2 class="card__t">Fichiers</h2><span class="card__note"><?= count($files) ?> fichier<?= count($files) > 1 ? 's' : '' ?><?= !empty($c['media']) ? ' · importés dans la médiathèque ✓' : '' ?></span></div>
      <div class="card__body">
        <?php if ($files): ?>
          <div class="thumbs">
            <?php foreach ($files as $i => $f): ?>
              <?php $url = '/admin/contributions/' . e($c['ticket']) . '/fichier/' . $i; $isImg = preg_match('#^image/(jpeg|png|gif|webp)$#', (string) ($f['mime'] ?? '')); ?>
              <a class="mcardx" href="<?= $url ?>" target="_blank" rel="noopener">
                <span class="mcardx__img"><?php if ($isImg): ?><img src="<?= $url ?>" alt="" loading="lazy"><?php else: ?><img src="/assets/admin/pdf.svg" alt="" style="object-fit:contain;padding:18px"><?php endif; ?></span>
                <span class="mcardx__body"><b style="overflow-wrap:anywhere"><?= e($f['name'] ?? $f['file']) ?></b><span class="muted"><?= e(Base::size((int) ($f['size'] ?? 0))) ?> · <?= e((string) ($f['mime'] ?? '')) ?></span></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?><p class="muted" style="margin:0">Aucun fichier joint.</p><?php endif; ?>
      </div>
    </div>

    <?php if (!empty($c['thread'])): ?>
      <div class="card">
        <div class="card__head"><h2 class="card__t">Échanges</h2></div>
        <?php foreach ($c['thread'] as $m): ?>
          <div class="card__row" style="grid-template-columns:34px minmax(0,1fr)"><span class="avatar avatar--sm"><?= e(Base::initials($m['by'])) ?></span><span><b><?= e($m['by']) ?></b> <span class="xs muted"><?= e(Base::ago($m['at'])) ?></span><br><span><?= rich_inline($m['text']) ?></span></span></div>
        <?php endforeach; ?>
        <div class="card__foot small muted">Les réponses du contributeur arrivent dans la boîte e-mail de contact.</div>
      </div>
    <?php endif; ?>
  </div>

  <div class="stack">
    <?php if ($hasImages): ?>
      <form class="card card--pad" method="post" action="/admin/contributions/<?= e($c['ticket']) ?>">
        <?= csrf_field() ?>
        <h2 class="card__t card__t--sm">Publier les fichiers</h2>
        <label class="f"><span class="f__k">Crédit photo</span><input type="text" name="credit" value="<?= e($credit) ?>" maxlength="200"></label>
        <div class="seg" style="--n:3" role="radiogroup">
          <label><input type="radio" name="action" value="mediatheque" checked><span>Médiathèque</span></label>
          <label><input type="radio" name="action" value="fiche"><span>Galerie d’une fiche</span></label>
          <label><input type="radio" name="action" value="objet"><span>Nouvel objet</span></label>
        </div>
        <div class="f" data-show-if="action" data-show-value="fiche">
          <span class="f__k">Fiche à compléter</span>
          <input type="text" data-ac="fiches" data-ac-id="fiche_id" placeholder="Tapez un match, un joueur, un article…" value="<?= $linked ? e($linked['title']) : '' ?>">
          <input type="hidden" name="fiche_id" value="<?= $linked ? (int) $linked['id'] : '' ?>">
        </div>
        <div class="stack" style="gap:10px" data-show-if="action" data-show-value="objet">
          <label class="f"><span class="f__k">Titre de l’objet</span><input type="text" name="title" maxlength="250" placeholder="Ex. : Programme Sochaux – Metz, finale 1988"></label>
          <label class="f"><span class="f__k">Collection des réserves</span><select name="collection"><?php foreach ($cols as $k => $l): ?><option value="<?= e($k) ?>"<?= $k === ($c['type'] === 'photo' ? 'photos' : 'programmes') ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="f"><span class="f__k">Message au contributeur <i>facultatif</i></span><textarea name="message" rows="3" data-wysiwyg="mini" placeholder="Merci ! Votre photo illustre désormais la fiche…"></textarea></div>
        <button type="submit" class="btn btn--navy">Publier</button>
        <span class="f__help">« Galerie d’une fiche » et « Nouvel objet » valident la contribution et préviennent le contributeur.</span>
      </form>
    <?php endif; ?>

    <form class="card card--pad" method="post" action="/admin/contributions/<?= e($c['ticket']) ?>">
      <?= csrf_field() ?>
      <h2 class="card__t card__t--sm">Décision</h2>
      <div class="f"><span class="f__k">Fiche corrigée ou enrichie <i>facultatif</i></span>
        <input type="text" data-ac="fiches" data-ac-id="fiche_id" placeholder="Fiche modifiée grâce à cette contribution" value="<?= $linked ? e($linked['title']) : '' ?>">
        <input type="hidden" name="fiche_id" value="<?= $linked ? (int) $linked['id'] : '' ?>">
      </div>
      <?php if (($c['type'] ?? '') === 'temoignage'): $pub = $c['public'] ?? true; ?>
      <div class="f iyebox" style="border:2px solid var(--navy);padding:12px;background:var(--cream)">
        <span class="f__k">« Ils y étaient » sur la fiche du match</span>
        <label class="row" style="gap:8px"><input type="checkbox" name="public" value="1"<?= $pub ? ' checked' : '' ?>> Publier ce souvenir sur la fiche du match choisie ci-dessus</label>
        <label class="f"><span class="f__k">Texte publié <i>relu et corrigé si besoin · 1 500 caractères</i></span><textarea name="public_text" rows="6" maxlength="1500" class="in" data-proof="quote" spellcheck="true"><?= e((string) ($c['public_text'] ?? $c['description'] ?? '')) ?></textarea></label>
        <label class="f"><span class="f__k">Signature</span><input type="text" name="public_name" maxlength="80" value="<?= e((string) ($c['public_name'] ?? \App\Services\Souvenirs::shortName((string) ($c['name'] ?? '')))) ?>"></label>
        <span class="f__help">Le contributeur a autorisé la publication en envoyant son témoignage. Seulement sur une fiche de match ; « Valider » met le souvenir en ligne.</span>
      </div>
      <?php endif; ?>
      <div class="f"><span class="f__k">Message au contributeur</span><textarea name="message" rows="4" data-wysiwyg="mini" placeholder="Un mot de remerciement, une question ou le motif du refus…"></textarea></div>
      <div class="row">
        <button type="submit" name="action" value="valider" class="btn btn--navy">✓ Valider</button>
        <button type="submit" name="action" value="info" class="btn">? Demander une précision</button>
        <button type="submit" name="action" value="refuser" class="btn btn--danger">Refuser</button>
      </div>
      <span class="f__help">Pour une correction ou un témoignage : modifiez d’abord la fiche, puis validez ici.</span>
    </form>

    <?php if (Auth::can('destroy')): ?>
      <form class="card card--pad" method="post" action="/admin/contributions/<?= e($c['ticket']) ?>" data-confirm="Supprimer définitivement ?|La contribution et ses fichiers seront effacés (les copies déjà importées dans la médiathèque sont conservées).|Supprimer|danger">
        <?= csrf_field() ?>
        <h2 class="card__t card__t--sm">Supprimer</h2>
        <p class="small muted" style="margin:0">Efface les coordonnées et les fichiers reçus (RGPD).</p>
        <button type="submit" name="action" value="supprimer" class="btn btn--danger">Supprimer la contribution</button>
      </form>
    <?php endif; ?>
  </div>
</div>
