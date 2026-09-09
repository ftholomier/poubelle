<?php
/** Réglages : identité et lieux, clés API, comptes. */

use App\Admin;
use App\Config;
use App\Csrf;
use App\Reviews;
use App\Router;
use App\Store;
use App\Text;
use App\View;

echo View::admin('_layout_start', get_defined_vars());

$tab = \in_array($tab, ['site', 'keys', 'users'], true) ? $tab : 'site';
$reviewsCache = Store::read(Reviews::CACHE);
?>
<div class="screen">
  <div class="tablist">
    <?php foreach (['site' => 'Site & lieux', 'keys' => 'Clés API & emails', 'users' => 'Comptes'] as $key => $label): ?>
      <a class="tab<?= $tab === $key ? ' is-active' : '' ?>" href="<?= Text::e(Router::adminUrl('settings', ['tab' => $key])) ?>"><?= Text::e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($tab === 'site'): ?>
  <form method="post" action="<?= Text::e(Router::adminUrl()) ?>">
    <?= Csrf::field('admin') ?>
    <input type="hidden" name="action" value="settings-save">

    <section class="panel panel--pad" style="margin-top:18px">
      <h2 style="margin:0;font:800 19px/1 'Bricolage Grotesque',sans-serif">Identité</h2>
      <div class="grid-2" style="margin-top:14px">
        <div>
          <label class="label" for="s-name">NOM DU SITE</label>
          <input class="field" id="s-name" type="text" name="s[name]" value="<?= Text::e((string) ($settings['site']['name'] ?? '')) ?>">
        </div>
        <div>
          <label class="label" for="s-tagline">SIGNATURE</label>
          <input class="field" id="s-tagline" type="text" name="s[tagline]" value="<?= Text::e((string) ($settings['site']['tagline'] ?? '')) ?>">
        </div>
        <div>
          <label class="label" for="s-email">EMAIL DE CONTACT</label>
          <input class="field" id="s-email" type="email" name="s[email]" value="<?= Text::e((string) ($settings['contact']['email'] ?? '')) ?>">
        </div>
        <div>
          <label class="label" for="s-phone">TÉLÉPHONE</label>
          <input class="field" id="s-phone" type="text" name="s[phone]" value="<?= Text::e((string) ($settings['contact']['phone'] ?? '')) ?>" placeholder="laisser vide pour le masquer">
        </div>
      </div>
      <label class="label" style="margin-top:18px" for="s-hours">HORAIRES DE VISITE</label>
      <input class="field" id="s-hours" type="text" name="s[hours]" value="<?= Text::e((string) ($settings['contact']['hours'] ?? '')) ?>">
      <div class="grid-2" style="margin-top:18px">
        <div>
          <label class="label" for="s-top1">BANDEAU HAUT — ARGUMENT 1</label>
          <input class="field" id="s-top1" type="text" name="s[topLine1]" value="<?= Text::e((string) ($settings['top']['line1'] ?? '')) ?>">
        </div>
        <div>
          <label class="label" for="s-top2">BANDEAU HAUT — ARGUMENT 2</label>
          <input class="field" id="s-top2" type="text" name="s[topLine2]" value="<?= Text::e((string) ($settings['top']['line2'] ?? '')) ?>">
        </div>
      </div>
      <div class="grid-2" style="margin-top:18px">
        <div>
          <label class="label" for="s-suffix">TITRE PAR DÉFAUT (SEO)</label>
          <input class="field" id="s-suffix" type="text" name="s[titleSuffix]" value="<?= Text::e((string) ($settings['seo']['titleSuffix'] ?? '')) ?>">
        </div>
        <div>
          <label class="label" for="s-seodesc">DESCRIPTION PAR DÉFAUT (SEO)</label>
          <input class="field" id="s-seodesc" type="text" name="s[seoDescription]" value="<?= Text::e((string) ($settings['seo']['description'] ?? '')) ?>">
        </div>
      </div>
      <label class="check" style="margin-top:18px"><input type="checkbox" name="s[autoReply]" value="1" <?= !empty($settings['contact']['autoReply']) ? 'checked' : '' ?>><span>Envoyer un accusé de réception aux demandes de contact</span></label>
    </section>

    <section class="panel panel--pad" style="margin-top:18px">
      <h2 style="margin:0;font:800 19px/1 'Bricolage Grotesque',sans-serif">Conversion</h2>
      <div class="grid-2" style="margin-top:14px">
        <div>
          <label class="check"><input type="checkbox" name="s[stickyEnabled]" value="1" <?= !empty($settings['sticky']['enabled']) ? 'checked' : '' ?>><span>Barre CTA collante</span></label>
          <label class="label" style="margin-top:14px" for="s-halo">HALO ANIMÉ SUR</label>
          <select class="field" id="s-halo" name="s[stickyHalo]">
            <option value="reserve" <?= ($settings['sticky']['halo'] ?? '') === 'reserve' ? 'selected' : '' ?>>Réservez votre bureau</option>
            <option value="contact" <?= ($settings['sticky']['halo'] ?? '') === 'contact' ? 'selected' : '' ?>>Contactez-nous</option>
          </select>
        </div>
        <div>
          <label class="check"><input type="checkbox" name="s[exitEnabled]" value="1" <?= !empty($settings['exit']['enabled']) ? 'checked' : '' ?>><span>Pop-up de sortie</span></label>
          <label class="label" style="margin-top:14px" for="s-inact">DÉCLENCHEMENT MOBILE APRÈS (SECONDES D'INACTIVITÉ)</label>
          <input class="field" id="s-inact" type="number" min="10" name="s[exitInactivity]" value="<?= (int) ($settings['exit']['inactivitySeconds'] ?? 45) ?>">
        </div>
        <div>
          <label class="check"><input type="checkbox" name="s[consentEnabled]" value="1" <?= ($settings['consent']['enabled'] ?? true) ? 'checked' : '' ?>><span>Bandeau de consentement aux cookies</span></label>
          <div class="hint">Trois catégories : nécessaires (toujours actives), mesure d'audience, et envoi des questions de l'assistant à Google. Refusé, aucun script tiers n'est chargé et l'assistant répond uniquement depuis l'index local.</div>
        </div>
        <div>
          <label class="check"><input type="checkbox" name="s[reviewsEnabled]" value="1" <?= !empty($settings['reviews']['enabled']) ? 'checked' : '' ?>><span>Afficher les avis sur l'accueil</span></label>
          <label class="label" style="margin-top:14px" for="s-badge">TEXTE DE LA PASTILLE D'AVIS</label>
          <input class="field" id="s-badge" type="text" name="s[reviewsBadge]" value="<?= Text::e((string) ($settings['reviews']['badge'] ?? '')) ?>">
        </div>
        <div>
          <label class="label" for="s-analytics">MESURE D'AUDIENCE</label>
          <select class="field" id="s-analytics" name="s[analyticsProvider]">
            <?php foreach (['none' => 'Aucune', 'plausible' => 'Plausible', 'matomo' => 'Matomo auto-hébergé'] as $value => $label): ?>
              <option value="<?= Text::e($value) ?>" <?= ($settings['analytics']['provider'] ?? 'none') === $value ? 'selected' : '' ?>><?= Text::e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <input class="field" style="margin-top:10px" type="text" name="s[analyticsDomain]" value="<?= Text::e((string) ($settings['analytics']['domain'] ?? '')) ?>" placeholder="domaine Plausible (ex. ioio.fr)">
          <input class="field" style="margin-top:10px" type="text" name="s[analyticsSrc]" value="<?= Text::e((string) ($settings['analytics']['src'] ?? '')) ?>" placeholder="URL du script Matomo">
        </div>
      </div>
    </section>

    <section class="panel panel--pad" style="margin-top:18px">
      <h2 style="margin:0;font:800 19px/1 'Bricolage Grotesque',sans-serif">Les deux lieux</h2>
      <p class="muted" style="margin:8px 0 0">Ces informations alimentent l'accueil, la page « Nos espaces », la page contact et les données structurées.</p>
      <?php foreach ((array) ($settings['sites'] ?? []) as $i => $site): ?>
        <div class="repeat-item" style="margin-top:16px">
          <div class="repeat-item__head">
            <span class="repeat-item__title"><?= Text::e(mb_strtoupper((string) ($site['shortName'] ?? $site['id'] ?? ''))) ?></span>
            <label class="check"><input type="checkbox" name="s[sites][<?= $i ?>][enabled]" value="1" <?= ($site['enabled'] ?? true) ? 'checked' : '' ?>><span>Actif</span></label>
          </div>
          <div class="grid-2">
            <div><label class="label">NOM COMPLET</label><input class="field" type="text" name="s[sites][<?= $i ?>][name]" value="<?= Text::e((string) ($site['name'] ?? '')) ?>"></div>
            <div><label class="label">NOM COURT</label><input class="field" type="text" name="s[sites][<?= $i ?>][shortName]" value="<?= Text::e((string) ($site['shortName'] ?? '')) ?>"></div>
            <div><label class="label">ÉTIQUETTE</label><input class="field" type="text" name="s[sites][<?= $i ?>][tag]" value="<?= Text::e((string) ($site['tag'] ?? '')) ?>"></div>
            <div><label class="label">SURFACE</label><input class="field" type="text" name="s[sites][<?= $i ?>][area]" value="<?= Text::e((string) ($site['area'] ?? '')) ?>"></div>
            <div><label class="label">ADRESSE</label><input class="field" type="text" name="s[sites][<?= $i ?>][address]" value="<?= Text::e((string) ($site['address'] ?? '')) ?>"></div>
            <div><label class="label">CODE POSTAL / VILLE</label>
              <div style="display:flex;gap:10px">
                <input class="field" type="text" name="s[sites][<?= $i ?>][zip]" value="<?= Text::e((string) ($site['zip'] ?? '')) ?>" style="max-width:110px">
                <input class="field" type="text" name="s[sites][<?= $i ?>][city]" value="<?= Text::e((string) ($site['city'] ?? '')) ?>">
              </div>
            </div>
            <div><label class="label">BOUTON</label><input class="field" type="text" name="s[sites][<?= $i ?>][cta]" value="<?= Text::e((string) ($site['cta'] ?? '')) ?>"></div>
            <div><label class="label">COULEUR</label>
              <div style="display:flex;gap:10px;align-items:center">
                <input class="field" type="text" name="s[sites][<?= $i ?>][color]" value="<?= Text::e((string) ($site['color'] ?? '#FFD100')) ?>" data-color-text>
                <input type="color" value="<?= Text::e(preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($site['color'] ?? '')) === 1 ? (string) $site['color'] : '#FFD100') ?>" data-color-picker aria-label="Couleur" style="width:52px;height:52px;border:2px solid #0E0E0E;border-radius:12px;background:none;cursor:pointer;padding:2px">
              </div>
            </div>
          </div>
          <label class="label" style="margin-top:14px">NOTE D'ACCÈS</label>
          <input class="field" type="text" name="s[sites][<?= $i ?>][note]" value="<?= Text::e((string) ($site['note'] ?? '')) ?>">
          <label class="label" style="margin-top:14px">DESCRIPTION (CARTE DE L'ACCUEIL)</label>
          <textarea class="field" name="s[sites][<?= $i ?>][description]" rows="2"><?= Text::e((string) ($site['description'] ?? '')) ?></textarea>
          <label class="label" style="margin-top:14px">ÉTIQUETTES (UNE PAR LIGNE)</label>
          <textarea class="field" name="s[sites][<?= $i ?>][chips]" rows="3"><?= Text::e(implode("\n", array_map('strval', (array) ($site['chips'] ?? [])))) ?></textarea>
          <label class="label" style="margin-top:14px">LIEN GOOGLE MAPS</label>
          <input class="field" type="text" name="s[sites][<?= $i ?>][mapUrl]" value="<?= Text::e((string) ($site['mapUrl'] ?? '')) ?>">
          <?= View::admin('_field', [
              'field' => ['label' => 'Photo principale', 'type' => 'media', 'path' => 'photo', 'max' => 1],
              'value' => (string) ($site['photo'] ?? ''),
              'name' => 's[sites][' . $i . '][photo]',
              'media' => $media,
          ]) ?>
        </div>
      <?php endforeach; ?>

      <div class="form-actions">
        <button class="btn btn--ink" type="submit">Enregistrer les réglages</button>
      </div>
    </section>
  </form>

  <?php elseif ($tab === 'keys'): ?>
  <form method="post" action="<?= Text::e(Router::adminUrl()) ?>">
    <?= Csrf::field('admin') ?>
    <input type="hidden" name="action" value="keys-save">
    <section class="panel panel--pad" style="margin-top:18px">
      <h2 style="margin:0;font:800 19px/1 'Bricolage Grotesque',sans-serif">Clés API et envoi d'emails</h2>
      <p class="muted" style="margin:8px 0 0">
        Les valeurs sont écrites dans <strong>storage/secrets.json</strong>, hors racine web, en droits 0600. Une clé posée dans
        le fichier <strong>.env</strong> du serveur reste prioritaire et apparaît ici en lecture seule.
      </p>
      <div class="grid-2" style="margin-top:16px">
        <?php foreach (Admin::keyDefs() as $def):
            $key = $def['key'];
            $locked = Config::isLockedByEnv($key);
            $set = Config::has($key);
            $value = $def['secret'] && $set ? '••••••••' : (string) (Config::get($key) ?? ''); ?>
          <div>
            <label class="label" for="k-<?= Text::e($key) ?>">
              <?= Text::e(mb_strtoupper((string) $def['label'])) ?>
              <?php if ($locked): ?><span class="badge" style="background:#EDE5D5;margin-left:6px">.ENV</span>
              <?php elseif ($set): ?><span class="badge" style="background:#12B39A;margin-left:6px">EN PLACE</span><?php endif; ?>
            </label>
            <input class="field" id="k-<?= Text::e($key) ?>" type="<?= $def['secret'] ? 'password' : 'text' ?>"
                   name="k[<?= Text::e($key) ?>]" value="<?= Text::e($value) ?>"
                   autocomplete="off" <?= $locked ? 'disabled' : '' ?>>
            <?php if (!empty($def['hint'])): ?><div class="hint"><?= Text::e((string) $def['hint']) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="form-actions">
        <button class="btn btn--ink" type="submit">Enregistrer les clés</button>
        <span class="form-actions__note">Un champ vidé supprime la clé. Un champ masqué laissé tel quel conserve la valeur.</span>
      </div>
    </section>
  </form>

  <section class="panel panel--pad" style="margin-top:18px">
    <h2 style="margin:0;font:800 19px/1 'Bricolage Grotesque',sans-serif">Avis Google</h2>
    <p class="muted" style="margin:8px 0 14px">
      <?php if (Reviews::configured()): ?>
        Cache : <?= \count((array) ($reviewsCache['reviews'] ?? [])) ?> avis ·
        <?= Text::e(($reviewsCache['fetchedAt'] ?? '') !== '' ? Admin::humanDate((string) $reviewsCache['fetchedAt']) : 'jamais récupéré') ?>.
        Rafraîchissement automatique toutes les 24 h ; le site n'appelle jamais Google au moment du rendu.
      <?php else: ?>
        Sans clé Places, le site affiche les avis saisis dans <strong>content/reviews.json</strong>. Renseignez
        GOOGLE_PLACES_KEY et GOOGLE_PLACE_ID ci-dessus pour activer la récupération automatique.
      <?php endif; ?>
    </p>
    <form method="post" action="<?= Text::e(Router::adminUrl()) ?>">
      <?= Csrf::field('admin') ?>
      <input type="hidden" name="action" value="reviews-refresh">
      <button class="btn btn--outline" type="submit" <?= Reviews::configured() ? '' : 'disabled' ?>>Rafraîchir maintenant</button>
    </form>
  </section>

  <?php else: ?>
  <section class="panel" style="margin-top:18px">
    <div class="panel__head"><h2>Comptes</h2><span class="panel__file">content/users.json · Argon2id</span></div>
    <div class="panel__scroll">
      <div class="row row--head row--users"><span>EMAIL</span><span>RÔLE</span><span>DERNIÈRE CONNEXION</span><span style="text-align:right">ACTION</span></div>
      <?php foreach ($users as $account): ?>
        <div class="row row--users">
          <div>
            <div class="row__title"><?= Text::e((string) $account['email']) ?></div>
            <div class="row__sub">créé <?= Text::e(Admin::humanDate((string) ($account['createdAt'] ?? ''))) ?></div>
          </div>
          <span class="row__value"><?= Text::e(($account['role'] ?? '') === 'admin' ? 'Administrateur' : 'Éditeur') ?></span>
          <span class="row__value"><?= Text::e(($account['lastLoginAt'] ?? '') !== '' ? Admin::humanDate((string) $account['lastLoginAt']) : 'jamais') ?></span>
          <div class="row__actions">
            <?php if ((string) $account['email'] !== (string) $user['email']): ?>
              <form method="post" action="<?= Text::e(Router::adminUrl()) ?>" data-confirm="Supprimer ce compte ?">
                <?= Csrf::field('admin') ?>
                <input type="hidden" name="action" value="user-delete">
                <input type="hidden" name="email" value="<?= Text::e((string) $account['email']) ?>">
                <button class="btn btn--sm btn--danger" type="submit">Supprimer</button>
              </form>
            <?php else: ?>
              <span class="badge" style="background:#FFD100">VOUS</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="panels">
    <section class="panel panel--pad">
      <h2 style="margin:0;font:800 19px/1 'Bricolage Grotesque',sans-serif">Ajouter un compte</h2>
      <form method="post" action="<?= Text::e(Router::adminUrl()) ?>" style="margin-top:14px">
        <?= Csrf::field('admin') ?>
        <input type="hidden" name="action" value="user-add">
        <label class="label" for="u-email">EMAIL</label>
        <input class="field" id="u-email" type="email" name="email" required autocomplete="off">
        <label class="label label--mt" for="u-pass">MOT DE PASSE (10 CARACTÈRES MINIMUM)</label>
        <input class="field" id="u-pass" type="password" name="password" required minlength="10" autocomplete="new-password">
        <label class="label label--mt" for="u-role">RÔLE</label>
        <select class="field" id="u-role" name="role">
          <option value="editor">Éditeur</option>
          <option value="admin">Administrateur</option>
        </select>
        <button class="btn btn--ink btn--block" type="submit" style="margin-top:18px">Créer le compte</button>
      </form>
    </section>

    <section class="panel panel--pad">
      <h2 style="margin:0;font:800 19px/1 'Bricolage Grotesque',sans-serif">Changer mon mot de passe</h2>
      <form method="post" action="<?= Text::e(Router::adminUrl()) ?>" style="margin-top:14px">
        <?= Csrf::field('admin') ?>
        <input type="hidden" name="action" value="user-password">
        <label class="label" for="mp1">NOUVEAU MOT DE PASSE</label>
        <input class="field" id="mp1" type="password" name="password" required minlength="10" autocomplete="new-password">
        <label class="label label--mt" for="mp2">CONFIRMATION</label>
        <input class="field" id="mp2" type="password" name="password2" required minlength="10" autocomplete="new-password">
        <button class="btn btn--ink btn--block" type="submit" style="margin-top:18px">Modifier</button>
        <div class="hint">Toutes vos sessions « rester connecté » seront invalidées.</div>
      </form>
    </section>
  </div>
  <?php endif; ?>
</div>
<?= View::admin('_layout_end') ?>
