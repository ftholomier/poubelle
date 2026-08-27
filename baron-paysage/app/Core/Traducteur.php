<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Traductions du contenu, mémorisées sur disque.
 *
 * Le français reste la seule version éditée ; chaque autre langue est un
 * fichier de correspondances « chemin dans le contenu » => « texte traduit ».
 * Les pages traduites sont donc de vraies pages, servies depuis le disque :
 * aucun service extérieur n'intervient à l'affichage, et le site continue de
 * fonctionner même si le traducteur automatique devient indisponible.
 *
 * Ce qui n'est pas traduit retombe sur le français, ce qui évite les trous :
 * une phrase ajoutée en français s'affiche en français tant que personne ne
 * l'a traduite, plutôt que de disparaître.
 */
final class Traducteur
{
    /** Clés dont la valeur n'est jamais du texte à traduire. */
    private const IGNOREES = [
        'slug', 'image', 'images', 'images_avant', 'icone', 'url', 'src',
        'actif', 'categorie', 'cat', 'prix_a_partir_de', 'capacite', 'pause',
    ];

    /** @var array<string, array<string, string>> par code de langue */
    private array $cache = [];

    public function __construct(private readonly string $dossier)
    {
    }

    private function fichier(string $langue): string
    {
        return $this->dossier . '/' . $langue . '.json';
    }

    /**
     * @return array<string, string>
     */
    public function tout(string $langue): array
    {
        if (isset($this->cache[$langue])) {
            return $this->cache[$langue];
        }

        $textes = [];
        $fichier = $this->fichier($langue);
        if (is_file($fichier)) {
            $lu = json_decode((string) file_get_contents($fichier), true);
            if (is_array($lu['textes'] ?? null)) {
                $textes = array_map(strval(...), $lu['textes']);
            }
        }

        return $this->cache[$langue] = $textes;
    }

    public function texte(string $langue, string $cle, string $defaut): string
    {
        $traduit = $this->tout($langue)[$cle] ?? '';
        return $traduit !== '' ? $traduit : $defaut;
    }

    /**
     * Applique les traductions à un arbre de contenu.
     *
     * @param array<mixed> $donnees
     * @return array<mixed>
     */
    public function appliquer(array $donnees, string $langue, string $prefixe): array
    {
        $textes = $this->tout($langue);
        if ($textes === []) {
            return $donnees;
        }

        return self::parcourir($donnees, $prefixe, static function (string $cle, string $valeur) use ($textes): string {
            return ($textes[$cle] ?? '') !== '' ? $textes[$cle] : $valeur;
        });
    }

    /**
     * Relève tous les textes traduisibles d'un arbre de contenu.
     *
     * @param array<mixed> $donnees
     * @return array<string, string> chemin => texte français
     */
    public static function extraire(array $donnees, string $prefixe): array
    {
        $releve = [];
        self::parcourir($donnees, $prefixe, static function (string $cle, string $valeur) use (&$releve): string {
            $releve[$cle] = $valeur;
            return $valeur;
        });

        return $releve;
    }

    /**
     * Parcourt les feuilles textuelles d'un arbre en construisant leur chemin.
     *
     * Les entrées d'une collection sont repérées par leur slug plutôt que par
     * leur rang : réordonner les hébergements ne doit pas décaler toutes les
     * traductions d'un cran.
     *
     * @param array<mixed> $donnees
     * @param callable(string, string): string $sur
     * @return array<mixed>
     */
    private static function parcourir(array $donnees, string $chemin, callable $sur): array
    {
        foreach ($donnees as $cle => $valeur) {
            $nom = is_int($cle) ? (string) $cle : $cle;

            // Une clé technique écarte toute sa descendance, pas seulement sa
            // valeur immédiate : « images_avant » est une liste, et ses
            // éléments ont des indices numériques. Sans cette coupure, les
            // chemins de photos partaient à la traduction — et revenaient
            // abîmés, « assets/img/… » devenant « asset/img/… » en allemand.
            if (is_string($cle) && in_array($cle, self::IGNOREES, true)) {
                continue;
            }

            if (is_array($valeur)) {
                $repere = is_int($cle) && isset($valeur['slug']) && is_string($valeur['slug'])
                    ? $valeur['slug']
                    : $nom;
                $donnees[$cle] = self::parcourir($valeur, $chemin . '.' . $repere, $sur);
                continue;
            }

            if (!is_string($valeur) || !self::traduisible($valeur)) {
                continue;
            }

            $donnees[$cle] = $sur($chemin . '.' . $nom, $valeur);
        }

        return $donnees;
    }

    /**
     * Cette chaîne se traduit-elle ?
     *
     * Second garde-fou, sur la valeur cette fois : une clé technique que
     * personne n'aurait pensé à déclarer laisserait sinon passer un chemin de
     * fichier ou une adresse, que le traducteur abîmerait sans prévenir.
     */
    private static function traduisible(string $valeur): bool
    {
        $valeur = trim($valeur);

        if ($valeur === '' || preg_match('/\p{L}{2,}/u', $valeur) !== 1) {
            return false;   // vide, ou sans le moindre mot
        }

        // chemin de fichier, adresse web, e-mail, code couleur
        return preg_match(
            '#^(?:[a-z]+://|/|\.{1,2}/|assets/|data/|public/|\#[0-9a-f]{3,8}$)#i',
            $valeur
        ) !== 1
            && preg_match('/\.(?:jpe?g|png|webp|gif|svg|ico|pdf|css|js|json|woff2?)$/i', $valeur) !== 1
            && filter_var($valeur, FILTER_VALIDATE_EMAIL) === false;
    }

    /**
     * Relève les textes d'interface des gabarits, en cherchant les appels à
     * t('…').
     *
     * Les mots des gabarits ne sont écrits nulle part ailleurs : les lire
     * dans le code évite d'entretenir une liste en double, qui finirait par
     * diverger dès qu'on ajoute une phrase.
     *
     * @return array<string, string> clé « interface.X » => texte français
     */
    public static function interface(string $dossierGabarits): array
    {
        $textes = [];

        $fichiers = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dossierGabarits, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($fichiers as $fichier) {
            if (!$fichier->isFile() || $fichier->getExtension() !== 'php') {
                continue;
            }
            // le back-office reste en français : il n'a pas à être traduit
            if (str_contains(str_replace('\\', '/', $fichier->getPathname()), '/admin/')) {
                continue;
            }

            $code = (string) file_get_contents($fichier->getPathname());
            if (preg_match_all("/\bt\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*\)/", $code, $trouves) === false) {
                continue;
            }
            foreach ($trouves[1] as $brut) {
                $texte = stripslashes($brut);
                if (trim($texte) !== '') {
                    $textes['interface.' . $texte] = $texte;
                }
            }
        }

        ksort($textes);

        return $textes;
    }

    // -------------------------------------------------------------- écriture

    /**
     * @param array<string, string> $textes chemin => traduction
     */
    public function enregistrer(string $langue, array $textes): void
    {
        if (!is_dir($this->dossier)) {
            $ancien = umask(0);
            @mkdir($this->dossier, Permissions::DOSSIER, true);
            umask($ancien);
        }

        $propres = [];
        foreach ($textes as $cle => $valeur) {
            $valeur = trim((string) $valeur);
            if ($valeur !== '') {
                $propres[$cle] = $valeur;
            }
        }
        ksort($propres);

        $json = json_encode(
            ['langue' => $langue, 'textes' => $propres],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $fichier = $this->fichier($langue);
        $tmp = $fichier . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, $fichier)) {
            @unlink($tmp);
            throw new RuntimeException('Écriture impossible : traductions/' . $langue . '.json');
        }
        @chmod($fichier, Permissions::FICHIER);

        unset($this->cache[$langue]);
    }

    /**
     * Ajoute ou remplace des traductions sans toucher aux autres.
     *
     * @param array<string, string> $textes
     */
    public function fusionner(string $langue, array $textes): void
    {
        $this->enregistrer($langue, array_merge($this->tout($langue), $textes));
    }

    public function oublier(string $langue): void
    {
        @unlink($this->fichier($langue));
        unset($this->cache[$langue]);
    }
}
