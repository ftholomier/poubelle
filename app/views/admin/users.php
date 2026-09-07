<?php
/** @var array $users @var array $settings @var array $user */
use App\Core\View; use App\Security\Auth; use App\Security\Csrf;
$title = 'Comptes';
echo View::render('admin/partials/shell-open', compact('settings', 'user', 'active', 'newLeads', 'flash', 'title'));
$isAdmin = ($user['role'] ?? '') === Auth::ROLE_ADMIN;
?>
<header class="ad-head">
    <div>
        <h1 class="ad-head__title">Comptes d’accès</h1>
        <p class="ad-head__sub">Mots de passe hachés en Argon2id, connexion limitée après cinq tentatives.</p>
    </div>
</header>

<section class="ad-panel">
    <div class="ad-panel__head"><h2 class="ad-panel__title">Comptes existants</h2></div>
    <div class="ad-scroll">
        <table class="ad-table">
            <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $account): ?>
                <tr>
                    <td><strong><?= e($account['name']) ?></strong>
                        <?php if ((string) $account['id'] === (string) $user['id']): ?>
                            <span class="ad-tag ad-tag--info">vous</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($account['email']) ?></td>
                    <td><span class="ad-tag"><?= e($account['role']) ?></span></td>
                    <td><span class="ad-tag ad-tag--<?= !empty($account['active']) ? 'ok' : 'danger' ?>">
                        <?= !empty($account['active']) ? 'Actif' : 'Désactivé' ?></span></td>
                    <td class="ad-nowrap"><?= e($account['last_login'] ? date('d/m/Y H:i', strtotime((string) $account['last_login']) ?: time()) : '—') ?></td>
                    <td>
                        <?php if ($isAdmin && (string) $account['id'] !== (string) $user['id']): ?>
                            <div class="ad-table__actions">
                                <form method="post" class="ad-inline">
                                    <input type="hidden" name="_token" value="<?= e(Csrf::token('users')) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= e($account['id']) ?>">
                                    <button type="submit" class="ad-btn ad-btn--ghost ad-btn--sm">
                                        <?= !empty($account['active']) ? 'Désactiver' : 'Activer' ?>
                                    </button>
                                </form>
                                <form method="post" class="ad-inline" data-confirm="Supprimer ce compte définitivement ?">
                                    <input type="hidden" name="_token" value="<?= e(Csrf::token('users')) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e($account['id']) ?>">
                                    <button type="submit" class="ad-btn ad-btn--danger ad-btn--sm">Supprimer</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="ad-cols">
    <section class="ad-panel">
        <div class="ad-panel__head"><h2 class="ad-panel__title">Mon mot de passe</h2></div>
        <form method="post">
            <input type="hidden" name="_token" value="<?= e(Csrf::token('users')) ?>">
            <input type="hidden" name="action" value="password">
            <div class="ad-field">
                <label class="ad-label" for="current_password">Mot de passe actuel</label>
                <input class="ad-input" id="current_password" name="current_password" type="password" required autocomplete="current-password">
            </div>
            <div class="ad-field">
                <label class="ad-label" for="new_password">Nouveau mot de passe</label>
                <input class="ad-input" id="new_password" name="new_password" type="password" required minlength="10" autocomplete="new-password">
                <span class="ad-hint">10 caractères minimum, lettres et chiffres.</span>
            </div>
            <button type="submit" class="ad-btn ad-btn--primary">Modifier</button>
        </form>
    </section>

    <?php if ($isAdmin): ?>
        <section class="ad-panel">
            <div class="ad-panel__head"><h2 class="ad-panel__title">Nouveau compte</h2></div>
            <form method="post">
                <input type="hidden" name="_token" value="<?= e(Csrf::token('users')) ?>">
                <input type="hidden" name="action" value="create">
                <div class="ad-field">
                    <label class="ad-label" for="new_name">Nom</label>
                    <input class="ad-input" id="new_name" name="name" required>
                </div>
                <div class="ad-field">
                    <label class="ad-label" for="new_email">Adresse e-mail</label>
                    <input class="ad-input" id="new_email" name="email" type="email" required>
                </div>
                <div class="ad-field">
                    <label class="ad-label" for="new_pass">Mot de passe</label>
                    <input class="ad-input" id="new_pass" name="password" type="password" required minlength="10" autocomplete="new-password">
                </div>
                <div class="ad-field">
                    <label class="ad-label" for="new_role">Rôle</label>
                    <select class="ad-input" id="new_role" name="role">
                        <option value="editor">Éditeur (contenus uniquement)</option>
                        <option value="admin">Administrateur (accès complet)</option>
                    </select>
                </div>
                <button type="submit" class="ad-btn ad-btn--primary">Créer le compte</button>
            </form>
        </section>
    <?php endif; ?>
</div>

<?= View::render('admin/partials/shell-close') ?>
