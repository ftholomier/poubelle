<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Core\Settings;
use App\Data\Collections;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;

/**
 * Correcteur d'orthographe et de syntaxe des textes saisis dans le back-office.
 *
 * Deux niveaux :
 * - les règles du musée, sans service extérieur : mot répété, espace avant une virgule,
 *   apostrophe suivie d'une espace, « 2ème » au lieu de « 2e », « A » au lieu de « À »… ;
 * - la relecture complète par Gemini quand la clé est réglée : accords, conjugaison,
 *   homophones, mots manquants, constructions fautives, fautes de frappe.
 *
 * Le correcteur ne modifie jamais un texte de lui-même : il propose, l'historien accepte
 * ou ignore chaque correction dans l'éditeur, puis enregistre. Les réponses de Gemini sont
 * gardées en cache texte par texte (un texte inchangé n'est jamais renvoyé) ; une tâche de
 * fond vérifie tout le musée et alimente Qualité › Orthographe.
 */
final class Proofreader
{
    /** À changer quand les consignes ou les règles changent : les anciennes réponses sont ignorées. */
    public const VERSION = 1;
    public const TYPES = [
        'orthographe' => 'Orthographe', 'accord' => 'Accord', 'conjugaison' => 'Conjugaison', 'homophone' => 'Homophone',
        'syntaxe' => 'Syntaxe', 'ponctuation' => 'Ponctuation', 'majuscule' => 'Majuscule', 'frappe' => 'Faute de frappe', 'typographie' => 'Typographie',
    ];
    /** Dossiers de travail (remplaçables dans les tests). */
    public static string $cacheDir = STORAGE_PATH . '/cache/correcteur';
    public static string $dir = STORAGE_PATH . '/correcteur';
    private const BATCH = 6000;        // caractères envoyés à Gemini par appel
    private const MAX_FIELD = 40000;   // au-delà, le texte est relu en partie seulement
    /** Fautes de langue (le reste : ponctuation, typographie) : elles comptent pour la priorité. */
    public const LANGUAGE = ['orthographe', 'accord', 'conjugaison', 'homophone', 'syntaxe', 'majuscule', 'frappe'];
    private const BLOCKS = ['p', 'div', 'section', 'article', 'header', 'footer', 'aside', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'figure', 'figcaption', 'hr', 'pre', 'dl', 'dt', 'dd', 'caption'];

    /** @var null|callable(array):array appels à Gemini (remplaçable dans les tests) */
    public static $ai = null;
    private static ?array $names = null;
    private static ?array $dict = null;

    // ------------------------------------------------------------------ texte

    /**
     * Texte brut d'un champ, tel que le voit le navigateur : nœuds de texte bout à bout,
     * un retour à la ligne avant et après chaque bloc (paragraphe, liste, citation…).
     * Le script de l'éditeur fait exactement le même calcul pour retrouver une faute.
     */
    public static function plain(string $value, bool $html): string
    {
        if (!$html) {
            return $value;
        }
        if ($value === '' || (!str_contains($value, '<') && !str_contains($value, '&'))) {
            return $value;
        }
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="cr-root">' . $value . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $dom->getElementById('cr-root');
        if (!$root) {
            return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $out = '';
        $walk = function (\DOMNode $n) use (&$walk, &$out): void {
            foreach ($n->childNodes as $c) {
                if ($c instanceof \DOMText) {
                    $out .= $c->nodeValue;
                } elseif ($c instanceof \DOMElement) {
                    $tag = strtolower($c->tagName);
                    if ($tag === 'br') {
                        $out .= "\n";
                        continue;
                    }
                    if (in_array($tag, ['script', 'style', 'template'], true)) {
                        continue;
                    }
                    $block = in_array($tag, self::BLOCKS, true);
                    if ($block) {
                        $out .= "\n";
                    }
                    $walk($c);
                    if ($block) {
                        $out .= "\n";
                    }
                }
            }
        };
        $walk($root);
        return $out;
    }

    /** Espaces resserrées (une seule espace, un seul retour à la ligne entre deux blocs). */
    public static function norm(string $text): string
    {
        $t = str_replace(["\r\n", "\r"], "\n", $text);
        $t = (string) preg_replace('/[ \t\x{00A0}\x{202F}\x{2007}\x{2009}]+/u', ' ', $t);
        $t = (string) preg_replace('/ *\n[\s\x{00A0}\x{202F}]*/u', "\n", $t);
        return trim($t);
    }

    // ------------------------------------------------------------------ règles du musée

    /**
     * Fautes repérées sans service extérieur (français seulement). Règles volontairement
     * prudentes : chacune ne vise que des cas sans ambiguïté.
     * @return list<array{off:int,wrong:string,right:string,type:string,why:string,src:string}>
     */
    public static function rules(string $t): array
    {
        $out = [];
        $add = function (int $off, string $wrong, string $right, string $type, string $why) use (&$out): void {
            if ($wrong !== $right) {
                $out[] = ['off' => $off, 'wrong' => $wrong, 'right' => $right, 'type' => $type, 'why' => $why, 'src' => 'regles'];
            }
        };
        $each = function (string $re, callable $fn) use ($t): void {
            if (preg_match_all($re, $t, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($all as $m) {
                    $fn($m, mb_strlen(substr($t, 0, $m[0][1])));
                }
            }
        };
        // Mot répété : « le le », « de de » (« nous nous », « vous vous » sont justes ;
        // « à à » cache souvent « a à », « il a à peine » : laissé à Gemini).
        $each('/(?<![\p{L}\p{N}\'’-])(\p{L}+) \1(?![\p{L}\p{N}\'’-])/u', function ($m, $off) use ($add, $t) {
            $w = $m[1][0];
            $lower = mb_strtolower($w);
            if (in_array($lower, ['nous', 'vous', 'à', 'très', 'si', 'non', 'oui', 'allez', 'bis', 'ah', 'oh', 'eh', 'hé', 'là', 'plus', 'trop', 'fort', 'vite', 'bien', 'tout', 'pas', 'loin', 'beaucoup', 'encore', 'jamais', 'merci', 'bravo', 'go', 'olé', 'ola', 'clap', 'la', 'na', 'ha', 'hop', 'ouh', 'tic', 'tac', 'pan', 'bla'], true)
                || preg_match('/^\p{Lu}/u', $w) || preg_match('/^x+$/i', $w)) {
                return;
            }
            // Trois fois de suite (chant, onomatopée) : voulu.
            $end = $m[0][1] + strlen($m[0][0]);
            if (preg_match('/^ ' . preg_quote($w, '/') . '(?![\p{L}])/u', substr($t, $end)) || preg_match('/(?<![\p{L}])' . preg_quote($w, '/') . ' $/u', substr($t, 0, $m[0][1]))) {
                return;
            }
            $add($off, $m[0][0], $w, 'frappe', 'Mot répété.');
        });
        // Espace avant une virgule ou un point.
        $each('/(?<=[\p{L}\p{N})])( +)([,.])(?=[\s)»"”]|$)(?!\.)/u', function ($m, $off) use ($add, $t) {
            // Le mot qui précède est repris pour que la correction soit lisible.
            $before = substr($t, 0, $m[0][1]);
            if (!preg_match('/(\S+)$/u', $before, $w)) {
                return;
            }
            $start = $off - mb_strlen($w[1]);
            $add($start, $w[1] . $m[1][0] . $m[2][0], $w[1] . $m[2][0], 'ponctuation', 'Pas d’espace avant ' . ($m[2][0] === ',' ? 'une virgule.' : 'un point.'));
        });
        // Espace manquante après une virgule entre deux mots : « Martin,Privat ».
        $each('/(?<![\p{L}\p{N}])(\p{L}{2,}),(\p{L}{2,})(?![\p{L}\p{N}])/u', function ($m, $off) use ($add) {
            if (preg_match('/^\p{Lu}\p{Ll}{0,2}$/u', $m[1][0]) && preg_match('/^\p{Ll}/u', $m[2][0])) {
                return; // « Bon,iface » : virgule tombée dans un nom, pas une espace oubliée
            }
            $add($off, $m[0][0], $m[1][0] . ', ' . $m[2][0], 'ponctuation', 'Une espace après la virgule.');
        });
        // Espace manquante après un point entre deux phrases : « fin.Début ».
        $each('/(?<![\p{L}.@\/])(\p{Ll}{2,})\.(\p{Lu}\p{Ll}{2,})(?![\p{L}.@\/])/u', function ($m, $off) use ($add) {
            $add($off, $m[0][0], $m[1][0] . '. ' . $m[2][0], 'ponctuation', 'Une espace après le point.');
        });
        // Apostrophe suivie d'une espace : « l' équipe » → « l'équipe ».
        $each('/(?<![\p{L}\p{N}])((?:[cdjlmnstCDJLMNST]|[qQ]u|[jJ]usqu|[lL]orsqu|[pP]uisqu|[qQ]uoiqu|[pP]resqu)([\'’])) (\p{L}+)/u', function ($m, $off) use ($add) {
            $add($off, $m[0][0], $m[1][0] . $m[3][0], 'typographie', 'Pas d’espace après une apostrophe d’élision.');
        });
        // Ordinaux : 2e, 3e, 1re, XXe (et non 2ème, 3eme, 1ère, XXème).
        $each('/(?<![\p{L}\p{N}])(\d+|[IVXLC]{1,6})(ièmes|iemes|èmes|emes|ième|ieme|ème|eme|è)(?![\p{L}\p{N}])/u', function ($m, $off) use ($add) {
            if ($m[1][0] === '1' || $m[1][0] === 'I') {
                return; // « 1ème » : « 1er » ou « 1re » selon le genre, laissé à Gemini
            }
            $plural = str_ends_with($m[2][0], 's');
            $add($off, $m[0][0], $m[1][0] . ($plural ? 'es' : 'e'), 'typographie', 'Abréviation des nombres ordinaux : on écrit « 2e », « 3e », « XXe » (et non « 2ème »).');
        });
        $each('/(?<![\p{L}\p{N}])1(ères|eres|ère|ere)(?![\p{L}\p{N}])/u', function ($m, $off) use ($add) {
            $add($off, $m[0][0], str_ends_with($m[1][0], 's') ? '1res' : '1re', 'typographie', 'Abréviation de « première » : on écrit « 1re » (et non « 1ère »).');
        });
        // « A » en début de phrase devant un mot en minuscules : c'est la préposition « À ».
        $each('/(?:^|(?<=[.!?…]\s)|(?<=\n))A (?=\p{Ll})/u', function ($m, $off) use ($add, $t) {
            $next = mb_substr($t, $off + 2, 12);
            if (preg_match('/^(t-|-t-)/u', $next)) {
                return;
            }
            $add($off, 'A', 'À', 'typographie', 'Les majuscules gardent leur accent : « À » (préposition).');
        });
        usort($out, fn ($a, $b) => $a['off'] <=> $b['off']);
        return $out;
    }

    // ------------------------------------------------------------------ vérification

    /**
     * Vérifie une liste de champs et renvoie les corrections proposées.
     *
     * @param list<array{k:string,value:string,html?:bool,lang?:string,kind?:string}> $fields
     * @param array{scope?:string,names?:list<string>,ai?:bool} $o
     * @return array{items:list<array>,engine:string,model:?string,notice:?string,calls:int}
     */
    public static function check(array $fields, array $o = []): array
    {
        $typo = (bool) Settings::get('correcteur.typography', true);
        $useAi = ($o['ai'] ?? true) && (self::$ai !== null || Gemini::ready());
        $model = $useAi ? (self::$ai !== null ? 'essai' : Gemini::model()) : null;
        $prepared = [];
        foreach (array_values($fields) as $f) {
            $text = self::norm(self::plain((string) ($f['value'] ?? ''), !empty($f['html'])));
            if (!preg_match('/\p{L}{2,}/u', $text)) {
                continue;
            }
            if (mb_strlen($text) > self::MAX_FIELD) {
                $text = mb_substr($text, 0, self::MAX_FIELD);
            }
            $prepared[] = [
                'k' => (string) $f['k'],
                'text' => $text,
                'lang' => ($f['lang'] ?? 'fr') === 'en' ? 'en' : 'fr',
                'kind' => in_array($f['kind'] ?? '', ['title', 'quote', 'caption'], true) ? $f['kind'] : 'text',
                'items' => [],
            ];
        }
        // Règles du musée (français).
        foreach ($prepared as &$p) {
            if ($p['lang'] === 'fr') {
                $p['items'] = self::rules($p['text']);
            }
        }
        unset($p);
        // Gemini : réponses en cache pour les textes déjà vérifiés, appels groupés pour les autres.
        $notice = null;
        $calls = 0;
        if ($useAi && $prepared) {
            [$ai, $notice, $calls] = self::aiCheck($prepared, (string) $model, $o['names'] ?? []);
            foreach ($ai as $i => $items) {
                $prepared[$i]['items'] = self::merge($prepared[$i]['items'], $items);
            }
        }
        // Filtres : typographie (réglage), mots protégés, corrections ignorées.
        $protected = self::protectedWords();
        foreach ($o['names'] ?? [] as $n) {
            foreach (preg_split('/[^\p{L}\p{M}]+/u', (string) $n, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
                if (mb_strlen($w) >= 2 && preg_match('/^\p{Lu}/u', $w) && !isset(self::COMMON[mb_strtolower($w)])) {
                    $protected[$w] = true;
                }
            }
        }
        $ignored = array_flip(array_merge(self::ignored($o['scope'] ?? ''), self::ignored('*')));
        $out = [];
        foreach ($prepared as $p) {
            foreach ($p['items'] as $it) {
                if (!$typo && $it['type'] === 'typographie') {
                    continue;
                }
                if (self::touchesProtected($it['wrong'], $it['right'], $protected)) {
                    continue;
                }
                $sig = self::sig($it['wrong'], $it['right']);
                if (isset($ignored[$sig])) {
                    continue;
                }
                $out[] = self::present($p, $it, $sig);
            }
        }
        return ['items' => $out, 'engine' => $useAi && $notice === null ? 'gemini' : ($useAi ? 'partiel' : 'regles'), 'model' => $model, 'notice' => $notice, 'calls' => $calls];
    }

    /** Correction prête pour l'éditeur : contexte avant / après, rang de l'occurrence. */
    private static function present(array $p, array $it, string $sig): array
    {
        $t = $p['text'];
        $off = (int) $it['off'];
        $len = mb_strlen($it['wrong']);
        $startB = max(0, $off - 40);
        $before = mb_substr($t, $startB, $off - $startB);
        $after = mb_substr($t, $off + $len, 40);
        // Contexte limité au paragraphe et coupé à un mot entier.
        if (($nl = mb_strrpos($before, "\n")) !== false) {
            $before = mb_substr($before, $nl + 1);
        } elseif ($startB > 0) {
            $before = (string) preg_replace('/^\S*\s/u', '', $before);
        }
        if (($nl = mb_strpos($after, "\n")) !== false) {
            $after = mb_substr($after, 0, $nl);
        } elseif ($off + $len + 40 < mb_strlen($t)) {
            $after = (string) preg_replace('/\s\S*$/u', '', $after);
        }
        // Rang de cette occurrence parmi les passages identiques du champ.
        $nth = mb_substr_count(mb_substr($t, 0, $off), $it['wrong']);
        return [
            'k' => $p['k'], 'wrong' => $it['wrong'], 'right' => $it['right'],
            'before' => $before, 'after' => $after, 'nth' => $nth,
            'type' => $it['type'], 'label' => self::TYPES[$it['type']] ?? 'Correction', 'why' => $it['why'], 'src' => $it['src'],
            'sig' => $sig, 'word' => self::isWord($it['wrong']) && in_array($it['type'], ['orthographe', 'frappe'], true) ? $it['wrong'] : null,
        ];
    }

    private static function isWord(string $s): bool
    {
        return (bool) preg_match('/^\p{L}[\p{L}\'’-]*$/u', $s);
    }

    public static function sig(string $wrong, string $right): string
    {
        return substr(sha1(mb_strtolower($wrong) . "\x1f" . $right), 0, 16);
    }

    /**
     * Règles et Gemini sur le même passage : une correction incluse dans une plus large est
     * fusionnée avec elle (« le 3ème but marquer » → « le 3e but marqué ») ; sinon la plus
     * large l'emporte.
     */
    private static function merge(array $rules, array $ai): array
    {
        $all = array_merge($rules, $ai);
        usort($all, fn ($a, $b) => [$a['off'], -mb_strlen($a['wrong'])] <=> [$b['off'], -mb_strlen($b['wrong'])]);
        $out = [];
        foreach ($all as $it) {
            $n = count($out);
            $last = $n ? $out[$n - 1] : null;
            if ($last && $it['off'] < $last['off'] + mb_strlen($last['wrong'])) {
                [$outer, $inner] = mb_strlen($it['wrong']) > mb_strlen($last['wrong']) ? [$it, $last] : [$last, $it];
                $out[$n - 1] = self::combine($outer, $inner) ?? $outer;
                continue;
            }
            $out[] = $it;
        }
        return $out;
    }

    /** Applique une petite correction à l'intérieur d'une plus large, si elle s'y retrouve telle quelle. */
    private static function combine(array $outer, array $inner): ?array
    {
        $rel = $inner['off'] - $outer['off'];
        if ($rel < 0 || $rel + mb_strlen($inner['wrong']) > mb_strlen($outer['wrong']) || mb_substr_count($outer['right'], $inner['wrong']) !== 1) {
            return null;
        }
        $main = $outer['src'] === 'gemini' ? $outer : ($inner['src'] === 'gemini' ? $inner : $outer);
        return [
            'off' => $outer['off'], 'wrong' => $outer['wrong'], 'right' => str_replace($inner['wrong'], $inner['right'], $outer['right']),
            'type' => $main['type'], 'why' => $outer['why'] . ' ' . $inner['why'], 'src' => $main['src'],
        ];
    }

    // ------------------------------------------------------------------ Gemini

    /**
     * @return array{0:array<int,list<array>>,1:?string,2:int} corrections par champ, message d'erreur, nombre d'appels
     */
    private static function aiCheck(array $prepared, string $model, array $extraNames): array
    {
        $result = [];
        $pending = [];
        foreach ($prepared as $i => $p) {
            $cached = self::cacheGet(self::cacheKey($p, $model));
            if ($cached !== null) {
                $result[$i] = $cached;
            } else {
                $pending[] = $i;
            }
        }
        if (!$pending) {
            return [$result, null, 0];
        }
        // Morceaux de texte (un champ long est découpé par paragraphes), regroupés par langue.
        $pieces = [];
        foreach ($pending as $i) {
            foreach (self::chunks($prepared[$i]['text'], self::BATCH) as [$off, $txt]) {
                $pieces[] = ['i' => $i, 'off' => $off, 'text' => $txt, 'lang' => $prepared[$i]['lang'], 'kind' => $prepared[$i]['kind']];
            }
        }
        $batches = [];
        foreach (['fr', 'en'] as $lang) {
            $cur = [];
            $size = 0;
            foreach ($pieces as $n => $pc) {
                if ($pc['lang'] !== $lang) {
                    continue;
                }
                $len = mb_strlen($pc['text']);
                if ($cur && $size + $len > self::BATCH) {
                    $batches[] = ['lang' => $lang, 'pieces' => $cur];
                    $cur = [];
                    $size = 0;
                }
                $cur[] = $n;
                $size += $len;
            }
            if ($cur) {
                $batches[] = ['lang' => $lang, 'pieces' => $cur];
            }
        }
        $protected = self::protectedWords();
        $jobs = [];
        foreach ($batches as $b) {
            $texts = [];
            $all = '';
            foreach ($b['pieces'] as $n) {
                $texts[] = ['id' => 't' . $n, 'nature' => ['title' => 'titre', 'quote' => 'citation', 'caption' => 'légende'][$pieces[$n]['kind']] ?? 'texte', 'texte' => $pieces[$n]['text']];
                $all .= ' ' . $pieces[$n]['text'];
            }
            $payload = ['noms_connus' => self::namesIn($all, $protected, $extraNames), 'dictionnaire' => self::dictionaryIn($all), 'textes' => $texts];
            $jobs[] = [[['role' => 'user', 'text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]], self::instructions($b['lang']), [
                'model' => $model, 'temperature' => 0.0, 'max_tokens' => 4096, 'json' => true, 'schema' => self::schema(), 'timeout' => 90,
            ]];
        }
        $answers = self::$ai ? (self::$ai)($jobs) : Gemini::generateMany($jobs);
        $notice = null;
        $failedPieces = [];
        $found = [];
        foreach ($batches as $bi => $b) {
            $a = $answers[$bi] ?? ['error' => 'pas de réponse'];
            $data = isset($a['text']) ? json_decode((string) preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim((string) $a['text'])), true) : null;
            if (!is_array($data) || !isset($data['corrections']) || !is_array($data['corrections'])) {
                $notice = !empty($a['error']) ? (string) $a['error'] : 'Gemini : réponse illisible, seules les règles de base ont été appliquées.';
                foreach ($b['pieces'] as $n) {
                    $failedPieces[$pieces[$n]['i']] = true;
                }
                continue;
            }
            $inBatch = array_flip(array_map(fn ($n) => 't' . $n, $b['pieces']));
            foreach ($data['corrections'] as $c) {
                $id = (string) ($c['id'] ?? '');
                if (!is_array($c) || !isset($inBatch[$id])) {
                    continue;
                }
                $pc = $pieces[(int) substr($id, 1)];
                foreach (self::validate($c, $pc['text']) as $it) {
                    $it['off'] += $pc['off'];
                    $found[$pc['i']][] = $it;
                }
            }
        }
        foreach ($pending as $i) {
            if (isset($failedPieces[$i])) {
                continue; // pas de cache : on réessaiera
            }
            $items = $found[$i] ?? [];
            usort($items, fn ($a, $b) => $a['off'] <=> $b['off']);
            self::cachePut(self::cacheKey($prepared[$i], $model), $items);
            $result[$i] = $items;
        }
        return [$result, $notice, count($jobs)];
    }

    /** Consignes données à Gemini. */
    private static function instructions(string $lang): string
    {
        $common = "Pour chaque faute, renvoie :\n"
            . "- « id » : l'identifiant du texte ;\n"
            . "- « faux » : l'extrait fautif recopié exactement, caractère pour caractère (accents, majuscules, apostrophes, ponctuation), le plus court possible mais unique dans le texte : ajoute un ou deux mots voisins si l'extrait apparaît plusieurs fois ;\n"
            . "- « juste » : ce même extrait corrigé, sans rien changer d'autre ;\n"
            . "- « type » : orthographe, accord, conjugaison, homophone, syntaxe, ponctuation, majuscule ou frappe ;\n"
            . "- « explication » : en français, une phrase courte et claire pour un non-spécialiste.\n"
            . "Réponds uniquement en JSON : {\"corrections\":[…]}. S'il n'y a aucune faute : {\"corrections\":[]}.";
        if ($lang === 'en') {
            return "Tu relis des textes en anglais britannique du musée en ligne du FC Sochaux-Montbéliard (club de football français fondé en 1928).\n"
                . "Signale uniquement les fautes certaines : orthographe, fautes de frappe, accords, formes verbales, confusions (its/it's, their/there, lose/loose…), mot manquant ou en trop, ponctuation fautive, majuscule manquante.\n"
                . "Ne signale pas : le style, le choix des mots, les variantes britanniques ou américaines, la typographie (apostrophes, guillemets, tirets), les noms propres (joueurs, clubs, stades, villes, journaux), les chiffres, scores, dates et minutes (33'), les sigles du football. Dans le doute, ne signale rien.\n"
                . $common;
        }
        return "Tu es correcteur professionnel pour le musée en ligne du FC Sochaux-Montbéliard (club de football fondé en 1928). Des historiens bénévoles rédigent des fiches de matchs, de joueurs, d'objets et de moments du club. Relève leurs fautes pour qu'ils puissent les corriger.\n\n"
            . "Signale uniquement les fautes certaines :\n"
            . "- orthographe d'usage et fautes de frappe ;\n"
            . "- accords (genre, nombre, participes passés), conjugaison, homophones (a/à, et/est, ou/où, ces/ses, ce/se, son/sont, on/ont, leur/leurs, quand/quant, -er/-é…) ;\n"
            . "- syntaxe : mot manquant, en trop ou répété, construction fautive, négation incomplète à l'écrit (« on a pas » → « on n'a pas ») ;\n"
            . "- ponctuation fautive ; majuscule manquante (début de phrase, nom propre).\n\n"
            . "Ne signale pas :\n"
            . "- le style, les répétitions voulues, les tournures familières, le choix des mots : ne reformule jamais une phrase correcte ;\n"
            . "- la typographie : espaces insécables, guillemets droits ou « français », apostrophes droites ou courbes, tirets, points de suspension ;\n"
            . "- l'orthographe rectifiée de 1990 ou traditionnelle : les deux sont justes ;\n"
            . "- les noms propres (joueurs, entraîneurs, clubs, stades, villes, journaux) : ceux de « noms_connus » et du « dictionnaire » sont justes ; ne corrige un autre nom que s'il s'agit d'une faute de frappe évidente sur un nom très connu ;\n"
            . "- les chiffres, scores, dates, minutes (33'), sigles et abréviations du football (J15, D1, L2, CFA, TAB, s.p., c.s.c.).\n"
            . "Dans un texte de nature « citation », garde le style oral : corrige seulement l'orthographe et les accords. Un « titre » peut ne pas avoir de verbe ni de point final.\n"
            . "Dans le doute, ne signale rien.\n\n"
            . $common;
    }

    private static function schema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'corrections' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'id' => ['type' => 'STRING'],
                            'faux' => ['type' => 'STRING'],
                            'juste' => ['type' => 'STRING'],
                            'type' => ['type' => 'STRING', 'enum' => ['orthographe', 'accord', 'conjugaison', 'homophone', 'syntaxe', 'ponctuation', 'majuscule', 'frappe']],
                            'explication' => ['type' => 'STRING'],
                        ],
                        'required' => ['id', 'faux', 'juste', 'type', 'explication'],
                    ],
                ],
            ],
            'required' => ['corrections'],
        ];
    }

    /** Découpe un texte en morceaux d'au plus $max caractères, aux fins de paragraphe puis de phrase. */
    public static function chunks(string $t, int $max): array
    {
        if (mb_strlen($t) <= $max) {
            return [[0, $t]];
        }
        $out = [];
        $pos = 0;
        $total = mb_strlen($t);
        while ($pos < $total) {
            $piece = mb_substr($t, $pos, $max);
            if ($pos + $max < $total) {
                foreach (["\n", '. ', ' '] as $sep) {
                    $cut = mb_strrpos($piece, $sep);
                    if ($cut !== false && $cut > $max / 3) {
                        $piece = mb_substr($piece, 0, $cut + mb_strlen($sep));
                        break;
                    }
                }
            }
            $out[] = [$pos, $piece];
            $pos += mb_strlen($piece);
        }
        return $out;
    }

    /**
     * Contrôle d'une correction proposée par Gemini : l'extrait doit exister dans le texte,
     * les chiffres et les mots protégés restent intacts, et ce n'est pas une réécriture.
     * Un mot mal orthographié présent plusieurs fois donne une correction par occurrence.
     * @return list<array{off:int,wrong:string,right:string,type:string,why:string,src:string}>
     */
    public static function validate(array $c, string $text): array
    {
        $wrong = (string) ($c['faux'] ?? '');
        $right = (string) ($c['juste'] ?? '');
        $type = (string) ($c['type'] ?? '');
        $why = trim((string) ($c['explication'] ?? ''));
        if ($wrong === '' || $right === '' || mb_strlen($wrong) > 200 || mb_strlen($right) > 260 || str_contains($wrong, "\n") || str_contains($right, "\n")) {
            return [];
        }
        $type = isset(self::TYPES[$type]) && $type !== 'typographie' ? $type : 'orthographe';
        // Extrait tel qu'il figure dans le texte (apostrophes, guillemets et espaces tolérés).
        $hits = self::occurrences($text, $wrong);
        if (!$hits) {
            return [];
        }
        $actual = $hits[0][1];
        if ($actual !== $wrong) {
            $right = self::sameMarks($actual, $right);
            $wrong = $actual;
        }
        if ($wrong === $right || self::typoOnly($wrong, $right)) {
            return [];
        }
        // Chiffres intacts (scores, dates, minutes).
        preg_match_all('/\d+/', $wrong, $dw);
        preg_match_all('/\d+/', $right, $dr);
        if ($dw[0] !== $dr[0]) {
            return [];
        }
        // Pas de réécriture : la correction reste proche de l'original.
        if (mb_strlen($wrong) >= 15) {
            similar_text(mb_strtolower($wrong), mb_strtolower($right), $pct);
            if ($pct < 45) {
                return [];
            }
        }
        if (count($hits) > 1 && !(mb_strlen($wrong) >= 4 && self::isWord($wrong))) {
            return []; // extrait ambigu (« a », « et ») : on ne sait pas lequel corriger
        }
        $why = $why !== '' ? mb_substr($why, 0, 240) : 'Correction proposée.';
        $out = [];
        foreach (array_slice($hits, 0, 10) as [$off]) {
            $out[] = ['off' => $off, 'wrong' => $wrong, 'right' => $right, 'type' => $type, 'why' => $why, 'src' => 'gemini'];
        }
        return $out;
    }

    /**
     * Occurrences d'un extrait dans un texte (mots entiers), apostrophes, guillemets,
     * tirets et espaces tolérés. @return list<array{0:int,1:string}> [position, extrait réel]
     */
    private static function occurrences(string $text, string $needle): array
    {
        $parts = preg_split('/(\s+|[\'’ʼ‘]|["«»“”]|[-–—])/u', $needle, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $re = '';
        foreach ($parts as $part) {
            $re .= match (true) {
                (bool) preg_match('/^\s+$/u', $part) => '\s+',
                (bool) preg_match('/^[\'’ʼ‘]$/u', $part) => '[\'’ʼ‘]',
                (bool) preg_match('/^["«»“”]$/u', $part) => '["«»“”]',
                (bool) preg_match('/^[-–—]$/u', $part) => '[-–—]',
                default => preg_quote($part, '/'),
            };
        }
        if ($re === '') {
            return [];
        }
        $l = preg_match('/^[\p{L}\p{N}]/u', $needle) ? '(?<![\p{L}\p{N}])' : '';
        $r = preg_match('/[\p{L}\p{N}]$/u', $needle) ? '(?![\p{L}\p{N}])' : '';
        if (!preg_match_all('/' . $l . $re . $r . '/u', $text, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        return array_map(fn ($x) => [mb_strlen(substr($text, 0, $x[1])), $x[0]], $m[0]);
    }

    /** Reprend dans la correction les apostrophes et guillemets de l'auteur. */
    private static function sameMarks(string $actual, string $right): string
    {
        if (str_contains($actual, '’') && !str_contains($actual, "'")) {
            $right = str_replace("'", '’', $right);
        } elseif (str_contains($actual, "'") && !str_contains($actual, '’')) {
            $right = str_replace('’', "'", $right);
        }
        return $right;
    }

    /** Différence purement typographique (apostrophes, guillemets, tirets, espaces) ? */
    private static function typoOnly(string $a, string $b): bool
    {
        $n = fn (string $s) => (string) preg_replace(['/[\'’ʼ‘]/u', '/["«»“”]/u', '/[-–—]/u', '/[\s\x{00A0}\x{202F}]+/u', '/…/u'], ["'", '"', '-', ' ', '...'], trim($s));
        return $n($a) === $n($b);
    }

    // ------------------------------------------------------------------ mots protégés

    /** Mots que le correcteur ne doit jamais modifier : dictionnaire du musée, noms connus. */
    public static function protectedWords(): array
    {
        if (self::$names !== null) {
            return self::$names;
        }
        $set = [];
        $add = function (string $s) use (&$set): void {
            foreach (preg_split('/[^\p{L}\p{M}]+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
                if (mb_strlen($w) >= 2 && preg_match('/^\p{Lu}/u', $w) && !isset(self::COMMON[mb_strtolower($w)])) {
                    $set[$w] = true;
                }
            }
        };
        foreach (Index::all() as $s) {
            if ($s['type'] === 'personne') {
                $add((string) ($s['p']['name'] ?? $s['title']));
                $add((string) ($s['p']['first'] ?? ''));
                $add((string) ($s['p']['last'] ?? ''));
            } elseif ($s['type'] === 'match') {
                $add((string) ($s['m']['home'] ?? ''));
                $add((string) ($s['m']['away'] ?? ''));
                $add((string) ($s['m']['stadium'] ?? ''));
            }
        }
        foreach (['clubs', 'stades'] as $c) {
            foreach (Collections::get($c, []) as $it) {
                $add((string) ($it['name'] ?? ''));
                $add((string) ($it['city'] ?? ''));
                foreach ((array) ($it['aliases'] ?? []) as $a) {
                    $add((string) $a);
                }
            }
        }
        foreach (array_keys(Derived::get()['unlinked'] ?? []) as $name) {
            $add(\App\Data\Names::display((string) $name));
        }
        // Dictionnaire : tous les mots, quelle que soit la casse.
        foreach (self::dictionary() as $w) {
            foreach (preg_split('/[^\p{L}\p{M}]+/u', $w, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                $set[$part] = true;
            }
        }
        return self::$names = $set;
    }

    /** Mots courants qui commencent souvent une phrase : jamais protégés. */
    private const COMMON = ['le' => 1, 'la' => 1, 'les' => 1, 'de' => 1, 'du' => 1, 'des' => 1, 'et' => 1, 'en' => 1, 'au' => 1, 'aux' => 1, 'un' => 1, 'une' => 1,
        'il' => 1, 'elle' => 1, 'ce' => 1, 'sur' => 1, 'sous' => 1, 'pour' => 1, 'par' => 1, 'avec' => 1, 'dans' => 1, 'est' => 1, 'ouest' => 1, 'nord' => 1, 'sud' => 1,
        'club' => 1, 'stade' => 1, 'football' => 1, 'saint' => 1, 'sainte' => 1, 'union' => 1, 'sporting' => 1, 'racing' => 1, 'olympique' => 1, 'association' => 1,
        'sportive' => 1, 'athletic' => 1, 'real' => 1, 'grand' => 1, 'petit' => 1, 'parc' => 1, 'des' => 1, 'stadium' => 1, 'arena' => 1, 'terrain' => 1, 'centre' => 1,
        'ville' => 1, 'ac' => 1, 'as' => 1, 'fc' => 1, 'us' => 1, 'sc' => 1, 'rc' => 1, 'cs' => 1, 'es' => 1, 'red' => 1, 'star' => 1, 'sport' => 1, 'sports' => 1,
        'national' => 1, 'municipal' => 1, 'pierre' => 1, 'son' => 1, 'sa' => 1, 'ses' => 1, 'on' => 1, 'nous' => 1, 'mais' => 1, 'ou' => 1, 'si' => 1, 'que' => 1, 'qui' => 1];

    /** La correction modifie-t-elle un mot protégé (présent dans l'original, absent de la correction) ? */
    private static function touchesProtected(string $wrong, string $right, array $protected): bool
    {
        $words = fn (string $s) => array_count_values(preg_split('/[^\p{L}\p{M}]+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $w = $words($wrong);
        $r = $words($right);
        foreach ($w as $word => $n) {
            if (isset($protected[$word]) && ($r[$word] ?? 0) < $n) {
                return true;
            }
        }
        return false;
    }

    /** Noms connus présents dans un texte (envoyés à Gemini pour qu'il ne les corrige pas). */
    private static function namesIn(string $text, array $protected, array $extra): array
    {
        $out = [];
        foreach (array_unique(preg_split('/[^\p{L}\p{M}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []) as $w) {
            if (isset($protected[$w]) && preg_match('/^\p{Lu}/u', $w)) {
                $out[] = $w;
            }
        }
        foreach ($extra as $n) {
            // Nom de la fiche (composition…) dont un élément figure dans le texte.
            foreach (preg_split('/[^\p{L}\p{M}]+/u', (string) $n, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
                if (mb_strlen($w) >= 3 && preg_match('/(?<![\p{L}])' . preg_quote($w, '/') . '(?![\p{L}])/u', $text)) {
                    $out[] = (string) $n;
                    break;
                }
            }
        }
        return array_slice(array_values(array_unique($out)), 0, 200);
    }

    /** Dictionnaire du musée (Qualité › Dictionnaire). */
    public static function dictionary(): array
    {
        if (self::$dict === null) {
            self::$dict = [];
            foreach (Collections::get('dictionnaire', []) as $it) {
                $w = trim((string) (is_array($it) ? ($it['mot'] ?? '') : $it));
                if ($w !== '') {
                    self::$dict[] = $w;
                }
            }
        }
        return self::$dict;
    }

    private static function dictionaryIn(string $text): array
    {
        return array_slice(array_values(array_filter(self::dictionary(), fn ($w) => mb_stripos($text, $w) !== false)), 0, 200);
    }

    /** Ajoute un mot au dictionnaire (bouton « Ajouter au dictionnaire » du correcteur). */
    public static function addWord(string $word, ?array $user): bool
    {
        $word = trim((string) preg_replace('/\s+/u', ' ', $word));
        if ($word === '' || mb_strlen($word) > 80 || !preg_match('/\p{L}/u', $word)) {
            return false;
        }
        $list = Collections::get('dictionnaire', []);
        foreach ($list as $it) {
            if (mb_strtolower((string) ($it['mot'] ?? '')) === mb_strtolower($word)) {
                return true;
            }
        }
        $list[] = ['mot' => $word, 'note' => 'Ajouté depuis le correcteur' . (!empty($user['name']) ? ' par ' . $user['name'] : '')];
        usort($list, fn ($a, $b) => strcasecmp(\App\Data\Paths::slug((string) ($a['mot'] ?? '')), \App\Data\Paths::slug((string) ($b['mot'] ?? ''))));
        Collections::save('dictionnaire', $list, $user, 'Mot ajouté au dictionnaire : ' . $word);
        self::$dict = null;
        self::$names = null;
        return true;
    }

    // ------------------------------------------------------------------ corrections ignorées

    /** Signatures ignorées pour une fiche (« fiche:123 ») ou un écran ; « * » : partout. */
    public static function ignored(string $scope): array
    {
        if ($scope === '') {
            return [];
        }
        $all = JsonStore::read(self::$dir . '/ignorees.json', []) ?: [];
        return array_keys($all[$scope] ?? []);
    }

    public static function ignore(string $scope, string $sig): void
    {
        if (!preg_match('/^[0-9a-f]{16}$/', $sig) || !self::validScope($scope)) {
            return;
        }
        JsonStore::update(self::$dir . '/ignorees.json', function ($all) use ($scope, $sig) {
            $all = $all ?: [];
            $all[$scope][$sig] = date('c');
            return $all;
        }, []);
        // Le décompte de la tâche de fond suit tout de suite.
        if (preg_match('/^fiche:(\d+)$/', $scope, $m)) {
            self::dropFromResult((int) $m[1], $sig);
        }
    }

    public static function validScope(string $scope): bool
    {
        return (bool) preg_match('#^(fiche:\d{1,9}|ecran:/admin/[a-z0-9/_-]{1,80})$#', $scope);
    }

    // ------------------------------------------------------------------ cache

    private static function cacheKey(array $p, string $model): string
    {
        return sha1(self::VERSION . '|' . $model . '|' . $p['lang'] . '|' . $p['kind'] . '|' . $p['text']);
    }

    private static function cacheGet(string $key): ?array
    {
        $f = self::$cacheDir . '/' . substr($key, 0, 2) . "/$key.json";
        if (!is_file($f)) {
            return null;
        }
        $d = json_decode((string) file_get_contents($f), true);
        return is_array($d) && isset($d['items']) && is_array($d['items']) ? $d['items'] : null;
    }

    private static function cachePut(string $key, array $items): void
    {
        $dir = self::$cacheDir . '/' . substr($key, 0, 2);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents("$dir/$key.json", json_encode(['at' => time(), 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /** Ménage : réponses en cache inutilisées depuis plus de six mois. */
    public static function purgeCache(int $days = 180): int
    {
        $n = 0;
        foreach (glob(self::$cacheDir . '/*/*.json') ?: [] as $f) {
            if (filemtime($f) < time() - $days * 86400) {
                @unlink($f);
                $n++;
            }
        }
        return $n;
    }

    // ------------------------------------------------------------------ fiches

    /**
     * Textes d'une fiche à vérifier : les mêmes que ceux de l'éditeur, en français.
     * @return list<array{k:string,value:string,html:bool,lang:string,kind:string,label:string}>
     */
    public static function fieldsForDoc(array $doc): array
    {
        $out = [];
        $add = function (string $k, mixed $v, string $label, bool $html = false, string $kind = 'text') use (&$out): void {
            if (is_string($v) && trim($v) !== '') {
                $out[] = ['k' => $k, 'value' => $v, 'html' => $html, 'lang' => 'fr', 'kind' => $kind, 'label' => $label];
            }
        };
        $add('title', $doc['title'] ?? '', 'Titre', false, 'title');
        $add('intro', $doc['intro'] ?? '', 'Introduction', true);
        foreach ($doc['sections'] ?? [] as $i => $s) {
            $add("sections.$i.title", $s['title'] ?? '', 'Intertitre du bloc ' . ($i + 1), false, 'title');
            $add("sections.$i.html", $s['html'] ?? '', 'Texte du bloc ' . ($i + 1), true);
        }
        $add('key_figure.text', $doc['key_figure']['text'] ?? '', 'Chiffre clé › légende');
        $add('seo.title', $doc['seo']['title'] ?? '', 'Référencement › titre', false, 'title');
        $add('seo.description', $doc['seo']['description'] ?? '', 'Référencement › description');
        $m = $doc['match'] ?? [];
        $add('match.event', $m['event'] ?? '', 'Événement');
        foreach ($m['highlights'] ?? [] as $i => $h) {
            $add("match.highlights.$i.text", is_array($h) ? ($h['text'] ?? '') : $h, 'Temps forts › action ' . ($i + 1));
        }
        foreach ($m['reactions'] ?? [] as $i => $r) {
            $add("match.reactions.$i.text", is_array($r) ? ($r['text'] ?? '') : $r, 'Réactions › citation ' . ($i + 1), true, 'quote');
        }
        foreach ($m['breves'] ?? [] as $i => $b) {
            $add("match.breves.$i", is_array($b) ? ($b['text'] ?? '') : $b, 'Brèves › n° ' . ($i + 1), true);
        }
        $p = $doc['personne'] ?? [];
        $add('personne.subtitle', $p['subtitle'] ?? '', 'Sous-titre');
        $add('personne.position', $p['position'] ?? '', 'Poste');
        foreach (['honours' => 'Palmarès', 'then' => 'Après Sochaux'] as $k => $l) {
            foreach ($p[$k] ?? [] as $i => $v) {
                $add("personne.$k.$i", $v, "$l › ligne " . ($i + 1));
            }
        }
        foreach ($p['fiche'] ?? [] as $i => $r) {
            $add("personne.fiche.$i.label", $r['label'] ?? '', 'Carte d’identité › libellé ' . ($i + 1));
            $add("personne.fiche.$i.value", $r['value'] ?? '', 'Carte d’identité › valeur ' . ($i + 1));
        }
        $add('article.heading', $doc['article']['heading'] ?? '', 'Chapeau');
        $add('article.subtitle', $doc['article']['subtitle'] ?? '', 'Sous-titre');
        $add('objet.origin', $doc['objet']['origin'] ?? '', 'Provenance');
        foreach (['gallery' => 'Galerie', 'images' => 'Images du texte'] as $k => $l) {
            foreach ($doc[$k] ?? [] as $i => $g) {
                $add("$k.$i.caption", is_array($g) ? ($g['caption'] ?? '') : '', "$l › légende " . ($i + 1), false, 'caption');
            }
        }
        foreach ($doc['embeds'] ?? [] as $i => $em) {
            $add("embeds.$i.text", $em['text'] ?? '', 'Publication intégrée ' . ($i + 1), true, 'quote');
        }
        return $out;
    }

    /** Noms propres d'une fiche (composition, joueurs liés) : à ne jamais corriger. */
    public static function namesForDoc(array $doc): array
    {
        $out = [];
        foreach ($doc['match']['lineup']['rows'] ?? [] as $r) {
            if (!empty($r['name'])) {
                $out[] = \App\Data\Names::display((string) $r['name']);
            }
        }
        foreach (['home', 'away'] as $side) {
            if (!empty($doc['match'][$side]['name'])) {
                $out[] = (string) $doc['match'][$side]['name'];
            }
        }
        if (!empty($doc['personne'])) {
            $out[] = (string) ($doc['personne']['display_name'] ?? '') ?: (string) ($doc['title'] ?? '');
        }
        return array_values(array_unique(array_filter($out)));
    }

    // ------------------------------------------------------------------ tâche de fond

    /** Résultat de la dernière vérification d'une fiche, s'il correspond à la version enregistrée. */
    public static function forFiche(array $doc): ?array
    {
        $e = self::index()[(int) ($doc['id'] ?? 0)] ?? null;
        return $e && ($e['m'] ?? null) === ($doc['modified'] ?? null) && ($e['d'] ?? '') === self::dictHash() ? $e : null;
    }

    /** @return array<int,array{m:?string,n:int,hi:int,e:string,at:int,d:string,ex:?string}> */
    public static function index(): array
    {
        return JsonStore::read(self::$dir . '/index.json', []) ?: [];
    }

    public static function dictHash(): string
    {
        return substr(sha1(implode("\n", self::dictionary()) . '|' . self::VERSION . '|' . (Settings::get('correcteur.typography', true) ? 't' : '')), 0, 10);
    }

    /**
     * Vérifie les fiches nouvelles ou modifiées depuis leur dernière vérification (puis toutes
     * les autres), dans la limite du temps donné et du plafond quotidien d'appels à Gemini
     * ($cap : autre plafond, pour un passage complet lancé à la main en ligne de commande).
     */
    public static function run(int $seconds = 40, ?int $cap = null): ?array
    {
        if (!Settings::get('correcteur.background', true)) {
            return null;
        }
        $t0 = microtime(true);
        $state = JsonStore::read(self::$dir . '/etat.json', []) ?: [];
        if (($state['day'] ?? '') !== date('Y-m-d')) {
            $state = ['day' => date('Y-m-d'), 'calls' => 0, 'pause' => $state['pause'] ?? 0];
        }
        $cap = $cap ?? max(0, (int) Settings::get('correcteur.daily_calls', 300));
        $aiOk = function () use (&$state, $cap): bool {
            return (self::$ai !== null || Gemini::ready()) && $state['calls'] < $cap && ($state['pause'] ?? 0) < time();
        };
        $index = self::index();
        $dict = self::dictHash();
        $todo = [];
        foreach (Index::all() as $id => $s) {
            if ($s['status'] === 'corbeille') {
                continue;
            }
            $e = $index[$id] ?? null;
            $stale = !$e || ($e['m'] ?? null) !== $s['modified'] || ($e['d'] ?? '') !== $dict;
            $upgrade = $e && !$stale && ($e['e'] ?? '') !== 'gemini' && (self::$ai !== null || Gemini::ready());
            if ($stale || $upgrade) {
                // Priorité : fiches jamais vérifiées ou modifiées, les plus récentes d'abord.
                $todo[$id] = [$stale ? 1 : 0, (string) ($s['modified'] ?? '')];
            }
        }
        if (!$todo) {
            return null;
        }
        uasort($todo, fn ($a, $b) => [$b[0], $b[1]] <=> [$a[0], $a[1]]);
        $done = 0;
        $found = 0;
        $changes = [];
        foreach (array_keys($todo) as $id) {
            if (microtime(true) - $t0 > $seconds) {
                break;
            }
            $doc = Fiches::get((int) $id);
            if (!$doc) {
                continue;
            }
            $useAi = $aiOk();
            if (!$useAi && $todo[$id][0] === 0) {
                continue; // vérification complète en attente de Gemini : rien à refaire avec les seules règles
            }
            $r = self::check(self::fieldsForDoc($doc), ['scope' => "fiche:$id", 'names' => self::namesForDoc($doc), 'ai' => $useAi]);
            $state['calls'] += $r['calls'];
            if ($r['notice'] && str_contains($r['notice'], 'quota')) {
                $state['pause'] = time() + 3600; // quota atteint : reprise dans une heure
            }
            $hi = self::languageCount($r['items']);
            $changes[(int) $id] = [
                'm' => $doc['modified'] ?? null, 'n' => count($r['items']), 'hi' => $hi, 'e' => $r['engine'], 'at' => time(), 'd' => $dict,
                'ex' => $r['items'] ? self::example($r['items']) : null,
            ];
            JsonStore::write(self::$dir . "/fiches/$id.json", ['m' => $doc['modified'] ?? null, 'at' => time(), 'engine' => $r['engine'], 'items' => $r['items']]);
            $done++;
            $found += count($r['items']);
        }
        if ($changes) {
            JsonStore::update(self::$dir . '/index.json', fn ($idx) => array_replace($idx ?: [], $changes), []);
        }
        JsonStore::write(self::$dir . '/etat.json', $state);
        return $done ? ['fiches' => $done, 'corrections' => $found, 'restantes' => max(0, count($todo) - $done), 'appels' => $state['calls']] : null;
    }

    public static function languageCount(array $items): int
    {
        return count(array_filter($items, fn ($x) => in_array($x['type'], self::LANGUAGE, true)));
    }

    /** Exemple affiché dans Qualité : de préférence une faute de langue plutôt que de la ponctuation. */
    private static function example(array $items): string
    {
        usort($items, fn ($a, $b) => !in_array($a['type'], self::LANGUAGE, true) <=> !in_array($b['type'], self::LANGUAGE, true));
        $it = $items[0];
        return '« ' . mb_strimwidth($it['wrong'], 0, 40, '…') . ' » → « ' . mb_strimwidth($it['right'], 0, 40, '…') . ' »';
    }

    private static function dropFromResult(int $id, string $sig): void
    {
        $file = self::$dir . "/fiches/$id.json";
        $d = JsonStore::read($file, null);
        if (!is_array($d)) {
            return;
        }
        $d['items'] = array_values(array_filter($d['items'] ?? [], fn ($x) => ($x['sig'] ?? '') !== $sig));
        JsonStore::write($file, $d);
        JsonStore::update(self::$dir . '/index.json', function ($idx) use ($id, $d) {
            $idx = $idx ?: [];
            if (isset($idx[$id])) {
                $idx[$id]['n'] = count($d['items']);
                $idx[$id]['hi'] = self::languageCount($d['items']);
                $idx[$id]['ex'] = $d['items'] ? self::example($d['items']) : null;
            }
            return $idx;
        }, []);
    }

    private static ?array $summary = null;

    /** Liste de Qualité › Orthographe et avancement de la vérification de fond. */
    public static function summary(): array
    {
        if (self::$summary !== null) {
            return self::$summary;
        }
        $index = self::index();
        $dict = self::dictHash();
        $rows = [];
        $checked = 0;
        $total = 0;
        $ai = 0;
        foreach (Index::all() as $id => $s) {
            if ($s['status'] === 'corbeille') {
                continue;
            }
            $total++;
            $e = $index[$id] ?? null;
            if (!$e || ($e['m'] ?? null) !== $s['modified'] || ($e['d'] ?? '') !== $dict) {
                continue;
            }
            $checked++;
            $ai += ($e['e'] ?? '') === 'gemini' ? 1 : 0;
            if (($e['n'] ?? 0) > 0) {
                $rows[] = ['id' => (int) $id, 'title' => $s['title'], 'n' => (int) $e['n'], 'hi' => (int) ($e['hi'] ?? 0), 'ex' => $e['ex'] ?? null, 'engine' => $e['e'] ?? ''];
            }
        }
        usort($rows, fn ($a, $b) => [$b['hi'], $b['n']] <=> [$a['hi'], $a['n']]);
        return self::$summary = ['rows' => $rows, 'checked' => $checked, 'total' => $total, 'ai' => $ai];
    }
}
