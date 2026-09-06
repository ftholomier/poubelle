<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Les PDF publiés par la mairie : comptes-rendus, délibérations, budgets.
 *
 * **Pourquoi cette classe existe.** L'aide du back-office disait « Les PDF se
 * déposent par FTP dans public/assets/doc/ ». Publier un compte-rendu de
 * conseil est la seule tâche vraiment mensuelle d'un secrétariat de mairie, et
 * elle demandait un client FTP, une adresse de serveur et un mot de passe que
 * personne n'a sous la main un mardi matin. La conséquence n'était pas un
 * inconfort : c'était un site dont la rubrique la plus consultée cesse d'être
 * tenue à jour.
 *
 * Trois garde-fous, dans cet ordre :
 *
 *   1. **la signature du fichier**, pas son extension ni son type déclaré. Un
 *      navigateur annonce ce qu'on lui dit d'annoncer, et `.pdf` n'est qu'un
 *      suffixe : seuls les cinq premiers octets font foi ;
 *   2. **le nom est réécrit**, jamais repris. Un nom de fichier arrive d'un
 *      poste Windows avec des espaces, des accents et parfois des points en
 *      série — `compte rendu.2025.pdf` — et c'est aussi par là que passe un
 *      `../` ;
 *   3. **l'écriture est atomique** : fichier temporaire puis `rename()`, comme
 *      pour le contenu. Un envoi interrompu laisserait sinon un PDF tronqué,
 *      publié et illisible.
 *
 * Le dossier reçoit en plus son propre `.htaccess` (voir `htaccess()`) : il
 * vit sous `public/`, donc servi, et rien n'y garantissait qu'un fichier
 * déposé ne serait pas exécuté.
 */
final class Documents
{
    /** Même plafond que les photos : au-delà, l'hébergement refuse de toute façon. */
    public const MAX_OCTETS = 15728640;   // 15 Mio

    /** Les cinq octets qui font un PDF. Le reste n'est qu'une promesse. */
    private const SIGNATURE = '%PDF-';

    public function __construct(
        private readonly string $dossier,
        private readonly string $prefixe = 'assets/doc',
    ) {
    }

    /**
     * Reçoit un PDF et rend son chemin relatif, prêt pour le contenu.
     *
     * @param array{name?:string, tmp_name?:string, size?:int, error?:int} $fichier
     */
    public function televerser(array $fichier): string
    {
        $erreur = $fichier['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($erreur !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($erreur) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Document trop lourd pour le serveur.',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier sélectionné.',
                default => 'Envoi interrompu.',
            });
        }
        if (($fichier['size'] ?? 0) > self::MAX_OCTETS) {
            throw new RuntimeException('Document trop lourd (15 Mo maximum).');
        }

        $temporaire = (string) ($fichier['tmp_name'] ?? '');
        if ($temporaire === '' || !is_uploaded_file($temporaire)) {
            throw new RuntimeException('Envoi interrompu.');
        }

        /* La signature d'abord. Le type MIME déclaré vient du navigateur, donc
           du visiteur ; l'extension vient du nom, donc du visiteur aussi. Les
           cinq premiers octets, eux, viennent du fichier. */
        $tete = (string) file_get_contents($temporaire, false, null, 0, 5);
        if ($tete !== self::SIGNATURE) {
            throw new RuntimeException('Ce fichier n’est pas un PDF. '
                . 'Un document scanné doit être enregistré au format PDF avant d’être déposé.');
        }

        $this->preparerDossier();

        $nom = $this->nomDisponible((string) ($fichier['name'] ?? 'document'));
        $cible = $this->dossier . '/' . $nom;

        /* move_uploaded_file() vers un nom temporaire, puis rename() : le
           fichier n'apparaît sous son nom définitif qu'une fois complet. Un
           envoi coupé au milieu laisserait sinon un PDF tronqué en ligne, que
           la mairie croirait publié. */
        $enCours = $cible . '.' . bin2hex(random_bytes(6)) . '.partiel';
        if (!move_uploaded_file($temporaire, $enCours)) {
            throw new RuntimeException('Le document n’a pas pu être enregistré. '
                . 'Vérifiez les droits du dossier assets/doc.');
        }
        if (!rename($enCours, $cible)) {
            @unlink($enCours);
            throw new RuntimeException('Le document n’a pas pu être enregistré.');
        }
        @chmod($cible, Permissions::FICHIER);

        return $this->prefixe . '/' . $nom;
    }

    /** Les PDF publiés, du plus récent au plus ancien. @return string[] */
    public function lister(): array
    {
        $fichiers = is_dir($this->dossier) ? (glob($this->dossier . '/*.pdf') ?: []) : [];
        usort($fichiers, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return array_map(static fn(string $f): string => basename($f), $fichiers);
    }

    /** Le document existe-t-il vraiment ici ? Contrôle avant toute suppression. */
    public function existe(string $nom): bool
    {
        $nom = basename($nom);

        return $nom !== '' && str_ends_with(strtolower($nom), '.pdf')
            && is_file($this->dossier . '/' . $nom);
    }

    public function supprimer(string $nom): void
    {
        if (!$this->existe($nom)) {
            throw new RuntimeException('Ce document n’existe pas.');
        }
        if (!@unlink($this->dossier . '/' . basename($nom))) {
            throw new RuntimeException('Le document n’a pas pu être supprimé.');
        }
    }

    /**
     * Un nom réécrit, et jamais celui reçu.
     *
     * Ce que le navigateur envoie vient d'un poste de la mairie : accents,
     * espaces, apostrophes, parfois deux extensions. On en garde le sens et on
     * jette la forme. Un homonyme reçoit un suffixe plutôt que d'écraser :
     * deux « compte-rendu.pdf » déposés à un mois d'intervalle sont deux
     * documents différents, et l'un d'eux n'a pas à disparaître.
     */
    private function nomDisponible(string $nomOrigine): string
    {
        $base = pathinfo($nomOrigine, PATHINFO_FILENAME);
        $base = Seo::normaliser($base) ?: 'document';
        $base = mb_substr($base, 0, 80);

        $nom = $base . '.pdf';
        $n = 1;
        while (is_file($this->dossier . '/' . $nom)) {
            $nom = $base . '-' . (++$n) . '.pdf';
        }

        return $nom;
    }

    private function preparerDossier(): void
    {
        if (!is_dir($this->dossier)) {
            $ancien = umask(0);
            @mkdir($this->dossier, Permissions::DOSSIER, true);
            umask($ancien);
        }
        if (!is_dir($this->dossier) || !is_writable($this->dossier)) {
            throw new RuntimeException('Le dossier assets/doc n’est pas accessible en écriture. '
                . 'Voir Paramètres → Droits d’accès.');
        }

        $htaccess = $this->dossier . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, self::htaccess());
            @chmod($htaccess, Permissions::FICHIER);
        }
    }

    /**
     * La configuration Apache du dossier des documents.
     *
     * Ce dossier vit sous `public/`, donc servi, et il reçoit désormais des
     * fichiers envoyés depuis un navigateur. Deux précautions :
     *
     *   · **aucune exécution.** Un `.php` déposé sous un faux nom serait sinon
     *     exécuté par le serveur. La signature `%PDF-` l'interdit déjà à
     *     l'entrée ; celle-ci le rattrape si un fichier arrive autrement, par
     *     FTP par exemple ;
     *   · **le PDF se télécharge, il ne s'ouvre pas dans la page.** Un PDF
     *     affiché en ligne s'exécute dans l'origine du site, et un PDF sait
     *     porter du JavaScript.
     */
    public static function htaccess(): string
    {
        return <<<'CONF'
        # Les PDF publiés par la mairie, déposés depuis le back-office.
        # Rien ici ne doit être exécuté : ce dossier reçoit des fichiers
        # envoyés depuis un navigateur.
        <IfModule mod_php.c>
            php_flag engine off
        </IfModule>
        RemoveHandler .php .phtml .phar .cgi .pl .py
        RemoveType .php .phtml .phar

        # Un PDF se télécharge plutôt qu'il ne s'ouvre dans la page : affiché
        # en ligne, il s'exécute dans l'origine du site, et un PDF sait porter
        # du JavaScript.
        <FilesMatch "\.pdf$">
            <IfModule mod_headers.c>
                Header set Content-Disposition "attachment"
                Header set X-Content-Type-Options "nosniff"
            </IfModule>
        </FilesMatch>

        Options -Indexes -ExecCGI

        CONF;
    }
}
