<?php
/** Équipe › Tickets › un ticket. Variables : $t, $admin, $canEdit, $prompt, $unread, $edit */
use App\Services\Tickets;
include __DIR__ . '/_style.php';
?>
<p><a href="/admin/tickets">← Tous les tickets</a></p>
<div class="tk-grid">
  <div>
    <article class="card card--pad">
      <div class="card__head"><h2 class="card__t">n° <?= (int) $t['id'] ?> · <?= e($t['title']) ?></h2><span class="tk-state tk-state--<?= e($t['status']) ?>"><?= e(Tickets::STATES[$t['status']]) ?></span></div>
      <p class="tk-meta"><?= e(Tickets::TYPES[$t['type']]) ?> · priorité <span class="tk-prio--<?= e($t['priority']) ?>"><?= e(Tickets::PRIORITIES[$t['priority']]) ?></span> · ouvert par <b><?= e($t['author']['name'] ?? '') ?></b> le <?= e(date('d/m/Y à H:i', strtotime($t['created_at']))) ?><?php if ($t['page'] !== ''): /* lien seulement vers une adresse du site : « //autre-site » ou « /\autre-site » restent du texte */ ?> · écran : <?= preg_match('#^/(?![/\\\\])#', $t['page']) ? '<a href="' . e($t['page']) . '">' . e($t['page']) . '</a>' : e($t['page']) ?><?php endif; ?></p>
      <?php if ($edit && $canEdit): ?>
        <form method="post" action="/admin/tickets/<?= (int) $t['id'] ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="modifier">
          <?php include __DIR__ . '/_form.php'; ?>
          <div class="row"><button class="btn btn--navy" type="submit">Enregistrer</button><a class="btn btn--ghost" href="/admin/tickets/<?= (int) $t['id'] ?>">Annuler</a></div>
        </form>
      <?php else: ?>
        <h3 class="card__t card__t--sm">La demande</h3>
        <div class="tk-body"><?= e($t['body']) ?></div>
        <?php $files = $t['files'] ?? []; $tid = $t['id']; $pjRemove = false; include __DIR__ . '/_pj_list.php'; ?>
        <?php if ($canEdit): ?><p style="margin-top:12px"><a class="btn btn--sm" href="?modifier=1">Modifier le ticket</a><?php if (!$admin): ?> <span class="xs muted">possible tant qu’il est « Nouveau »</span><?php endif; ?></p><?php endif; ?>
      <?php endif; ?>
    </article>

    <article class="card card--pad" style="margin-top:16px">
      <div class="card__head"><h2 class="card__t card__t--sm">Prompt pour l’IA</h2><?php if ($admin): ?><button type="button" class="btn btn--sm btn--yellow" data-copy="<?= e($prompt) ?>">Copier le ticket pour l’IA</button><?php endif; ?></div>
      <?php if ($t['prompt'] !== ''): ?><div class="tk-prompt"><?= e($t['prompt']) ?></div>
      <?php else: ?><p class="muted small">Pas de prompt proposé : <?= $admin ? 'le bouton copie quand même le titre et la demande.' : 'vous pouvez en ajouter un en modifiant le ticket, ou dans une réponse.' ?></p><?php endif; ?>
    </article>

    <article class="card card--pad" id="fil" style="margin-top:16px">
      <h2 class="card__t card__t--sm">Échanges</h2>
      <?php foreach ($t['comments'] as $c): $isAdmin = (\App\Core\Auth::users()[$c['by']['id'] ?? '']['role'] ?? '') === 'admin'; ?>
        <div class="tk-com<?= $isAdmin ? ' tk-com--admin' : '' ?>"><b><?= e($c['by']['name'] ?? '') ?></b> <span class="xs muted"><?= e(date('d/m/Y H:i', strtotime($c['at']))) ?></span><?php if (($c['text'] ?? '') !== ''): ?><div class="tk-body"><?= e($c['text']) ?></div><?php endif; ?><?php $files = $c['files'] ?? []; $tid = $t['id']; $pjRemove = false; include __DIR__ . '/_pj_list.php'; ?></div>
      <?php endforeach; ?>
      <?php if (!$t['comments']): ?><p class="muted small">Pas encore de réponse.</p><?php endif; ?>
      <form method="post" action="/admin/tickets/<?= (int) $t['id'] ?>#fil" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="commenter">
        <label class="f"><span class="f__k">Répondre</span><textarea name="texte" rows="4" maxlength="5000" placeholder="Une précision, une réponse, un « c’est bon pour moi »… (ou seulement une capture d’écran)"></textarea></label>
        <?php $pjCompact = true; include __DIR__ . '/_pj.php'; ?>
        <button class="btn btn--navy" type="submit">Envoyer</button> <span class="xs muted">L’auteur et l’administrateur sont prévenus par e-mail.</span>
      </form>
    </article>
  </div>

  <aside>
    <?php if ($admin): ?>
    <div class="card card--pad">
      <h2 class="card__t card__t--sm">Traiter le ticket</h2>
      <form method="post" action="/admin/tickets/<?= (int) $t['id'] ?>"><?= csrf_field() ?><input type="hidden" name="action" value="statut">
        <label class="f"><span class="f__k">Statut</span><select name="status"><?php foreach (Tickets::STATES as $k => $l): ?><option value="<?= e($k) ?>"<?= $t['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label class="f"><span class="f__k">Priorité</span><select name="priority"><?php foreach (Tickets::PRIORITIES as $k => $l): ?><option value="<?= e($k) ?>"<?= $t['priority'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label class="f"><span class="f__k">Un mot pour l’auteur (facultatif)</span><textarea name="texte" rows="3" maxlength="5000" placeholder="« En ligne, à vérifier sur la fiche… », « Pas possible parce que… »"></textarea></label>
        <button class="btn btn--navy" type="submit">Mettre à jour</button>
      </form>
      <form method="post" action="/admin/tickets/<?= (int) $t['id'] ?>" style="margin-top:14px" data-confirm="Supprimer ce ticket ?|Le ticket, ses échanges et son historique seront effacés définitivement.|Supprimer|danger"><?= csrf_field() ?><input type="hidden" name="action" value="supprimer"><button class="btn btn--sm btn--ghost" type="submit">Supprimer le ticket</button></form>
    </div>
    <?php endif; ?>
    <div class="card card--pad"<?= $admin ? ' style="margin-top:16px"' : '' ?>>
      <h2 class="card__t card__t--sm">Historique</h2>
      <ol class="tk-hist"><?php foreach (array_reverse($t['history']) as $h): ?><li><b><?= e(date('d/m H:i', strtotime($h['at']))) ?></b> · <?= e($h['by']) ?> <?= e($h['what']) ?></li><?php endforeach; ?></ol>
    </div>
  </aside>
</div>
