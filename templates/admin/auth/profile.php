<?php /** Profil. Variables : $u, $activity */ use App\Admin\Base; ?>
<div class="cols">
  <form method="post" class="card card--pad">
    <?= csrf_field() ?>
    <h2 class="card__t">Mon compte</h2>
    <label class="f"><span class="f__k">Nom affiché</span><input name="name" value="<?= e($u['name']) ?>" required></label>
    <label class="f"><span class="f__k">E-mail</span><input value="<?= e($u['email']) ?>" disabled></label>
    <span class="f__help">Rôle : <?= e(\App\Core\Auth::ROLES[$u['role']] ?? $u['role']) ?> · dernière connexion : <?= e(Base::ago($u['last_login'] ?? null)) ?></span>
    <h3 class="card__t card__t--sm" style="margin-top:6px">Changer de mot de passe</h3>
    <label class="f"><span class="f__k">Mot de passe actuel</span><input type="password" name="current" autocomplete="current-password"></label>
    <div class="fgrid">
      <label class="f"><span class="f__k">Nouveau <i>10 caractères min.</i></span><input type="password" name="password" minlength="10" autocomplete="new-password"></label>
      <label class="f"><span class="f__k">Confirmation</span><input type="password" name="password2" minlength="10" autocomplete="new-password"></label>
    </div>
    <button type="submit" class="btn btn--navy" style="align-self:flex-start">Enregistrer</button>
  </form>
  <div class="card">
    <div class="card__head"><h2 class="card__t">Mon activité récente</h2></div>
    <?php foreach ($activity as $a): ?>
      <div class="card__row" style="grid-template-columns:minmax(0,1fr) auto"><span><?= e($a['action']) ?> <?php if (!empty($a['title'])): ?><b><?= e($a['title']) ?></b><?php endif; ?></span><span class="xs muted"><?= e(Base::ago($a['at'])) ?></span></div>
    <?php endforeach; ?>
    <?php if (!$activity): ?><div class="card__body muted">Aucune activité pour le moment.</div><?php endif; ?>
  </div>
</div>
