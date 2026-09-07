<?php
declare(strict_types=1);

/**
 * Installation initiale : copie les données de démarrage dans /data
 * et crée un compte administrateur. Idempotent.
 */
final class Installer
{
    public static function run(): void
    {
        foreach ([DATA_DIR, UPLOAD_DIR] as $dir) {
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        }
        // Interdit l'accès direct si /data se retrouve exposé par erreur.
        @file_put_contents(DATA_DIR . '/.htaccess', "Require all denied\nDeny from all\n");

        foreach (['content', 'settings', 'posts'] as $name) {
            $target = DATA_DIR . '/' . $name . '.json';
            if (!is_file($target)) {
                @copy(APP_DIR . '/seed/' . $name . '.json', $target);
            }
        }
        foreach (['applications', 'leads'] as $name) {
            $target = DATA_DIR . '/' . $name . '.json';
            if (!is_file($target)) {
                @file_put_contents($target, "[]");
            }
        }

        // Sel unique par installation (empreintes visiteurs).
        $settings = Store::read('settings');
        if (empty($settings['security']['salt'])) {
            $settings['security']['salt'] = bin2hex(random_bytes(16));
            Store::write('settings', $settings);
        }

        self::upgrade();
        self::ensureAdmin();
    }

    /** Fichier déposé par FTP pour choisir soi-même ses identifiants. */
    public const FICHIER_DEBLOCAGE = 'NOUVEAU-COMPTE.txt';

    /**
     * Garantit qu'un compte administrateur utilisable existe.
     *
     * Appelée à chaque requête, et non plus seulement à la toute première
     * installation. Auparavant la création n'avait lieu que si
     * `data/settings.json` était absent : supprimer `data/users.json` pour
     * reprendre la main laissait donc le site sans aucun compte, et sans
     * moyen d'en recréer un. C'est précisément le geste que documente la
     * procédure de dépannage.
     */
    public static function ensureAdmin(): void
    {
        // Un fichier déposé par FTP a la priorité : il permet de choisir
        // l'adresse et le mot de passe plutôt que de subir un tirage.
        if (self::appliquerDeblocage()) {
            return;
        }

        $utilisable = false;
        foreach (Store::read('users') as $u) {
            if (($u['active'] ?? true) && ($u['password_hash'] ?? '') !== '') {
                $utilisable = true;
                break;
            }
        }
        if ($utilisable) {
            return;
        }

        // Sans consigne explicite, un mot de passe aléatoire : un mot de
        // passe par défaut connu resterait valable sur toute installation
        // dont l'exploitant n'a pas encore ouvert le back-office.
        $email = getenv('ADMIN_EMAIL') ?: 'admin@suisse-immo.fr';
        $password = getenv('ADMIN_PASSWORD') ?: self::randomPassword();
        Store::write('users', [[
            'id' => Store::uid('usr-'),
            'name' => 'Administrateur',
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
            'active' => true,
            'must_change_password' => true,
            'created_at' => date('c'),
        ]]);
        @file_put_contents(DATA_DIR . '/PREMIERE-CONNEXION.txt',
            "Compte administrateur créé le " . date('d/m/Y H:i') . "\n" .
            "Identifiant : " . $email . "\n" .
            "Mot de passe : " . $password . "\n\n" .
            "Le changement est imposé à la première connexion ; ce fichier est supprimé automatiquement à ce moment-là.\n");
    }

    /**
     * Applique un fichier `data/NOUVEAU-COMPTE.txt` déposé par FTP.
     *
     * Sur un hébergement sans accès SSH et dont la messagerie ne
     * fonctionne pas, c'est le seul moyen de reprendre la main en
     * choisissant ses identifiants. Déposer un fichier dans `data/`
     * suppose déjà un accès complet au serveur : le niveau de confiance
     * est celui du FTP, pas celui du web. Le fichier est traité puis
     * supprimé, et le résultat écrit à sa place.
     *
     * Format attendu (deux lignes) :
     *   email = vous@exemple.fr
     *   motdepasse = votre mot de passe
     *
     * @return bool vrai si un fichier a été traité
     */
    public static function appliquerDeblocage(): bool
    {
        $fichier = DATA_DIR . '/' . self::FICHIER_DEBLOCAGE;
        if (!is_file($fichier)) {
            return false;
        }
        $contenu = (string) @file_get_contents($fichier);
        @unlink($fichier);   // traité une seule fois, quoi qu'il arrive

        $champs = [];
        foreach (preg_split('/\r?\n/', $contenu) ?: [] as $ligne) {
            if (!str_contains($ligne, '=')) {
                continue;
            }
            [$cle, $valeur] = explode('=', $ligne, 2);
            $cle = strtolower(trim(str_replace(['-', '_', ' '], '', $cle)));
            $champs[$cle] = trim($valeur);
        }
        $email = strtolower((string) ($champs['email'] ?? ''));
        $motDePasse = (string) ($champs['motdepasse'] ?? $champs['motdepassse'] ?? '');
        $nom = (string) ($champs['nom'] ?? 'Administrateur');

        $compteRendu = static function (string $texte): void {
            @file_put_contents(DATA_DIR . '/' . self::FICHIER_DEBLOCAGE . '.resultat.txt',
                date('d/m/Y H:i') . "\n" . $texte . "\n");
        };

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $motDePasse === '') {
            $compteRendu(
                "Fichier ignoré : il doit contenir exactement ces deux lignes,\n"
                . "sans guillemets :\n\n"
                . "email = vous@exemple.fr\n"
                . "motdepasse = votre mot de passe\n"
            );
            return false;
        }

        $champsCompte = [
            'password_hash' => password_hash($motDePasse, PASSWORD_DEFAULT),
            'must_change_password' => false,
            'active' => true,
            'password_changed_at' => date('c'),
            'password_faible' => mb_strlen($motDePasse) < 12,
        ];

        $existant = null;
        foreach (Store::read('users') as $u) {
            if (strtolower((string) ($u['email'] ?? '')) === $email) {
                $existant = $u;
                break;
            }
        }
        if ($existant !== null) {
            Store::update('users', (string) ($existant['id'] ?? ''), $champsCompte);
            $action = 'Mot de passe remplacé';
        } else {
            Store::push('users', $champsCompte + ['name' => $nom, 'email' => $email, 'role' => 'admin']);
            $action = 'Compte créé';
        }

        @unlink(DATA_DIR . '/PREMIERE-CONNEXION.txt');
        $compteRendu(
            $action . " : " . $email . "\n"
            . "Vous pouvez vous connecter sur " . url('admin/login') . "\n\n"
            . "Supprimez ce fichier de compte rendu.\n"
            . ($champsCompte['password_faible']
                ? "\nAttention : mot de passe de moins de 12 caractères. Le back-office donne accès\n"
                  . "à des candidatures nominatives — remplacez-le depuis Utilisateurs.\n"
                : '')
        );
        return true;
    }

    /**
     * Complète data/settings.json avec les clés apparues depuis
     * l'installation. Idempotent : n'écrit que s'il manque quelque chose,
     * et ne touche jamais à une valeur déjà renseignée.
     */
    public static function upgrade(): void
    {
        $seedFile = APP_DIR . '/seed/settings.json';
        if (!is_file($seedFile)) {
            return;
        }
        $seed = json_decode((string) file_get_contents($seedFile), true);
        if (!is_array($seed)) {
            return;
        }
        // Reprise du journal d'audience au format tableau (versions < 2026-09).
        Analytics::migrateLegacy();

        $current = Store::read('settings');
        $changed = false;
        foreach ($seed as $group => $values) {
            if (!is_array($values)) {
                if (!array_key_exists($group, $current)) { $current[$group] = $values; $changed = true; }
                continue;
            }
            foreach ($values as $key => $value) {
                if (!array_key_exists($key, $current[$group] ?? [])) {
                    $current[$group][$key] = $value;
                    $changed = true;
                }
            }
        }
        if ($changed) {
            Store::write('settings', $current);
        }
    }

    /**
     * État de l'environnement PHP.
     *
     * Le site fonctionne sans base de données mais s'appuie sur quelques
     * extensions : leur absence se manifeste autrement par une
     * fonctionnalité silencieusement inopérante (extraction d'un .docx,
     * détection du type d'un CV, TLS sortant). Le tableau de bord affiche
     * ce diagnostic.
     *
     * @return array<int,array{cle:string,libelle:string,requis:bool,present:bool,role:string}>
     */
    public static function requirements(): array
    {
        $lignes = [
            ['cle' => 'php', 'libelle' => 'PHP 8.1 ou plus', 'requis' => true,
             'present' => PHP_VERSION_ID >= 80100,
             'role' => 'Version actuelle : ' . PHP_VERSION . '.'],
            ['cle' => 'json', 'libelle' => 'Extension json', 'requis' => true,
             'present' => extension_loaded('json'),
             'role' => 'Lecture et écriture de toutes les données du site.'],
            ['cle' => 'mbstring', 'libelle' => 'Extension mbstring', 'requis' => true,
             'present' => extension_loaded('mbstring'),
             'role' => 'Découpe correcte des textes accentués (titres, extraits, e-mails).'],
            ['cle' => 'fileinfo', 'libelle' => 'Extension fileinfo', 'requis' => false,
             'present' => extension_loaded('fileinfo'),
             'role' => 'Vérifie le type réel des CV déposés ; à défaut, seule la signature du fichier est contrôlée.'],
            ['cle' => 'zip', 'libelle' => 'Extension zip', 'requis' => false,
             'present' => class_exists('ZipArchive'),
             'role' => 'Extraction du texte des documents .docx pour la base de connaissances du bot.'],
            ['cle' => 'zlib', 'libelle' => 'Extension zlib', 'requis' => false,
             'present' => function_exists('gzuncompress'),
             'role' => 'Extraction du texte des PDF pour la base de connaissances du bot.'],
            ['cle' => 'openssl', 'libelle' => 'Extension openssl', 'requis' => false,
             'present' => extension_loaded('openssl'),
             'role' => 'Connexion chiffrée au serveur SMTP et à l’API Gemini.'],
            ['cle' => 'sortie', 'libelle' => 'Requêtes HTTP sortantes', 'requis' => false,
             'present' => function_exists('curl_init') || filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL),
             'role' => 'Nécessaire au bot IA (API Gemini). Sans cela, le reste du site fonctionne normalement.'],
            ['cle' => 'data', 'libelle' => 'Dossier /data accessible en écriture', 'requis' => true,
             'present' => is_dir(DATA_DIR) && is_writable(DATA_DIR),
             'role' => 'Stockage des candidatures, du contenu et des réglages.'],
        ];
        return $lignes;
    }

    /** Manques bloquants ou notables, pour l'alerte du tableau de bord. */
    public static function missingRequirements(): array
    {
        return array_values(array_filter(self::requirements(), static fn ($l) => !$l['present']));
    }

    /** Mot de passe d'installation : 16 caractères tirés au sort. */
    public static function randomPassword(int $length = 16): string
    {
        // Alphabet sans caractères ambigus (0/O, 1/l/I) : le mot de passe est
        // relu dans un fichier texte avant d'être saisi.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#%+=?';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out;
    }
}
