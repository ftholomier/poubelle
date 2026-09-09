<?php
declare(strict_types=1);

namespace App\Ai;

use App\Config;
use App\Content;
use App\I18n;
use App\Offices;
use App\Router;

/**
 * Les chiffres réels du site, calculés à la question.
 *
 * L'index documentaire décrit le site en mots ; cette classe le décrit en
 * données : combien de bureaux sont libres à l'instant, à quel prix, où, et
 * vers quelle page envoyer le visiteur. Ces valeurs sont injectées dans le
 * prompt de Gemini (qui n'a donc jamais à deviner un tarif) et servent aussi
 * de réponse directe quand aucune clé API n'est configurée.
 */
final class Facts
{
    /** Intentions repérées dans la question, FR et EN. */
    private const INTENTS = [
        'availability' => ['dispo', 'disponible', 'disponibles', 'disponibilite', 'libre', 'libres', 'reste', 'restant',
            'place', 'places', 'vacant', 'available', 'availability', 'free', 'vacancy', 'left'],
        'price' => ['prix', 'tarif', 'tarifs', 'combien', 'cout', 'coute', 'loyer', 'mensuel', 'budget', 'cher',
            'price', 'prices', 'cost', 'rate', 'rates', 'monthly', 'much'],
        'location' => ['adresse', 'situe', 'situee', 'trouve', 'localisation', 'venir', 'acces', 'acceder', 'plan',
            'carte', 'parking', 'tram', 'bus', 'gare', 'quartier', 'centre', 'ville', 'besancon',
            'where', 'address', 'located', 'location', 'access', 'directions', 'station', 'map'],
        'visit' => ['visite', 'visiter', 'rdv', 'rendez', 'reserver', 'reservation', 'essayer', 'essai',
            'visit', 'viewing', 'book', 'booking', 'tour', 'try'],
        'included' => ['compris', 'inclus', 'charge', 'charges', 'internet', 'fibre', 'wifi', 'menage', 'cafe',
            'equipement', 'equipements', 'service', 'services', 'included', 'includes', 'cleaning', 'coffee'],
        'contract' => ['contrat', 'engagement', 'bail', 'preavis', 'duree', 'resiliation', 'partir',
            'contract', 'commitment', 'lease', 'notice', 'term'],
        'meeting' => ['reunion', 'meeting', 'salle', 'client', 'clients', 'visio', 'room'],
    ];

    /** Lieux reconnus dans la question. */
    private const SITE_WORDS = ['carnot' => 'carnot', 'granvelle' => 'granvelle'];

    /** Types reconnus dans la question. */
    private const TYPE_WORDS = [
        'private' => ['prive', 'privee', 'prives', 'individuel', 'ferme', 'private', 'individual'],
        'openspace' => ['openspace', 'open', 'space', 'partage', 'coworking', 'poste', 'postes', 'desk', 'shared'],
    ];

    /**
     * Instantané du catalogue, prêt à être lu par un humain ou par le modèle.
     *
     * @return array<string,mixed>
     */
    public static function snapshot(string $lang): array
    {
        $published = Offices::published();
        $available = Offices::filter($published, ['status' => 'available']);
        $soon = Offices::filter($published, ['status' => 'soon']);

        $sites = [];
        foreach ((array) (Content::settings()['sites'] ?? []) as $site) {
            if (!\is_array($site) || ($site['enabled'] ?? true) === false) {
                continue;
            }
            $id = (string) ($site['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $atSite = Offices::filter($published, ['site' => $id]);
            $sites[$id] = [
                'id' => $id,
                'name' => (string) ($site['shortName'] ?? $site['name'] ?? ucfirst($id)),
                'address' => (string) ($site['address'] ?? ''),
                'note' => Content::i18n($site, 'note', $lang),
                'total' => \count($atSite),
                'available' => \count(Offices::filter($atSite, ['status' => 'available'])),
                'minPrice' => self::minOf($atSite),
                'url' => Router::officesAtSite($id, $lang),
            ];
        }

        return [
            'total' => \count($published),
            'available' => \count($available),
            'soon' => \count($soon),
            'availableList' => self::listOf($available, $lang),
            'soonList' => self::listOf($soon, $lang),
            'byType' => [
                'private' => [
                    'total' => \count(Offices::filter($published, ['type' => 'private'])),
                    'available' => \count(Offices::filter($available, ['type' => 'private'])),
                    'minPrice' => self::minOf(Offices::filter($published, ['type' => 'private'])),
                    'maxPrice' => self::maxOf(Offices::filter($published, ['type' => 'private'])),
                ],
                'openspace' => [
                    'total' => \count(Offices::filter($published, ['type' => 'openspace'])),
                    'available' => \count(Offices::filter($available, ['type' => 'openspace'])),
                    'minPrice' => self::minOf(Offices::filter($published, ['type' => 'openspace'])),
                    'maxPrice' => self::maxOf(Offices::filter($published, ['type' => 'openspace'])),
                ],
            ],
            'sites' => $sites,
            'minPrice' => self::minOf($published),
            'urls' => [
                'available' => Router::availableOffices($lang),
                'offices' => Router::url('offices', $lang),
                'spaces' => Router::url('spaces', $lang),
                'contact' => Router::url('contact', $lang),
            ],
        ];
    }

    /**
     * Bloc de données injecté dans le prompt : le modèle dispose des chiffres
     * exacts et n'a plus aucune raison d'en inventer un.
     */
    public static function brief(string $lang): string
    {
        $s = self::snapshot($lang);
        $lines = [];
        $lines[] = 'Bureaux publiés : ' . $s['total'] . '. Libres maintenant : ' . $s['available']
            . '. Bientôt libres : ' . $s['soon'] . '.';

        foreach ($s['sites'] as $site) {
            $lines[] = $site['name'] . ' — ' . $site['address'] . ' : ' . $site['total'] . ' bureaux, '
                . $site['available'] . ' libre(s)'
                . ($site['minPrice'] !== null ? ', à partir de ' . I18n::price($site['minPrice']) . ' HT/mois' : '')
                . '. Page filtrée : ' . $site['url'];
        }

        foreach (['private' => 'Bureaux privés', 'openspace' => 'Postes en open space'] as $type => $label) {
            $t = $s['byType'][$type];
            $range = $t['minPrice'] === null ? 'tarif sur demande'
                : ($t['minPrice'] === $t['maxPrice']
                    ? I18n::price($t['minPrice']) . ' HT/mois'
                    : 'de ' . I18n::price($t['minPrice']) . ' à ' . I18n::price($t['maxPrice']) . ' HT/mois');
            $lines[] = $label . ' : ' . $t['total'] . ' au total, ' . $t['available'] . ' libre(s), ' . $range . '.';
        }

        if ($s['availableList'] !== []) {
            $lines[] = 'Liste exacte des bureaux libres :';
            foreach ($s['availableList'] as $office) {
                $lines[] = '- ' . $office['name'] . ' (' . $office['siteLabel'] . ', ' . $office['typeLabel'] . ') — '
                    . $office['price'] . ' — ' . $office['url'];
            }
        } else {
            $lines[] = 'Aucun bureau libre à cet instant : proposer de laisser ses coordonnées pour être prévenu.';
        }

        $lines[] = 'Page de tous les bureaux libres : ' . $s['urls']['available'];
        $lines[] = 'Page contact / demande de visite : ' . $s['urls']['contact'];

        return implode("\n", $lines);
    }

    /**
     * Réponse construite à partir des seules données du catalogue, avec les
     * boutons qui vont avec. Renvoie null si la question ne porte pas sur des
     * chiffres — l'index documentaire reprend alors la main.
     *
     * @param array<int,array{role:string,text:string}> $history échanges précédents
     * @return array{answer:string,actions:array<int,array{label:string,url:string}>}|null
     */
    public static function answer(string $question, string $lang, array $history = []): ?array
    {
        $tokens = Indexer::tokenize($question);
        if ($tokens === []) {
            return null;
        }
        $intents = self::intentsOf($tokens, $history);
        if ($intents === []) {
            return null;
        }

        $s = self::snapshot($lang);
        $site = self::siteOf($tokens);
        $type = self::typeOf($tokens);

        if (\in_array('availability', $intents, true)) {
            return self::availabilityAnswer($s, $lang, $site, $type);
        }
        if (\in_array('price', $intents, true)) {
            return self::priceAnswer($s, $lang, $site, $type);
        }
        if (\in_array('location', $intents, true)) {
            return self::locationAnswer($s, $lang);
        }
        if (\in_array('visit', $intents, true)) {
            return self::visitAnswer($s, $lang);
        }
        return null;
    }

    /** Boutons proposés sous une réponse, quelle qu'en soit l'origine. */
    public static function actionsFor(string $question, string $lang, array $history = []): array
    {
        $tokens = Indexer::tokenize($question);
        $intents = self::intentsOf($tokens, $history);
        $s = self::snapshot($lang);
        $site = self::siteOf($tokens);

        $actions = [];
        if ($s['available'] > 0 && array_intersect($intents, ['availability', 'price', 'visit', 'meeting']) !== []) {
            $actions[] = $site !== '' && isset($s['sites'][$site])
                ? ['label' => I18n::t('bot.actionSite', ['name' => $s['sites'][$site]['name']]), 'url' => $s['sites'][$site]['url']]
                : ['label' => self::availableLabel($s['available']), 'url' => $s['urls']['available']];
        }
        if (\in_array('location', $intents, true) || \in_array('visit', $intents, true)) {
            $actions[] = ['label' => I18n::t('bot.actionVisit'), 'url' => $s['urls']['contact']];
        }
        if (\in_array('included', $intents, true) || \in_array('meeting', $intents, true)) {
            $actions[] = ['label' => I18n::t('bot.actionSpaces'), 'url' => $s['urls']['spaces']];
        }
        if ($actions === [] && $intents !== []) {
            $actions[] = ['label' => I18n::t('bot.actionOffices'), 'url' => $s['urls']['offices']];
        }
        return array_values($actions);
    }

    // ------------------------------------------------------------ réponses

    private static function availabilityAnswer(array $s, string $lang, string $site, string $type): array
    {
        $scope = $s['availableList'];
        if ($site !== '') {
            $scope = array_values(array_filter($scope, static fn (array $o): bool => $o['site'] === $site));
        }
        if ($type !== '') {
            $scope = array_values(array_filter($scope, static fn (array $o): bool => $o['type'] === $type));
        }
        $count = \count($scope);

        if ($count === 0) {
            $answer = I18n::t('bot.dataNone');
            if ($s['soon'] > 0) {
                $answer .= ' ' . I18n::t('bot.dataSoon', ['count' => $s['soon']]);
            }
            return ['answer' => $answer, 'actions' => [
                ['label' => I18n::t('bot.actionNotify'), 'url' => $s['urls']['contact']],
                ['label' => I18n::t('bot.actionOffices'), 'url' => $s['urls']['offices']],
            ]];
        }

        $details = [];
        foreach (\array_slice($scope, 0, 4) as $office) {
            $details[] = $office['name'] . ' (' . $office['siteLabel'] . ', ' . $office['price'] . ')';
        }
        $answer = I18n::t($count === 1 ? 'bot.dataOne' : 'bot.dataMany', ['count' => $count])
            . ' ' . implode(' · ', $details) . '.';

        // Une question ciblée sur un lieu renvoie vers les bureaux de ce lieu ;
        // le libellé doit dire cela, pas « les bureaux libres ».
        $actions = [$site !== '' && isset($s['sites'][$site])
            ? ['label' => I18n::t('bot.actionSite', ['name' => $s['sites'][$site]['name']]), 'url' => $s['sites'][$site]['url']]
            : ['label' => self::availableLabel($count), 'url' => $s['urls']['available']]];
        if ($count === 1) {
            $actions[] = ['label' => I18n::t('bot.actionOffice', ['name' => $scope[0]['name']]), 'url' => $scope[0]['url']];
        }
        return ['answer' => $answer, 'actions' => $actions];
    }

    private static function priceAnswer(array $s, string $lang, string $site, string $type): array
    {
        $parts = [];
        foreach (['private' => 'bot.dataPricePrivate', 'openspace' => 'bot.dataPriceOpen'] as $key => $label) {
            if ($type !== '' && $type !== $key) {
                continue;
            }
            $t = $s['byType'][$key];
            if ($t['total'] === 0 || $t['minPrice'] === null) {
                continue;
            }
            $parts[] = I18n::t($label, [
                'range' => $t['minPrice'] === $t['maxPrice']
                    ? I18n::price($t['minPrice'])
                    : I18n::t('bot.dataRange', ['min' => I18n::price($t['minPrice']), 'max' => I18n::price($t['maxPrice'])]),
            ]);
        }
        if ($parts === []) {
            return ['answer' => I18n::t('bot.dataPriceNone'), 'actions' => [
                ['label' => I18n::t('bot.actionOffices'), 'url' => $s['urls']['offices']],
            ]];
        }

        $answer = implode(' ', $parts) . ' ' . I18n::t('bot.dataAllIn');
        $actions = [];
        if ($s['available'] > 0) {
            $actions[] = ['label' => self::availableLabel($s['available']), 'url' => $s['urls']['available']];
        }
        $actions[] = ['label' => I18n::t('bot.actionOffices'), 'url' => $s['urls']['offices']];
        return ['answer' => $answer, 'actions' => $actions];
    }

    private static function locationAnswer(array $s, string $lang): array
    {
        // Le français met une espace avant les deux-points, pas l'anglais.
        $colon = $lang === Config::DEFAULT_LANG ? ' : ' : ': ';
        $parts = [];
        foreach ($s['sites'] as $site) {
            $parts[] = $site['name'] . $colon . rtrim($site['address'], '.')
                . ($site['note'] !== '' ? ' (' . rtrim($site['note'], '.') . ')' : '') . '.';
        }
        return [
            'answer' => implode(' ', $parts),
            'actions' => [
                ['label' => I18n::t('bot.actionSpaces'), 'url' => $s['urls']['spaces']],
                ['label' => I18n::t('bot.actionVisit'), 'url' => $s['urls']['contact']],
            ],
        ];
    }

    private static function visitAnswer(array $s, string $lang): array
    {
        $answer = I18n::t('bot.dataVisit');
        $actions = [['label' => I18n::t('bot.actionVisit'), 'url' => $s['urls']['contact']]];
        if ($s['available'] > 0) {
            $actions[] = ['label' => self::availableLabel($s['available']), 'url' => $s['urls']['available']];
        }
        return ['answer' => $answer, 'actions' => $actions];
    }

    private static function availableLabel(int $count): string
    {
        return I18n::t($count === 1 ? 'bot.actionAvailableOne' : 'bot.actionAvailable', ['count' => $count]);
    }

    // ------------------------------------------------------------ utilitaires

    /** @return array<int,array<string,string>> */
    private static function listOf(array $offices, string $lang): array
    {
        $out = [];
        foreach (Offices::decorateAll($offices, $lang) as $office) {
            $out[] = [
                'id' => (string) $office['id'],
                'name' => (string) $office['name'],
                'site' => (string) $office['site'],
                'siteLabel' => (string) $office['siteLabel'],
                'type' => (string) $office['type'],
                'typeLabel' => (string) $office['typeLabel'],
                'price' => (string) $office['priceLabel'],
                'url' => (string) $office['url'],
            ];
        }
        return $out;
    }

    private static function minOf(array $offices): ?int
    {
        $prices = self::pricesOf($offices);
        return $prices === [] ? null : min($prices);
    }

    private static function maxOf(array $offices): ?int
    {
        $prices = self::pricesOf($offices);
        return $prices === [] ? null : max($prices);
    }

    private static function pricesOf(array $offices): array
    {
        $prices = [];
        foreach ($offices as $office) {
            $price = (int) ($office['price'] ?? 0);
            if ($price > 0) {
                $prices[] = $price;
            }
        }
        return $prices;
    }

    /**
     * Un mot de la question compte s'il est identique au mot-clé ou s'il en est
     * une variante par préfixe : « situés » → « situe », « disponibilités » →
     * « disponibilite ». Évite d'énumérer tous les pluriels et conjugaisons.
     */
    private static function matches(array $tokens, array $words): bool
    {
        foreach ($tokens as $token) {
            foreach ($words as $word) {
                if ($token === $word) {
                    return true;
                }
                $short = min(\strlen($token), \strlen($word));
                if ($short >= 5 && (str_starts_with($token, $word) || str_starts_with($word, $token))) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Intentions de la question. Une relance du type « et à Carnot ? » ne
     * contient aucun mot-clé : on reprend alors l'intention du dernier tour,
     * ce qui rend la conversation réellement suivie.
     *
     * @return array<int,string>
     */
    private static function intentsOf(array $tokens, array $history = []): array
    {
        $found = [];
        foreach (self::INTENTS as $intent => $words) {
            if (self::matches($tokens, $words)) {
                $found[] = $intent;
            }
        }
        if ($found !== [] || $history === []) {
            return $found;
        }
        // Relance : elle doit tout de même désigner un lieu ou un type,
        // sinon on laisse la main à l'index documentaire.
        if (self::siteOf($tokens) === '' && self::typeOf($tokens) === '') {
            return [];
        }
        foreach (array_reverse($history) as $turn) {
            if (($turn['role'] ?? '') !== 'user') {
                continue;
            }
            $previous = self::intentsOf(Indexer::tokenize((string) ($turn['text'] ?? '')));
            if ($previous !== []) {
                return $previous;
            }
        }
        return [];
    }

    private static function siteOf(array $tokens): string
    {
        foreach (self::SITE_WORDS as $word => $id) {
            if (\in_array($word, $tokens, true)) {
                return $id;
            }
        }
        return '';
    }

    private static function typeOf(array $tokens): string
    {
        foreach (self::TYPE_WORDS as $type => $words) {
            if (self::matches($tokens, $words)) {
                return $type;
            }
        }
        return '';
    }
}
