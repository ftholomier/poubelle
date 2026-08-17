<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Réglages techniques du site (SMTP, destinataire des demandes).
 *
 * Stockés dans data/admin/parametres.json : hors racine web, hors git, et
 * jamais exposés par l'éditeur avancé — ce fichier contient des mots de
 * passe.
 */
final class Parametres
{
    private const DEFAUTS = [
        'smtp' => [
            'actif'        => false,
            'hote'         => '',
            'port'         => 587,
            'securite'     => 'tls',      // tls | ssl | aucune
            'identifiant'  => '',
            'mot_de_passe' => '',
            'expediteur'   => '',
            'nom_expediteur' => 'Étang Fourchu',
        ],
        'contact' => [
            'destinataire' => '',
            'copie'        => '',
        ],
    ];

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(private readonly string $fichier)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function tout(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $donnees = [];
        if (is_file($this->fichier)) {
            $brut = json_decode((string) file_get_contents($this->fichier), true);
            if (is_array($brut)) {
                $donnees = $brut;
            }
        }

        // fusion profonde avec les valeurs par défaut
        $resultat = self::DEFAUTS;
        foreach ($donnees as $section => $valeurs) {
            if (isset($resultat[$section]) && is_array($valeurs)) {
                $resultat[$section] = array_merge($resultat[$section], $valeurs);
            }
        }

        return $this->cache = $resultat;
    }

    public function get(string $chemin, mixed $defaut = null): mixed
    {
        $valeur = $this->tout();
        foreach (explode('.', $chemin) as $segment) {
            if (!is_array($valeur) || !array_key_exists($segment, $valeur)) {
                return $defaut;
            }
            $valeur = $valeur[$segment];
        }
        return $valeur;
    }

    /**
     * @param array<string, mixed> $donnees
     */
    public function enregistrer(array $donnees): void
    {
        $dossier = dirname($this->fichier);
        if (!is_dir($dossier)) {
            $ancien = umask(0);
            @mkdir($dossier, Permissions::DOSSIER, true);
            umask($ancien);
            if (!is_dir($dossier)) {
                throw new RuntimeException('Impossible de créer ' . $dossier);
            }
        }

        $json = json_encode(
            $donnees,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $tmp = $this->fichier . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, $this->fichier)) {
            @unlink($tmp);
            throw new RuntimeException('Écriture impossible : parametres.json');
        }
        @chmod($this->fichier, Permissions::SECRET);

        $this->cache = null;
    }

    public function smtpConfigure(): bool
    {
        return (bool) $this->get('smtp.actif')
            && $this->get('smtp.hote') !== ''
            && $this->get('smtp.expediteur') !== '';
    }
}
