<?php
declare(strict_types=1);

namespace App\Shop;

use App\Core\JsonStore;
use App\Data\Derived;
use App\Services\Chiffres;
use App\Services\Gemini;

/**
 * Anecdote (une affirmation, jamais une question) tirée par le client sur la page d'un article (bouton « Une autre »).
 * Pour ne jamais imprimer un fait inventé, l'IA ne fait que rédiger : le fait vient de la base du
 * musée (un des 100 chiffres du FCSM, ou un vrai match : date, score, buteurs, affluence), et la
 * phrase est refusée si elle contient un nombre absent du fait ou si elle ne tient pas dans le
 * cadre. Chaque anecdote est signée par le serveur : la commande n'accepte qu'une anecdote
 * réellement tirée ici, jamais un texte retouché. Les anecdotes vendues sont notées pour ne pas
 * être reproposées (chaque client a la sienne).
 */
final class Anecdotes
{
    /** Champ réservé dans les modèles : un calque texte « client » de ce nom reçoit l'anecdote. */
    public const FIELD = 'anecdote';
    /** Faux Gemini des tests : fn (string $system, string $user) => string. */
    public static $ai = null;

    private const SYSTEM = 'Tu rédiges une anecdote pour un produit dérivé (t-shirt, mug, poster) de Sochaux Rétro, '
        . 'le musée des supporters du FC Sochaux-Montbéliard (les Lionceaux, jaune et bleu, stade Auguste-Bonal). '
        . 'Règles strictes : utilise UNIQUEMENT le fait fourni, n’ajoute aucune information, aucun chiffre, aucune date qui n’y figure pas ; '
        . 'cite TOUJOURS les noms précis donnés dans le fait : le joueur ou l’entraîneur concerné (prénom et nom), l’adversaire, la compétition, la date du match — jamais « un Lionceau » ou « un joueur » quand le nom est connu ; n’utilise aucun nom absent du fait ; une seule phrase AFFIRMATIVE, en français, au présent ou au passé : jamais de question, jamais « Le saviez-vous », « Saviez-vous » ni point d’interrogation ; '
        . 'ton fier et chaleureux, jamais moqueur envers l’adversaire ; pas de guillemets, pas d’émoji ; respecte la longueur maximale demandée.';

    /** Réserve : les anecdotes déjà rédigées par l'IA, resservies sans IA quand le budget du jour est atteint. */
    public const POOL_MAX = 600;

    private static function poolFile(): string
    {
        return STORAGE_PATH . '/shop/anecdotes-reserve.json';
    }

    /** Une anecdote de la réserve qui tient dans le cadre, ni vendue ni déjà vue par ce client. */
    public static function fromPool(array $layers, array $avoid = []): array
    {
        $sold = array_flip((array) JsonStore::read(self::soldFile(), []));
        $avoid = array_flip(array_map([self::class, 'key'], $avoid));
        $pool = (array) JsonStore::read(self::poolFile(), []);
        shuffle($pool);
        foreach ($pool as $t) {
            $t = (string) $t;
            if (isset($sold[self::key($t)]) || isset($avoid[self::key($t)])) {
                continue;
            }
            $fit = true;
            foreach ($layers as $l) {
                $fit = $fit && Vector::fits($l, [($l['field'] ?? self::FIELD) => $t]);
            }
            if ($fit) {
                return ['text' => $t, 'sig' => self::sign($t)];
            }
        }
        return ['error' => 'Pas d’autre anecdote disponible pour le moment : revenez un peu plus tard.'];
    }

    private static function keep(string $t): void
    {
        JsonStore::update(self::poolFile(), function ($all) use ($t) {
            $all = is_array($all) ? $all : [];
            if (!in_array($t, $all, true)) {
                $all[] = $t;
            }
            return array_slice($all, -self::POOL_MAX);
        }, []);
    }

    private static function soldFile(): string
    {
        return STORAGE_PATH . '/shop/anecdotes-vendues.json';
    }

    /** Un fait vérifié, au hasard : [texte du fait, référence]. */
    public static function fact(): array
    {
        if (random_int(0, 1) === 0) {
            $stats = array_values(array_filter(Chiffres::flat(), fn ($s) => ($s['value'] ?? '') !== '' && ($s['label'] ?? '') !== ''));
            if ($stats) {
                $s = $stats[random_int(0, count($stats) - 1)];
                $who = implode(', ', array_filter(array_map(fn ($w) => (string) ($w['name'] ?? ''), (array) ($s['who'] ?? []))));
                $t = $s['label'] . ' : ' . $s['value'] . (($s['unit'] ?? '') !== '' ? ' ' . $s['unit'] : '') . '. ' . trim((string) ($s['text'] ?? ''))
                    . ($who !== '' ? ' Qui : ' . $who . '.' : '');
                return [trim(strip_tags($t)), 'chiffre:' . $s['key']];
            }
        }
        $matches = array_values(array_filter(Derived::part('matches'), fn ($m) => !empty($m['v']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($m['date'] ?? ''))));
        if (!$matches) {
            return ['', ''];
        }
        // Les matchs marquants d'abord (deux fois sur trois), sinon n'importe lequel.
        $hl = array_values(array_filter($matches, fn ($m) => (int) ($m['hl'] ?? 0) > 0));
        $pool = $hl && random_int(0, 2) > 0 ? $hl : $matches;
        $m = $pool[random_int(0, count($pool) - 1)];
        $v = TonMatch::values((string) $m['date']);
        $t = 'Le ' . $v['match_date'] . ' : ' . $v['match_affiche'] . ($v['match_compet'] !== '' ? ' (' . $v['match_compet'] . ')' : '')
            . ($v['match_lieu'] !== '' ? ', à ' . $v['match_lieu'] : '') . ($v['match_public'] !== '' ? ', devant ' . $v['match_public'] : '')
            . '.';
        $sc = Derived::part('scorers')[$m['id']] ?? Derived::part('scorers')[(string) $m['id']] ?? [];
        $names = array_values(array_unique(array_filter(array_map(fn ($x) => trim((string) ($x[1] ?? '')), (array) $sc))));
        if ($names) {
            $t .= ' Buteurs sochaliens : ' . implode(', ', $names) . '.';
        }
        return [trim((string) preg_replace('/\s+/', ' ', $t)), 'match:' . $m['id']];
    }

    /** Mots du club toujours permis, même absents du fait. */
    private const OK_NAMES = ['sochaux', 'lionceaux', 'lionceau', 'fcsm', 'fc', 'montbeliard', 'bonal', 'auguste', 'auguste-bonal', 'peugeot', 'france', 'coupe', 'ligue', 'championnat'];

    /** Les noms propres de la phrase absents du fait. */
    private static function foreignNames(string $t, string $fact): array
    {
        $f = mb_strtolower(\App\Data\Names::ascii($fact));
        preg_match_all('/(?<![\p{L}\'’])\p{Lu}[\p{L}\'’-]+/u', $t, $m, PREG_OFFSET_CAPTURE);
        $bad = [];
        foreach ($m[0] as [$w, $pos]) {
            $k = mb_strtolower(\App\Data\Names::ascii(trim($w, "'’-")));
            // Premier mot d'une phrase : majuscule de grammaire, pas forcément un nom.
            if ($pos === 0 || preg_match('/[.!:;]\s*$/u', substr($t, 0, $pos))) {
                continue;
            }
            if (in_array($k, self::OK_NAMES, true) || str_contains($f, $k)) {
                continue;
            }
            $bad[] = $w;
        }
        return $bad;
    }

    /** Les nombres d'un texte (1 234 et 1234 comptent pareil). */
    private static function numbers(string $t): array
    {
        preg_match_all('/\d[\d\x{202F}\x{00A0} .,]*\d|\d/u', $t, $m);
        return array_values(array_unique(array_map(fn ($n) => (string) preg_replace('/\D/', '', $n), $m[0])));
    }

    /** Signature d'une anecdote (champ caché du formulaire). */
    public static function sign(string $text): string
    {
        return substr(hash_hmac('sha256', $text, site_key('boutique-anecdote')), 0, 32);
    }

    public static function valid(string $text, string $sig): bool
    {
        return $text !== '' && $sig !== '' && hash_equals(self::sign($text), $sig);
    }

    private static function key(string $t): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower(\App\Data\Names::ascii($t)));
    }

    /**
     * Tire une anecdote qui tient dans les calques du champ. @param list<array> $layers
     * @return array{text:string,sig:string}|array{error:string}
     */
    public static function draw(array $layers, array $avoid = []): array
    {
        $max = 160;
        foreach ($layers as $l) {
            $max = min($max, max(40, (int) ($l['max'] ?? 160)));
        }
        $asked = $avoid;
        $sold = array_flip((array) JsonStore::read(self::soldFile(), []));
        $avoid = array_flip(array_map([self::class, 'key'], $avoid));
        if (self::$ai === null && !Gemini::ready()) {
            return self::fromPool($layers, $asked);
        }
        for ($try = 0; $try < 4; $try++) {
            [$fact] = self::fact();
            if ($fact === '') {
                break;
            }
            $ask = "Fait : $fact\nLongueur maximale : " . ($try > 1 ? (int) ($max * 0.8) : $max) . " caractères, espaces compris.\nRéponds par la phrase seule.";
            try {
                $out = self::$ai !== null ? (string) (self::$ai)(self::SYSTEM, $ask)
                    : (string) Gemini::generate([['role' => 'user', 'text' => $ask]], self::SYSTEM, ['for' => 'boutique', 'ref' => 'boutique:anecdote', 'temperature' => 0.9, 'max_tokens' => 400])['text'];
            } catch (\Throwable $e) {
                return self::fromPool($layers, $asked);
            }
            $t = trim(str_replace(["'", '"', '«', '»'], ['’', '', '', ''], (string) preg_replace('/\s+/', ' ', $out)));
            $t = trim($t, " \t\n-–—");
            // Une affirmation, pas une question.
            if (str_contains($t, '?') || preg_match('/^(le\s+)?saviez[- ]vous/iu', $t)) {
                continue;
            }
            if ($t === '' || mb_strlen($t) > $max || isset($sold[self::key($t)]) || isset($avoid[self::key($t)])) {
                continue;
            }
            // Aucun nom propre inventé : chaque mot à majuscule (hors début de phrase) doit figurer dans le fait.
            if (self::foreignNames($t, $fact)) {
                continue;
            }
            // Aucun nombre inventé : chaque nombre de la phrase doit figurer dans le fait.
            if (array_diff(self::numbers($t), self::numbers($fact))) {
                continue;
            }
            $fit = true;
            foreach ($layers as $l) {
                $fit = $fit && Vector::fits($l, [($l['field'] ?? self::FIELD) => $t]);
            }
            if ($fit) {
                self::keep($t);
                return ['text' => $t, 'sig' => self::sign($t)];
            }
        }
        return self::fromPool($layers, $asked); // l'IA n'a rien donné de sûr : la réserve
    }

    /** Anecdote vendue : elle ne sera plus proposée à personne. */
    public static function sold(string $text): void
    {
        JsonStore::update(self::soldFile(), function ($all) use ($text) {
            $all = is_array($all) ? $all : [];
            $all[] = self::key($text);
            return array_values(array_unique($all));
        }, []);
    }
}
