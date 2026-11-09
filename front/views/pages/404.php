<div class="nf">
  <b>404</b>
  <h1 class="h2" style="margin-top:24px">Page introuvable</h1>
  <p style="color:#4a4a4a;font-size:17px;margin:14px 0 30px">La page demandée n'existe pas ou a été déplacée.</p>
  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
    <?= View::btn('Accueil', '/', 'btn-y btn-md') ?>
    <?= View::btn(Content::ui()['cta_bar']['contact'] ?? 'Contactez-nous', Content::path('contact'), 'btn-l') ?>
  </div>
</div>
