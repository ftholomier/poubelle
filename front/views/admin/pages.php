<div class="card card--flush">
  <table class="tbl">
    <thead><tr><th>Page</th><th>Adresse</th><th></th></tr></thead>
    <tbody>
      <tr><td><b>Accueil</b></td><td><code>/</code></td><td class="r"><a class="b b--l" href="/admin/accueil">Modifier</a></td></tr>
      <?php foreach (Content::pages() as $k => $p): if ($k === 'home') { continue; } ?>
        <tr>
          <td><b><?= e($p['title']) ?></b></td>
          <td><code><?= e($p['path']) ?></code></td>
          <td class="r"><a class="b b--l" href="<?= e($p['path']) ?>" target="_blank" rel="noopener">Voir ↗</a> <a class="b b--l" href="/admin/pages/<?= e($k) ?>">Modifier</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="bo-muted">Les adresses sont celles de renovest.fr : le référencement acquis est conservé. Les anciennes adresses d'images (<code>/wp-content/uploads/…</code>) sont redirigées vers les nouvelles.</p>
