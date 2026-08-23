<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Traduction automatique, sans clé d'API.
 *
 * Elle ne sert qu'une fois, au moment où l'on demande une première version
 * d'une langue : le résultat est enregistré sur disque, relu et corrigé à la
 * main, puis servi depuis les fichiers. Le site public n'appelle jamais ce
 * service — s'il devient indisponible, seul le bouton « Traduire
 * automatiquement » du back-office cesse de fonctionner.
 *
 * Deux services sont tentés dans l'ordre. Le premier est le point d'entrée
 * public de Google Traduction : gratuit et sans inscription, mais non
 * documenté, il peut changer ou limiter le débit sans préavis. C'est
 * précisément pourquoi rien d'autre n'en dépend.
 */
final class TraductionAuto
{
    /** Au-delà, l'adresse d'appel devient trop longue et le service refuse. */
    private const LOT_CARACTERES = 1400;

    /** Pause entre deux lots, pour ne pas se faire couper. */
    private const PAUSE_MS = 350;

    private const DELAI = 15;

    /**
     * Traduit une liste de textes.
     *
     * @param array<string, string> $textes clé => texte français
     * @return array{textes: array<string, string>, echecs: int, service: string}
     */
    public function traduire(array $textes, string $vers, string $depuis = 'fr'): array
    {
        $textes = array_filter($textes, static fn(string $t): bool => trim($t) !== '');
        if ($textes === []) {
            return ['textes' => [], 'echecs' => 0, 'service' => '—'];
        }

        $traduits = [];
        $echecs   = 0;
        $service  = '';

        foreach ($this->lots($textes) as $rang => $lot) {
            if ($rang > 0) {
                usleep(self::PAUSE_MS * 1000);
            }

            try {
                [$resultat, $utilise] = $this->traduireLot(array_values($lot), $vers, $depuis);
                $service = $service ?: $utilise;

                // le service rend les textes dans l'ordre reçu : un décalage
                // attribuerait la traduction d'une phrase à une autre
                if (count($resultat) !== count($lot)) {
                    $echecs += count($lot);
                    continue;
                }
                foreach (array_keys($lot) as $rangCle => $cle) {
                    $traduits[$cle] = $resultat[$rangCle];
                }
            } catch (RuntimeException $e) {
                error_log('Traduction automatique : ' . $e->getMessage());
                $echecs += count($lot);
            }
        }

        return ['textes' => $traduits, 'echecs' => $echecs, 'service' => $service ?: '—'];
    }

    /**
     * Découpe en lots qui tiennent dans une requête.
     *
     * @param array<string, string> $textes
     * @return array<int, array<string, string>>
     */
    private function lots(array $textes): array
    {
        $lots = [];
        $lot = [];
        $taille = 0;

        foreach ($textes as $cle => $texte) {
            $long = mb_strlen($texte);
            if ($lot !== [] && $taille + $long > self::LOT_CARACTERES) {
                $lots[] = $lot;
                $lot = [];
                $taille = 0;
            }
            $lot[$cle] = $texte;
            $taille += $long;
        }
        if ($lot !== []) {
            $lots[] = $lot;
        }

        return $lots;
    }

    /**
     * @param string[] $textes
     * @return array{0: string[], 1: string}
     */
    private function traduireLot(array $textes, string $vers, string $depuis): array
    {
        try {
            return [$this->viaGoogle($textes, $vers, $depuis), 'Google Traduction'];
        } catch (RuntimeException $e) {
            // un service de secours : certains hébergements bloquent l'un ou l'autre
            return [$this->viaMyMemory($textes, $vers, $depuis), 'MyMemory'];
        }
    }

    /**
     * Point d'entrée public de Google Traduction.
     *
     * Les textes d'un lot sont séparés par un repère que le traducteur laisse
     * intact, ce qui permet de n'envoyer qu'une requête et de redécouper la
     * réponse.
     *
     * @param string[] $textes
     * @return string[]
     */
    private function viaGoogle(array $textes, string $vers, string $depuis): array
    {
        $separateur = "\n@@@\n";
        $reponse = $this->appeler(
            'https://translate.googleapis.com/translate_a/single?' . http_build_query([
                'client' => 'gtx',
                'sl'     => $depuis,
                'tl'     => $vers,
                'dt'     => 't',
                'q'      => implode($separateur, $textes),
            ])
        );

        $json = json_decode($reponse, true);
        if (!is_array($json[0] ?? null)) {
            throw new RuntimeException('Réponse inattendue de Google Traduction.');
        }

        $entier = '';
        foreach ($json[0] as $morceau) {
            $entier .= (string) ($morceau[0] ?? '');
        }

        $parties = preg_split('/\s*@@@\s*/u', $entier) ?: [];

        return array_map(trim(...), $parties);
    }

    /**
     * MyMemory : documenté et sans clé, mais un texte par requête.
     *
     * @param string[] $textes
     * @return string[]
     */
    private function viaMyMemory(array $textes, string $vers, string $depuis): array
    {
        $traduits = [];
        foreach ($textes as $texte) {
            $reponse = $this->appeler(
                'https://api.mymemory.translated.net/get?' . http_build_query([
                    'q'        => $texte,
                    'langpair' => $depuis . '|' . $vers,
                ])
            );
            $json = json_decode($reponse, true);
            $traduit = (string) ($json['responseData']['translatedText'] ?? '');
            if ($traduit === '') {
                throw new RuntimeException('Réponse vide de MyMemory.');
            }
            $traduits[] = html_entity_decode($traduit, ENT_QUOTES, 'UTF-8');
            usleep(200_000);
        }

        return $traduits;
    }

    private function appeler(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::DELAI,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; MenuiserieTrehant/1.0)',
            ]);
            $corps = curl_exec($ch);
            $code  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $souci = curl_error($ch);
            curl_close($ch);

            if (!is_string($corps) || $corps === '') {
                throw new RuntimeException($souci !== '' ? $souci : 'Réponse vide (HTTP ' . $code . ').');
            }
            if ($code >= 400) {
                throw new RuntimeException('Le service a répondu HTTP ' . $code . '.');
            }
            return $corps;
        }

        $contexte = stream_context_create(['http' => [
            'timeout' => self::DELAI,
            'header'  => "User-Agent: Mozilla/5.0 (compatible; MenuiserieTrehant/1.0)\r\n",
        ]]);
        $corps = @file_get_contents($url, false, $contexte);
        if (!is_string($corps) || $corps === '') {
            throw new RuntimeException('Service de traduction injoignable depuis le serveur.');
        }

        return $corps;
    }
}
