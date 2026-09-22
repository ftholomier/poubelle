<?php
declare(strict_types=1);

namespace App\Services\Sources;

use App\Core\Config;
use App\Services\Http;
use App\Storage\Json;

/**
 * France Travail (ex-Pôle emploi), API « Offres d'emploi v2 ».
 *
 * Source officielle, gratuite et libre-service : on crée une application sur
 * francetravail.io, on souscrit à l'API « Offres d'emploi », et on obtient un
 * client_id / client_secret. C'est aujourd'hui la source la plus pertinente
 * pour ce site : elle couvre nativement le domaine « spectacle » du référentiel
 * ROME, ce qu'aucun agrégateur généraliste ne fait aussi proprement.
 *
 * Le jeton OAuth2 (client_credentials) est mis en cache jusqu'à son expiration.
 */
final class FranceTravailSource extends AbstractSource
{
    private const TOKEN_URL = 'https://entreprise.francetravail.fr/connexion/oauth2/access_token?realm=%2Fpartenaire';
    private const SEARCH_URL = 'https://api.francetravail.io/partenaire/offresdemploi/v2/offres/search';
    private const SCOPE = 'api_offresdemploiv2 o2dsoffre';

    /**
     * Domaine « L » du ROME : spectacle, cinéma et audiovisuel.
     * Liste surchargeable par `sources.france_travail.rome` en configuration —
     * un code inconnu ne fait pas échouer l'appel, il ramène juste moins d'offres.
     */
    private const ROME_SPECTACLE = [
        'L1101', 'L1103',                                  // animation, présentation
        'L1201', 'L1202', 'L1203', 'L1204',                // danse, musique, théâtre, cirque
        'L1301', 'L1302', 'L1303', 'L1304',                // mise en scène, production, promotion, réalisation
        'L1501', 'L1502', 'L1503', 'L1504', 'L1505',       // HMC, costume, décor, lumière, image
        'L1506', 'L1507', 'L1508', 'L1509', 'L1510',       // machinerie, montage, son, régie, animation 2D/3D
    ];

    public function name(): string
    {
        return 'France Travail';
    }

    public function key(): string
    {
        return 'france-travail';
    }

    public function isConfigured(): bool
    {
        return Config::has('francetravail_client_id') && Config::has('francetravail_client_secret');
    }

    public function search(array $criteria): array
    {
        $token = $this->token();
        if ($token === '') {
            return $this->fail('jeton OAuth2 indisponible');
        }

        $limit = max(1, min(150, (int) $criteria['limit']));
        $from = max(0, ((int) $criteria['page'] - 1) * $limit);

        $params = [
            'range' => $from . '-' . ($from + $limit - 1),
            'sort'  => 1,   // par date de création décroissante
        ];

        // Le filtre ROME est plus précis qu'une recherche plein texte : on le
        // garde par défaut, et les mots-clés du visiteur viennent l'affiner.
        $rome = (array) $this->setting('france_travail.rome', self::ROME_SPECTACLE);
        if ($rome !== []) {
            $params['codeROME'] = implode(',', array_slice($rome, 0, 20));
        }
        if (trim($criteria['q']) !== '') {
            // L'API limite la longueur des mots-clés : on garde les plus utiles.
            $words = array_slice(preg_split('/[\s,]+/', trim($criteria['q'])) ?: [], 0, 5);
            $params['motsCles'] = mb_substr(implode(',', $words), 0, 200);
        }
        if (trim($criteria['city']) !== '') {
            $params['lieux'] = mb_substr(trim($criteria['city']), 0, 60);
        }

        $response = Http::request('GET', self::SEARCH_URL . '?' . http_build_query($params), [
            'headers' => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
            'timeout' => self::timeout(),
        ]);

        // 204 = aucune offre pour ces critères : ce n'est pas une erreur.
        if ($response['status'] === 204) {
            return [];
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            return $this->fail('recherche refusée', ['status' => $response['status']]);
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            return $this->fail('réponse illisible');
        }

        $out = [];
        foreach ((array) ($data['resultats'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $url = (string) ($row['origineOffre']['urlOrigine'] ?? '');
            if ($url === '' && ($row['id'] ?? '') !== '') {
                $url = 'https://candidat.francetravail.fr/offres/recherche/detail/' . rawurlencode((string) $row['id']);
            }

            $normalized = $this->normalize([
                'title'        => (string) ($row['intitule'] ?? ''),
                'company'      => (string) ($row['entreprise']['nom'] ?? ''),
                'city'         => $this->city((string) ($row['lieuTravail']['libelle'] ?? '')),
                'region'       => '',
                'salary'       => (string) ($row['salaire']['libelle'] ?? ''),
                'contract'     => array_filter([(string) ($row['typeContratLibelle'] ?? '')]),
                'tags'         => array_filter([(string) ($row['romeLibelle'] ?? '')]),
                'excerpt'      => $this->text((string) ($row['description'] ?? '')),
                'published_at' => $this->date($row['dateCreation'] ?? ''),
                'url'          => $url,
                'id'           => (string) ($row['id'] ?? ''),
            ]);
            if ($normalized !== []) {
                $out[] = $normalized;
            }
        }
        return $out;
    }

    /** « 75 - PARIS 11 » -> « Paris 11 ». */
    private function city(string $label): string
    {
        $clean = trim((string) preg_replace('/^\d{2,3}\s*-\s*/', '', $label));
        return $clean === '' ? '' : mb_convert_case(mb_strtolower($clean), MB_CASE_TITLE, 'UTF-8');
    }

    /* ------------------------------------------------------------- OAuth2 */

    private function tokenPath(): string
    {
        return Config::path('data') . '/private/ft-token.json';
    }

    /** Jeton en cache, renouvelé une minute avant expiration. */
    private function token(): string
    {
        $cached = Json::read($this->tokenPath());
        if (($cached['token'] ?? '') !== '' && (int) ($cached['expires_at'] ?? 0) > time() + 60) {
            return (string) $cached['token'];
        }

        $response = Http::request('POST', self::TOKEN_URL, [
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
            'timeout' => self::timeout(),
            'form'    => [
                'grant_type'    => 'client_credentials',
                'client_id'     => (string) Config::secret('francetravail_client_id'),
                'client_secret' => (string) Config::secret('francetravail_client_secret'),
                'scope'         => self::SCOPE,
            ],
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $this->fail('authentification refusée', ['status' => $response['status']]);
            return '';
        }

        $data = json_decode($response['body'], true);
        $token = is_array($data) ? (string) ($data['access_token'] ?? '') : '';
        if ($token === '') {
            return '';
        }

        Json::write($this->tokenPath(), [
            'token'      => $token,
            'expires_at' => time() + max(60, (int) ($data['expires_in'] ?? 1500)),
        ]);
        return $token;
    }
}
