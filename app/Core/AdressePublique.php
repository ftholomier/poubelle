<?php
declare(strict_types=1);

namespace App\Core;

/**
 * L'adresse à laquelle le site répond, telle que le site doit l'écrire.
 *
 * **Pourquoi ce réglage existe.** Faute de le connaître, le site déduisait son
 * adresse de l'en-tête `Host` de la requête. Or cet en-tête est écrit par le
 * client, pas par le serveur : `curl -H 'Host: pirate.example'` suffisait à
 * faire produire au site des adresses absolues sur ce domaine. Ce qui en
 * dépend n'est pas anodin :
 *
 *   · `<link rel="canonical">` et `og:url`, que les moteurs et les réseaux
 *     recopient ;
 *   · le plan du site, que Google relit ;
 *   · les liens des courriels envoyés au secrétariat et aux administrés —
 *     un message signé de la mairie renvoyant ailleurs ;
 *   · l'URI de retour OAuth affichée dans l'écran Réseaux, que la mairie
 *     recopie telle quelle chez Meta.
 *
 * L'en-tête reste le dernier recours : sans lui, un site fraîchement installé
 * ne saurait rien dire de lui-même. Mais il est signalé — une fois par jour,
 * dans le journal d'erreurs de PHP, donc relevé par `outils/verifs/alertes.py`
 * — et le tableau de bord le porte dans « À traiter » tant que le réglage est
 * vide. Un défaut silencieux devient un défaut visible.
 *
 * Ordre de préférence :
 *   1. `config/config.php` → `app.base_url`, quand un développeur l'a fixée ;
 *   2. le réglage de la mairie, saisi dans Paramètres ;
 *   3. l'en-tête `Host`, à défaut.
 */
final class AdressePublique
{
    public const CLE = 'site.adresse_publique';

    /** Un rappel par jour suffit : le journal doit rester lisible. */
    private const DELAI_RAPPEL = 86400;

    /**
     * Ramène une saisie à une origine seule, ou à la chaîne vide.
     *
     * Le chemin, la requête et le fragment sont retirés : `base_absolue()` y
     * ajoute déjà le sous-répertoire d'installation, et une adresse réglée
     * avec un `/` final produisait des `//` dans les liens des courriels.
     */
    public static function normaliser(string $brut): string
    {
        $brut = trim($brut);
        if ($brut === '') {
            return '';
        }
        // Une mairie saisit « angeot.fr » aussi souvent que l'adresse complète.
        if (!preg_match('~^https?://~i', $brut)) {
            $brut = 'https://' . ltrim($brut, '/');
        }

        $parties = parse_url($brut);
        if ($parties === false || !isset($parties['host'])) {
            return '';
        }
        $hote = strtolower($parties['host']);
        // Un nom de domaine, ou une machine locale pour le développement.
        if (preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $hote) !== 1) {
            return '';
        }
        if (!str_contains($hote, '.') && $hote !== 'localhost') {
            return '';
        }

        $schema = strtolower($parties['scheme'] ?? 'https');
        $port   = isset($parties['port']) ? ':' . (int) $parties['port'] : '';

        return $schema . '://' . $hote . $port;
    }

    /**
     * Pose l'adresse retenue dans la configuration lue par origine().
     *
     * Passer par la configuration plutôt que par un appel direct garde les
     * aides de vue (`origine()`, `base_absolue()`, `absolu()`) ignorantes des
     * réglages : elles ne connaissent qu'une clé, et le choix se fait ici.
     */
    public static function appliquer(Parametres $parametres, ?string $fichierRappel = null): void
    {
        $dejaFixee = (string) ($GLOBALS['config']['app']['base_url'] ?? '');
        if (str_starts_with($dejaFixee, 'http')) {
            return;
        }

        $reglee = self::normaliser((string) $parametres->get(self::CLE, ''));
        if ($reglee !== '') {
            $GLOBALS['config']['app']['base_url'] = $reglee;
            return;
        }

        if ($fichierRappel !== null) {
            self::rappeler($fichierRappel);
        }
    }

    /** Le réglage est-il renseigné ? Le tableau de bord le demande. */
    public static function renseignee(Parametres $parametres): bool
    {
        return self::normaliser((string) $parametres->get(self::CLE, '')) !== ''
            || str_starts_with((string) ($GLOBALS['config']['app']['base_url'] ?? ''), 'http');
    }

    /**
     * Une ligne par jour dans le journal de PHP, pas une par requête.
     *
     * Le fichier témoin vit dans le cache : le perdre ne coûte qu'un rappel
     * de plus, et l'écriture ne doit jamais faire échouer une page.
     */
    private static function rappeler(string $fichierRappel): void
    {
        $dernier = @filemtime($fichierRappel);
        if ($dernier !== false && time() - $dernier < self::DELAI_RAPPEL) {
            return;
        }

        $dossier = dirname($fichierRappel);
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0755, true);
        }
        @touch($fichierRappel);

        error_log(
            'Adresse publique du site non renseignée : les adresses absolues'
            . ' (canonical, og:url, plan du site, liens des courriels) suivent'
            . ' l’en-tête Host de la requête, qui est écrit par le client.'
            . ' À renseigner dans Paramètres → Adresse publique du site.'
        );
    }
}
