<div class="card">
  <div class="drop" data-drop>
    <p><b>Glissez des fichiers ici</b> ou</p>
    <label class="b b--y">Choisir des fichiers<input type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4" hidden data-upload></label>
    <p class="bo-muted">JPG, PNG, WebP, GIF (15 Mo max, les grandes photos sont réduites à 2400 px) · MP4 (vidéo du héros). Taille maximale acceptée par le serveur : <?= e(ini_get('upload_max_filesize')) ?>.</p>
  </div>
</div>
<div class="bo-actions" style="margin:16px 0">
  <button class="b b--l is-on" type="button" data-media-folder="tout">Tout</button>
  <button class="b b--l" type="button" data-media-folder="uploads">Téléversés</button>
  <button class="b b--l" type="button" data-media-folder="media">Importés de renovest.fr</button>
  <input type="search" placeholder="Filtrer par nom…" data-media-search>
</div>
<div class="mgrid" data-media-grid></div>
