<?php
declare(strict_types=1);

/**
 * Réinitialisation de mot de passe du back-office.
 *
 * Sans cette fonctionnalité, un compte dont le mot de passe est perdu ne
 * se récupère qu'en éditant `data/users.json` à la main sur le serveur.
 *
 * Le jeton n'est jamais stocké en clair : seule son empreinte SHA-256
 * l'est, exactement comme un mot de passe. Quelqu'un qui obtiendrait une
 * copie du fichier de données ne pourrait donc pas s'en servir pour
 * prendre la main sur un compte. Le lien est à usage unique et expire au
 * bout d'une heure.
 */
final class PasswordReset
{
    /** Durée de validité d'un lien, en secondes. */
    public const DUREE = 3600;

    /** Longueur minimale du nouveau mot de passe. */
    public const LONGUEUR_MIN = 12;

    /** Fichier de repli, écrit uniquement si aucun e-mail n'a pu partir. */
    public const FICHIER_REPLI = 'REINITIALISATION.txt';

    /**
     * Ouvre une demande pour cette adresse et envoie le lien.
     *
     * Ne renvoie jamais si l'adresse correspond à un compte : l'appelant
     * affiche le même message dans tous les cas, sans quoi le formulaire
     * deviendrait un outil pour découvrir les comptes existants.
     */
    public static function demander(string $email): void
    {
        $email = strtolower(trim($email));
        $compte = null;
        foreach (Store::read('users') as $u) {
            if (strtolower((string) ($u['email'] ?? '')) === $email && ($u['active'] ?? true)) {
                $compte = $u;
                break;
            }
        }
        if ($compte === null) {
            return;
        }

        $jeton = bin2hex(random_bytes(32));
        Store::push('password-resets', [
            'user_id' => (string) ($compte['id'] ?? ''),
            'token_hash' => hash('sha256', $jeton),
            'expires_at' => date('c', time() + self::DUREE),
            'ip' => hash('sha256', client_ip()),
            'used_at' => null,
        ]);

        self::envoyer((string) ($compte['email'] ?? ''), (string) ($compte['name'] ?? ''), $jeton);
    }

    /**
     * Retrouve la demande correspondant à un jeton, si elle est encore
     * valide (non consommée, non expirée, compte toujours actif).
     *
     * @return array{0:array,1:array}|null couple demande / utilisateur
     */
    public static function trouver(string $jeton): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $jeton) !== 1) {
            return null;
        }
        $empreinte = hash('sha256', $jeton);
        $maintenant = time();

        foreach (Store::read('password-resets') as $ligne) {
            // hash_equals : la comparaison ne doit pas renseigner sur le
            // nombre de caractères devinés.
            if (!hash_equals((string) ($ligne['token_hash'] ?? ''), $empreinte)) {
                continue;
            }
            if (!empty($ligne['used_at'])) {
                return null;
            }
            if ((int) strtotime((string) ($ligne['expires_at'] ?? '')) < $maintenant) {
                return null;
            }
            $utilisateur = Store::find('users', (string) ($ligne['user_id'] ?? ''));
            if ($utilisateur === null || !($utilisateur['active'] ?? true)) {
                return null;
            }
            return [$ligne, $utilisateur];
        }
        return null;
    }

    /**
     * Applique le nouveau mot de passe et referme toutes les demandes du
     * compte : un second lien envoyé entre-temps ne doit plus fonctionner.
     */
    public static function consommer(string $jeton, string $motDePasse): bool
    {
        $trouve = self::trouver($jeton);
        if ($trouve === null) {
            return false;
        }
        [, $utilisateur] = $trouve;
        $id = (string) ($utilisateur['id'] ?? '');

        Store::update('users', $id, [
            'password_hash' => password_hash($motDePasse, PASSWORD_DEFAULT),
            'must_change_password' => false,
            // Invalide les sessions ouvertes avant ce changement : si le mot
            // de passe a été perdu parce qu'un tiers l'avait, sa session ne
            // doit pas survivre à la reprise en main du compte.
            'password_changed_at' => date('c'),
        ]);

        Store::mutate('password-resets', static function (array $lignes) use ($id): array {
            return array_values(array_filter(
                $lignes,
                static fn ($l) => (string) ($l['user_id'] ?? '') !== $id
            ));
        });

        return true;
    }

    /** Supprime les demandes expirées depuis plus d'un jour. */
    public static function purger(): int
    {
        $limite = time() - 86400;
        $supprimees = 0;
        Store::mutate('password-resets', static function (array $lignes) use ($limite, &$supprimees): array {
            $gardees = [];
            foreach ($lignes as $l) {
                if ((int) strtotime((string) ($l['expires_at'] ?? '')) < $limite) {
                    $supprimees++;
                    continue;
                }
                $gardees[] = $l;
            }
            return $gardees;
        });
        return $supprimees;
    }

    private static function envoyer(string $email, string $nom, string $jeton): void
    {
        $lien = rtrim((string) settings('site.url', ''), '/') . url('admin/nouveau-mot-de-passe') . '?jeton=' . $jeton;
        $prenom = trim(explode(' ', $nom)[0] ?? '');
        $minutes = (int) round(self::DUREE / 60);

        $corps = '<h2 style="margin:0 0 12px">Réinitialisation de votre mot de passe</h2>'
            . '<p>Bonjour' . ($prenom !== '' ? ' ' . e($prenom) : '') . ',</p>'
            . '<p>Une réinitialisation du mot de passe du back-office Suisse Immo a été demandée '
            . 'pour cette adresse. Le lien ci-dessous est valable ' . $minutes . ' minutes et ne peut servir qu’une fois.</p>'
            . '<p style="margin:24px 0"><a href="' . e($lien) . '" '
            . 'style="display:inline-block;padding:14px 26px;border-radius:99px;background:#e02c3f;color:#fff;'
            . 'text-decoration:none;font-weight:600">Choisir un nouveau mot de passe</a></p>'
            . '<p style="font-size:13px;color:#8d99ae">Si le bouton ne fonctionne pas, copiez cette adresse '
            . 'dans votre navigateur :<br>' . e($lien) . '</p>'
            . '<p><strong>Vous n’êtes pas à l’origine de cette demande ?</strong> Ignorez ce message : '
            . 'votre mot de passe actuel reste valable, et le lien expirera de lui-même.</p>';

        // Message de service : il part même si les notifications sont
        // désactivées dans les réglages.
        $parti = Mailer::send($email, 'Réinitialisation de votre mot de passe — Suisse Immo', $corps, null, true);

        if (!$parti) {
            self::ecrireRepli($email, $lien);
        }
    }

    /**
     * Repli lorsque aucun e-mail ne peut partir.
     *
     * Sans serveur SMTP configuré et sans agent local, la personne serait
     * définitivement bloquée hors du back-office. Le lien est alors déposé
     * dans `/data`, dossier refusé au web et hors racine publique : il ne
     * se lit qu'avec un accès au serveur, ce qui reste bien moins exposé
     * qu'un mot de passe modifié à la main dans `users.json`. Le fichier
     * est écrasé à chaque demande et supprimé dès qu'un lien est utilisé.
     */
    private static function ecrireRepli(string $email, string $lien): void
    {
        @file_put_contents(
            DATA_DIR . '/' . self::FICHIER_REPLI,
            "Demande de réinitialisation du " . date('d/m/Y H:i') . "\n"
            . "Compte : " . $email . "\n"
            . "Lien (valable " . (int) round(self::DUREE / 60) . " minutes, usage unique) :\n"
            . $lien . "\n\n"
            . "Ce fichier n'existe que parce qu'aucun e-mail n'a pu être envoyé.\n"
            . "Renseignez un serveur SMTP dans Back-office → Réglages → Envoi des e-mails,\n"
            . "puis supprimez ce fichier.\n",
            LOCK_EX
        );
    }
}
