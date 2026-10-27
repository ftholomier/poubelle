<?php
/** @var array $w @var string $csrf */
$code = (string) ($w['code'] ?? '');
$avis = (string) ($w['avis'] ?? 'after');
$footerCode = (string) ($w['footer_code'] ?? '');
$footerPlace = (string) ($w['footer_place'] ?? 'before');
$opt = static fn(string $value, string $current, string $label) => '<option value="' . e($value) . '"' . ($value === $current ? ' selected' : '') . '>' . e($label) . '</option>';
$placeholder = '&lt;script src=&quot;https://…&quot; defer&gt;&lt;/script&gt;&#10;&lt;div class=&quot;…&quot;&gt;&lt;/div&gt;';
?>
<?= LD\View::render('admin/reviews-tabs', ['on' => 'widget']) ?>

<form method="post" action="/admin/avis/widget" class="settings-form">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <p class="small">Tu utilises un service d’avis (Trustindex, Elfsight, Avis Vérifiés, Guest Suite, Custplace, EmbedSocial…) qui fournit des codes à intégrer ? Colle chaque code tel quel dans sa case : un <b>carrousel</b> pour toutes les pages, un <b>mur d’avis</b> pour la page Avis. Le code est inséré tel quel : ne colle que du code venant d’un service de confiance. Service tiers : il ne se charge qu’après l’accord du visiteur dans le bandeau cookies (à défaut, le pied de page affiche ta note Google et tes avis).</p>

  <section class="card">
    <div class="card__head"><h2>1. Carrousel d’avis — toutes les pages</h2><?php if ($footerCode !== ''): ?><span class="badge badge--ok">En ligne</span><?php endif; ?></div>
    <p class="small">Affiché sur <b>toutes les pages du site</b>, au-dessus du pied de page. Choisis dans ton service un widget « carrousel » (ou « slider »). Sur l’accueil, il remplace la section « Ils en parlent mieux que moi » ; sur la page Avis, il est remplacé par le mur d’avis ci-dessous quand celui-ci est renseigné.</p>
    <label class="lbl">Code du carrousel (HTML / JavaScript)
      <textarea name="footer_code" class="code-area" rows="8" spellcheck="false" placeholder="<?= $placeholder ?>"><?= e($footerCode) ?></textarea>
    </label>
    <div class="form form--cols">
      <label>Emplacement
        <select name="footer_place">
          <?= $opt('before', $footerPlace, 'Juste au-dessus du pied de page (fond blanc)') ?>
          <?= $opt('inside', $footerPlace, 'Dans le pied de page, au-dessus de la citation (fond sombre)') ?>
        </select>
      </label>
    </div>
    <p class="small muted">Fond blanc : idéal pour un widget au thème clair. Fond sombre : choisis plutôt le thème sombre du widget dans ton service. Sans code ici, le pied de page affiche la note Google et des extraits de tes avis (si Google est connecté ou si des avis sont publiés).</p>
  </section>

  <section class="card">
    <div class="card__head"><h2>2. Mur d’avis — page Avis</h2><?php if ($code !== ''): ?><span class="badge badge--ok">En ligne</span><?php endif; ?></div>
    <p class="small">Affiché uniquement sur la page <a href="/avis" target="_blank" rel="noopener">Avis</a> (pas sur l’accueil : le carrousel y est déjà). Choisis dans ton service un widget « mur » (ou « grille », « wall »).</p>
    <label class="lbl">Code du mur d’avis (HTML / JavaScript)
      <textarea name="code" class="code-area" rows="8" spellcheck="false" placeholder="<?= $placeholder ?>"><?= e($code) ?></textarea>
    </label>
    <div class="form form--cols">
      <label>Page Avis
        <select name="avis">
          <?= $opt('after', $avis, 'Sous les avis du site') ?>
          <?= $opt('before', $avis, 'Au-dessus des avis du site') ?>
          <?= $opt('replace', $avis, 'À la place des avis du site') ?>
          <?= $opt('off', $avis, 'Ne pas afficher') ?>
        </select>
      </label>
    </div>
    <p class="small muted">Tant qu’aucun avis n’est publié sur le site, le mur s’affiche quand même sur la page Avis (même réglé sur « Ne pas afficher »).</p>
  </section>

  <div class="actions sticky-actions">
    <button class="btn btn--red" type="submit">Enregistrer</button>
    <a class="btn btn--ghost" href="/" target="_blank" rel="noopener">Voir le carrousel (accueil) ↗</a>
    <a class="btn btn--ghost" href="/avis" target="_blank" rel="noopener">Voir la page Avis ↗</a>
  </div>
</form>
