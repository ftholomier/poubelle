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
        . 'Une ANECDOTE, pas un compte rendu : dans le fait fourni, cherche l’angle le plus étonnant, le plus fier ou le plus insolite, celui qu’un supporter aura envie de raconter : '
        . 'un record ou un rang dans l’histoire du club, une première fois, un détail de coulisses, une prime, une phrase marquante, un parcours hors du commun, un chiffre frappant. '
        . 'Évite la simple mention « X marque pour Sochaux le … » si le fait offre mieux. '
        . 'Règles strictes : utilise UNIQUEMENT le fait fourni, n’ajoute aucune information, aucun chiffre, aucune date qui n’y figure pas ; '
        . 'cite TOUJOURS les noms précis donnés dans le fait : le joueur ou l’entraîneur concerné (prénom et nom), l’adversaire, la compétition, la date du match — jamais « un Lionceau » ou « un joueur » quand le nom est connu ; n’utilise aucun nom absent du fait ; une seule phrase AFFIRMATIVE, en français, au présent ou au passé : jamais de question, jamais « Le saviez-vous », « Saviez-vous » ni point d’interrogation ; '
        . 'ton fier et chaleureux, jamais moqueur envers l’adversaire ; pas de guillemets, pas d’émoji ; respecte la longueur maximale demandée.';

    /** Réserve : les anecdotes déjà rédigées par l'IA, resservies sans IA quand le budget du jour est atteint. */
    public const POOL_MAX = 600;
    /** Version de la consigne : seules les anecdotes de la version en cours sont ressorties du stock. */
    public const VERSION = 3;

    private static function poolFile(): string
    {
        return STORAGE_PATH . '/shop/anecdotes-reserve.json';
    }

    /** Les calques d'un modèle qui reçoivent l'anecdote. */
    public static function layers(array $m): array
    {
        $out = [];
        foreach ($m['faces'] as $f) {
            foreach ($f['layers'] as $l) {
                if (($l['mode'] ?? '') === 'client' && ($l['field'] ?? '') === self::FIELD) {
                    $out[] = $l;
                }
            }
        }
        return $out;
    }

    /** Texte d'exemple sans « Le saviez-vous ? » (anciens modèles). */
    public static function clean(string $t): string
    {
        return trim((string) preg_replace('/^\s*(le\s+)?saviez[- ]vous\s*\?\s*/iu', '', $t));
    }

    /** Une anecdote de la réserve qui tient dans le cadre, ni vendue ni déjà vue par ce client. */
    public static function fromPool(array $layers, array $avoid = [], string $topic = ''): array
    {
        $ok = self::poolChoices($layers, $avoid, 1, $topic);
        return $ok ? ['text' => $ok[0], 'sig' => self::sign($ok[0]), 'pool' => true]
            : ['error' => 'Pas d’autre anecdote disponible pour le moment : revenez un peu plus tard.'];
    }

    /** Anecdotes du stock (version en cours) ni vendues ni déjà vues, qui tiennent dans le cadre ; au plus $limit. */
    public static function poolChoices(array $layers, array $avoid = [], int $limit = PHP_INT_MAX, string $topic = ''): array
    {
        $sold = array_flip((array) JsonStore::read(self::soldFile(), []));
        $avoid = array_flip(array_map([self::class, 'key'], $avoid));
        // Avec un sujet : seulement les anecdotes rédigées sur ce sujet.
        $pool = array_filter((array) JsonStore::read(self::poolFile(), []), fn ($e) => is_array($e) && (int) ($e['v'] ?? 0) === self::VERSION && ($topic === '' || ($e['k'] ?? '') === $topic));
        shuffle($pool);
        $out = [];
        foreach ($pool as $e) {
            $t = (string) ($e['t'] ?? '');
            if ($t === '' || isset($sold[self::key($t)]) || isset($avoid[self::key($t)])) {
                continue;
            }
            $fit = true;
            foreach ($layers as $l) {
                $fit = $fit && Vector::fits($l, [($l['field'] ?? self::FIELD) => $t]);
            }
            if ($fit && count($out) < $limit) {
                $out[] = $t;
            }
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * Le tirage du client : toujours dans le stock déjà rédigé d'abord (aucun coût) ; quand ce client
     * a déjà vu tout ce qui convient dans le stock, une nouvelle anecdote par l'IA (si le budget le permet).
     */
    public static function pick(array $layers, array $avoid, bool $aiAllowed, string $topic = ''): array
    {
        if ($topic !== '' && !self::topicRich($topic)) {
            // Rien de parlant dans la fiche de ce sujet : on le dit, et on revient au tirage du musée.
            $r = self::pick($layers, $avoid, $aiAllowed);
            return isset($r['error']) ? $r : $r + ['note' => 'Le musée n’a pas encore d’anecdote à raconter sur ce sujet : en voici une tirée dans toute l’histoire du club.'];
        }
        $r = self::fromPool($layers, $avoid, $topic);
        if (isset($r['error']) && $aiAllowed) {
            $r = self::draw($layers, $avoid, $topic);
        }
        if (isset($r['error']) && $topic !== '') {
            // Pas d'IA aujourd'hui pour ce sujet : une anecdote au hasard, en le disant au client.
            return self::fromPool($layers, $avoid) + ['note' => 'Plus d’anecdote sur ce sujet pour aujourd’hui : en voici une au hasard.'];
        }
        return $r;
    }

    private static function keep(string $t, string $topic = ''): void
    {
        JsonStore::update(self::poolFile(), function ($all) use ($t, $topic) {
            $all = is_array($all) ? $all : [];
            $all = array_values(array_filter($all, 'is_array')); // les anciennes entrées (texte seul, consigne périmée) s'effacent
            if (!in_array($t, array_column($all, 't'), true)) {
                $all[] = ['t' => $t, 'v' => self::VERSION] + ($topic !== '' ? ['k' => $topic] : []);
            }
            return array_slice($all, -self::POOL_MAX);
        }, []);
    }

    private static function soldFile(): string
    {
        return STORAGE_PATH . '/shop/anecdotes-vendues.json';
    }

    /**
     * Un fait vérifié : [texte du fait, référence]. Sans sujet, au hasard (un chiffre du FCSM ou un
     * match) ; avec un sujet choisi par le client (« m:ID » un match, « p:ID » un joueur ou un
     * entraîneur), un fait sur ce sujet.
     */
    public static function fact(string $topic = '', int $n = 0): array
    {
        if ($topic !== '') {
            // Les faits du sujet, du plus « anecdotique » au plus banal : chaque nouveau tirage passe au suivant.
            $all = self::topicFacts($topic);
            return $all ? $all[$n % count($all)] : ['', ''];
        }
        $dice = random_int(0, 2);
        if ($dice === 2 && ($p = self::notablePerson())) {
            // Un joueur marquant : un de ses faits les plus parlants (record, chiffre clé, histoire).
            $best = array_slice(self::topicFacts('p:' . $p), 0, max(1, min(4, self::$rich['p:' . $p] ?? 0)));
            if ($best && (self::$rich['p:' . $p] ?? 0) > 0) {
                return $best[random_int(0, count($best) - 1)];
            }
        }
        if ($dice === 0) {
            $stats = array_values(array_filter(Chiffres::flat(), fn ($s) => ($s['value'] ?? '') !== '' && ($s['label'] ?? '') !== ''));
            if ($stats) {
                $s = $stats[random_int(0, count($stats) - 1)];
                $who = implode(', ', array_filter(array_map(fn ($w) => (string) ($w['name'] ?? ''), (array) ($s['who'] ?? []))));
                $t = $s['label'] . ' : ' . $s['value'] . (($s['unit'] ?? '') !== '' ? ' ' . $s['unit'] : '') . '. ' . trim((string) ($s['text'] ?? ''))
                    . ($who !== '' ? ' Qui : ' . $who . '.' : '');
                return [trim(strip_tags($t)), 'chiffre:' . $s['key']];
            }
        }
        $matches = self::matches();
        if (!$matches) {
            return ['', ''];
        }
        // Les matchs marquants d'abord (deux fois sur trois), sinon n'importe lequel.
        $hl = array_values(array_filter($matches, fn ($m) => (int) ($m['hl'] ?? 0) > 0));
        $pool = $hl && random_int(0, 2) > 0 ? $hl : $matches;
        $m = $pool[random_int(0, count($pool) - 1)];
        // Un des faits les plus parlants de sa fiche (chiffre clé, coulisses, déclarations), pas le simple score.
        $best = array_slice(self::topicFacts('m:' . $m['id']), 0, max(1, min(3, self::$rich['m:' . $m['id']] ?? 0)));
        return $best ? $best[random_int(0, count($best) - 1)] : self::matchFact($m);
    }

    /** Un joueur ou entraîneur marquant au hasard (légende, ou au moins 100 matchs avec le club). */
    private static function notablePerson(): ?int
    {
        $tot = Derived::part('person_totals');
        $ids = [];
        foreach (\App\Data\Index::all() as $s) {
            if (($s['type'] ?? '') === 'personne' && \App\Data\Index::visible($s)
                && (!empty($s['p']['legend']) || (int) (($tot[$s['id']]['matches'] ?? 0) + ($tot[$s['id']]['coached'] ?? 0)) >= 100)) {
                $ids[] = (int) $s['id'];
            }
        }
        return $ids ? $ids[random_int(0, count($ids) - 1)] : null;
    }

    /** @return list<array> matchs publiés et datés */
    private static function matches(): array
    {
        return array_values(array_filter(Derived::part('matches'), fn ($m) => !empty($m['v']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($m['date'] ?? ''))));
    }

    /** Le fait d'un match : date, affiche, compétition, stade, affluence, buteurs, temps forts. */
    private static function matchFact(array $m, bool $rich = false): array
    {
        $v = TonMatch::values((string) $m['date']);
        if ((string) ($v['_match'] ?? '') !== (string) $m['id']) {
            // Deux matchs le même jour : on décrit bien celui-ci.
            $v['match_affiche'] = $m['home'] . ' ' . ($m['sh'] ? $m['us'] . '-' . $m['them'] : $m['them'] . '-' . $m['us']) . ' ' . $m['away'];
        }
        $t = 'Le ' . $v['match_date'] . ' : ' . $v['match_affiche'] . ($v['match_compet'] !== '' ? ' (' . $v['match_compet'] . ')' : '')
            . ($v['match_lieu'] !== '' ? ', à ' . $v['match_lieu'] : '') . ($v['match_public'] !== '' ? ', devant ' . $v['match_public'] : '')
            . '.';
        $sc = Derived::part('scorers')[$m['id']] ?? Derived::part('scorers')[(string) $m['id']] ?? [];
        $names = array_values(array_unique(array_filter(array_map(fn ($x) => trim((string) ($x[1] ?? '')), (array) $sc))));
        if ($names) {
            $t .= ' Buteurs sochaliens : ' . implode(', ', $names) . '.';
        }
        if ($rich) {
            // Sujet choisi par le client : quelques temps forts de la fiche pour varier les anecdotes.
            $hs = array_values(array_filter((array) (\App\Data\Fiches::get((int) $m['id'])['match']['highlights'] ?? []), fn ($h) => trim((string) ($h['text'] ?? '')) !== ''));
            shuffle($hs);
            foreach (array_slice($hs, 0, 3) as $h) {
                $t .= ' ' . preg_replace('/\D/', '', (string) ($h['minute'] ?? '')) . 'e minute : ' . Poster::tidy((string) $h['text']);
            }
        }
        return [trim((string) preg_replace('/\s+/', ' ', $t)), 'match:' . $m['id']];
    }

    /** Le meilleur fait d'un sujet choisi par le client, ou null si le sujet est inconnu. */
    public static function topicFact(string $topic): ?array
    {
        return self::topicFacts($topic)[0] ?? null;
    }

    /** @var array<string,list<array>> */
    private static array $facts = [];
    /** @var array<string,int> nombre de faits « parlants » (records, chiffre clé, histoire, coulisses) par sujet */
    private static array $rich = [];

    /** Le musée a-t-il quelque chose de parlant à raconter sur ce sujet (pas seulement un score ou un bilan) ? */
    public static function topicRich(string $topic): bool
    {
        self::topicFacts($topic);
        return (self::$rich[$topic] ?? 0) > 0;
    }

    /**
     * Les faits d'un sujet, classés du plus « anecdotique » au plus banal. Joueur : ses records et
     * rangs dans les 100 chiffres du FCSM, le chiffre clé de sa fiche, les paragraphes de son
     * histoire, son bilan, puis un match où il a marqué. Match : le chiffre clé, les coulisses
     * (avant-match, primes, déclarations, réactions), puis le match lui-même et ses temps forts.
     * @return list<array{0:string,1:string}>
     */
    public static function topicFacts(string $topic): array
    {
        if (!preg_match('/^([mp]):(\d{1,9})$/', $topic, $x)) {
            return [];
        }
        if (isset(self::$facts[$topic])) {
            return self::$facts[$topic];
        }
        $id = (int) $x[2];
        $out = [];
        $doc = \App\Data\Fiches::get($id);
        $clean = fn (string $h) => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) preg_replace('/<\/(li|p)>/i', "\n", $h)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        // Paragraphes (ou puces) d'une fiche, de 80 à 900 caractères, sans tableau de statistiques.
        $paras = function (array $doc) use ($clean): array {
            $ps = [];
            foreach ((array) ($doc['sections'] ?? []) as $sec) {
                if (preg_match('/statisti|composition|palmar/i', (string) ($sec['title'] ?? ''))) {
                    continue;
                }
                foreach (preg_split('/<\/(?:p|li)>/i', (string) ($sec['html'] ?? '')) ?: [] as $chunk) {
                    $t = Poster::tidy($clean($chunk));
                    if (mb_strlen($t) >= 80) {
                        $ps[] = mb_substr($t, 0, 900);
                    }
                }
            }
            return $ps;
        };
        if ($x[1] === 'm') {
            $m = null;
            foreach (self::matches() as $mm) {
                if ((int) $mm['id'] === $id) {
                    $m = $mm;
                }
            }
            if (!$m || !$doc) {
                return self::$facts[$topic] = [];
            }
            [$base] = self::matchFact($m);
            $k = (array) ($doc['key_figure'] ?? []);
            if (trim((string) ($k['text'] ?? '')) !== '') {
                $out[] = [$base . ' Le chiffre : ' . rtrim(Poster::tidy((string) $k['text']), '. ') . '.', 'match:' . $id];
            }
            foreach ($paras($doc) as $p) {
                $out[] = [$base . ' ' . $p, 'match:' . $id];
            }
            foreach (['reactions', 'breves'] as $kk) {
                foreach ((array) ($doc['match'][$kk] ?? []) as $r) {
                    $t = Poster::tidy(is_array($r) ? implode(' ', array_filter($r, 'is_string')) : (string) $r);
                    if (mb_strlen($t) >= 60) {
                        $out[] = [$base . ' ' . mb_substr($t, 0, 700), 'match:' . $id];
                    }
                }
            }
            self::$rich[$topic] = count(self::unique($out));
            $out[] = self::matchFact($m, true);
            return self::$facts[$topic] = self::unique($out);
        }
        $s = \App\Data\Index::get($id);
        if (!$s || ($s['type'] ?? '') !== 'personne' || !\App\Data\Index::visible($s)) {
            return self::$facts[$topic] = [];
        }
        $p = (array) ($s['p'] ?? []);
        $name = (string) ($p['name'] ?? $s['title']);
        $ref = 'personne:' . $id;
        // 1. Records et rangs dans les 100 chiffres du FCSM.
        foreach (Chiffres::flat() as $c) {
            $who = (array) ($c['who'] ?? []);
            $rank = null;
            $val = '';
            foreach ($who as $w) {
                if ((int) ($w['id'] ?? 0) === $id || ($w['name'] ?? '') === $name) {
                    $rank = 1;
                    $val = (string) $c['value'];
                }
            }
            foreach ((array) ($c['more'] ?? []) as $i => $w) {
                if ($rank === null && ($w['name'] ?? '') === $name) {
                    $rank = $i + 2;
                    $val = (string) ($w['v'] ?? '');
                }
            }
            if ($rank === null) {
                continue;
            }
            $unit = (string) ($c['unit'] ?? '');
            $holder = implode(', ', array_map(fn ($w) => (string) ($w['name'] ?? ''), $who));
            $t = $rank === 1
                ? $name . ' détient le record du FC Sochaux-Montbéliard « ' . $c['label'] . ' » : ' . $val . ' ' . $unit . '. ' . trim(strip_tags((string) ($c['text'] ?? '')))
                : $name . ' est ' . $rank . 'e de l’histoire du FC Sochaux-Montbéliard au classement « ' . $c['label'] . ' », avec ' . $val . ' ' . $unit . ' (record : ' . $holder . ', ' . $c['value'] . ' ' . $unit . ').';
            $out[] = [trim($t), $ref];
        }
        $records = $out;
        $out = [];
        // 2. Le chiffre clé de sa fiche, 3. les paragraphes de son histoire.
        if ($doc) {
            $k = (array) ($doc['key_figure'] ?? []);
            if (trim((string) ($k['text'] ?? '')) !== '') {
                $out[] = [rtrim(Poster::tidy((string) $k['text']), '. ') . '.', $ref];
            }
            foreach ($paras($doc) as $para) {
                $out[] = [str_contains($para, $name) || str_contains($para, (string) ($p['last'] ?? '~')) ? $para : $name . ' : ' . $para, $ref];
            }
        }
        // Records et histoire en alternance (les plus parlants d'abord), puis le reste.
        $story = $out;
        $out = [];
        for ($i = 0; $i < max(count($records), count($story)); $i++) {
            foreach ([$records[$i] ?? null, $story[$i] ?? null] as $f) {
                if ($f) {
                    $out[] = $f;
                }
            }
        }
        self::$rich[$topic] = count(self::unique($out));
        // 4. Son bilan au club.
        $tot = (array) (Derived::part('person_totals')[$id] ?? []);
        $roles = str_replace('entraineur', 'entraîneur', implode(' et ', (array) ($p['roles'] ?? [])));
        $t = $name . ($roles !== '' ? ', ' . $roles : '') . (($p['position'] ?? '') !== '' ? ' (' . $p['position'] . ')' : '') . ' du FC Sochaux-Montbéliard'
            . (!empty($p['arrival']) ? ', au club de ' . $p['arrival'] . (!empty($p['departure']) && $p['departure'] != $p['arrival'] ? ' à ' . $p['departure'] : '') : '')
            . (!empty($p['birth_year']) ? ', né en ' . $p['birth_year'] . (!empty($p['birth_place']) ? ' à ' . $p['birth_place'] : '') : '') . '.';
        if (($tot['matches'] ?? 0) > 0) {
            $t .= ' ' . $tot['matches'] . ' matchs officiels avec Sochaux' . (($tot['goals'] ?? 0) > 0 ? ', ' . $tot['goals'] . ' buts' : '') . (($tot['seasons'] ?? 0) > 1 ? ', ' . $tot['seasons'] . ' saisons' : '') . '.';
        }
        if (($tot['coached'] ?? 0) > 0) {
            $t .= ' Entraîneur de Sochaux sur ' . $tot['coached'] . ' matchs (' . $tot['v'] . ' victoires, ' . $tot['n'] . ' nuls, ' . $tot['d'] . ' défaites).';
        }
        foreach (['legend' => ' Une légende du club.', 'formed' => ' Formé au club.', 'intl' => ' International.'] as $kk => $label) {
            $t .= !empty($p[$kk]) ? $label : '';
        }
        $out[] = [trim((string) preg_replace('/\s+/', ' ', $t)), $ref];
        // 5. En dernier : un ou deux matchs où il a marqué (les plus marquants).
        $goals = [];
        foreach (Derived::part('scorers') as $mid => $list) {
            foreach ((array) $list as $g) {
                if ((int) ($g[0] ?? 0) === $id) {
                    $goals[(string) $mid] = true;
                }
            }
        }
        $gm = array_values(array_filter(self::matches(), fn ($m) => isset($goals[(string) $m['id']])));
        usort($gm, fn ($a, $b) => (int) ($b['hl'] ?? 0) <=> (int) ($a['hl'] ?? 0));
        foreach (array_slice($gm, 0, 2) as $m) {
            [$mt] = self::matchFact($m);
            $out[] = [$mt . ' Ce jour-là, ' . $name . ' marque pour Sochaux.', $ref];
        }
        return self::$facts[$topic] = self::unique($out);
    }

    /** Faits sans doublon (même texte à la ponctuation près). */
    private static function unique(array $facts): array
    {
        $seen = [];
        $out = [];
        foreach ($facts as $f) {
            $k = self::key(mb_substr($f[0], -300));
            if (!isset($seen[$k])) {
                $seen[$k] = true;
                $out[] = $f;
            }
        }
        return $out;
    }

    /**
     * Sujets proposés au client (saisie automatique) : joueurs et entraîneurs (les plus capés
     * d'abord), puis matchs (les plus marquants d'abord). @return list<array{key:string,label:string}>
     */
    public static function topics(string $q, int $limit = 10): array
    {
        $words = array_values(array_filter(preg_split('/[\s,\/·-]+/u', mb_strtolower(\App\Data\Names::ascii(trim($q)))) ?: [], fn ($w) => $w !== ''));
        if (!$words || mb_strlen(trim($q)) < 2) {
            return [];
        }
        $has = function (string $hay) use ($words): bool {
            foreach ($words as $w) {
                if (!str_contains($hay, $w) && !(preg_match('/^\d{2}$/', $w) && (str_contains($hay, ' 19' . $w) || str_contains($hay, ' 20' . $w)))) {
                    return false;
                }
            }
            return true;
        };
        $tot = Derived::part('person_totals');
        $people = [];
        foreach (\App\Data\Index::all() as $s) {
            if (($s['type'] ?? '') !== 'personne' || !\App\Data\Index::visible($s)) {
                continue;
            }
            $p = (array) ($s['p'] ?? []);
            $name = (string) ($p['name'] ?? $s['title']);
            if ($has(' ' . mb_strtolower(\App\Data\Names::ascii($name . ' ' . ($p['nickname'] ?? ''))) . ' ')) {
                $years = !empty($p['arrival']) ? ' · ' . $p['arrival'] . (!empty($p['departure']) && $p['departure'] != $p['arrival'] ? '-' . $p['departure'] : '') : '';
                $people[] = ['key' => 'p:' . $s['id'], 'label' => $name . ' · ' . (str_replace('entraineur', 'entraîneur', implode(', ', (array) ($p['roles'] ?? []))) ?: 'Lionceau') . $years,
                    'n' => (int) (($tot[$s['id']]['matches'] ?? 0) + ($tot[$s['id']]['coached'] ?? 0))];
            }
        }
        usort($people, fn ($a, $b) => $b['n'] <=> $a['n']);
        $games = [];
        foreach (self::matches() as $m) {
            $hay = ' ' . mb_strtolower(\App\Data\Names::ascii(implode(' ', [$m['home'], $m['away'], $m['comp'], $m['label'] ?? '', $m['round'] ?? '', $m['date'], $m['season'] ?? '', TonMatch::frDate((string) $m['date'])]))) . ' ';
            if ($has($hay)) {
                $games[] = $m;
            }
        }
        usort($games, fn ($a, $b) => ((int) ($b['hl'] ?? 0) <=> (int) ($a['hl'] ?? 0)) ?: strcmp((string) $b['date'], (string) $a['date']));
        $out = array_map(fn ($p) => ['key' => $p['key'], 'label' => $p['label']], array_slice($people, 0, (int) ceil($limit / 2)));
        foreach ($games as $m) {
            if (count($out) >= $limit) {
                break;
            }
            $out[] = ['key' => 'm:' . $m['id'], 'label' => Poster::label($m)];
        }
        return $out;
    }

    /** Mots du club toujours permis, même absents du fait. */
    private const OK_NAMES = ['sochaux', 'lionceaux', 'lionceau', 'fcsm', 'fc', 'montbeliard', 'bonal', 'auguste', 'auguste-bonal', 'peugeot', 'france', 'coupe', 'ligue', 'championnat'];

    /** Les noms propres de la phrase absents du fait. */
    public static function foreignNames(string $t, string $fact): array
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
    public static function numbers(string $t): array
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
    public static function draw(array $layers, array $avoid = [], string $topic = ''): array
    {
        $max = 160;
        foreach ($layers as $l) {
            $max = min($max, max(40, (int) ($l['max'] ?? 160)));
        }
        $asked = $avoid;
        $sold = array_flip((array) JsonStore::read(self::soldFile(), []));
        $avoid = array_flip(array_map([self::class, 'key'], $avoid));
        if (self::$ai === null && !Gemini::ready()) {
            return self::fromPool($layers, $asked, $topic);
        }
        // Au plus 3 tirages par l'IA à la fois sur tout le site (chacun occupe un processus PHP
        // plusieurs secondes) et 25 s au total : au-delà, la réserve d'anecdotes déjà rédigées.
        $slot = self::$ai === null ? self::slot() : true;
        if ($slot === null) {
            return self::fromPool($layers, $asked, $topic);
        }
        $until = microtime(true) + 25;
        for ($try = 0; $try < 4 && microtime(true) < $until; $try++) {
            [$fact] = self::fact($topic, count($asked) + $try);
            if ($fact === '') {
                break;
            }
            $ask = "Fait : $fact\nLongueur maximale : " . ($try > 1 ? (int) ($max * 0.8) : $max) . " caractères, espaces compris.\nRéponds par la phrase seule.";
            try {
                $out = self::$ai !== null ? (string) (self::$ai)(self::SYSTEM, $ask)
                    : (string) Gemini::generate([['role' => 'user', 'text' => $ask]], self::SYSTEM, ['for' => 'boutique', 'ref' => 'boutique:anecdote', 'temperature' => 0.9, 'max_tokens' => 400, 'timeout' => 12])['text'];
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
                self::keep($t, $topic);
                return ['text' => $t, 'sig' => self::sign($t)];
            }
        }
        return self::fromPool($layers, $asked, $topic); // l'IA n'a rien donné de sûr : la réserve
    }

    /** Une des 3 places de tirage par l'IA (verrou gardé jusqu'à la fin de la requête), ou null. */
    private static function slot(): mixed
    {
        @mkdir(STORAGE_PATH . '/shop', 0775, true);
        for ($i = 0; $i < 3; $i++) {
            $fp = @fopen(STORAGE_PATH . '/shop/ia-place-' . $i . '.lock', 'c');
            if ($fp && flock($fp, LOCK_EX | LOCK_NB)) {
                return $fp;
            }
            if ($fp) {
                fclose($fp);
            }
        }
        return null;
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
