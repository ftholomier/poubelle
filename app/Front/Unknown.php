<?php
declare(strict_types=1);

namespace App\Front;

use App\Data\Names;

/**
 * « xx » de l'ancien site : information inconnue au moment de la saisie (date, lieu, minute,
 * taille, score…). Jamais affichés ni lus à voix haute : la phrase est réécrite sans eux
 * (« né le xx/xx/1925 à Aulnoye » → « né en 1925 à Aulnoye »), ou la ligne disparaît quand il
 * ne reste rien à dire. La fiche enregistrée n'est pas modifiée : l'écran Qualité les liste
 * pour qu'on les complète.
 */
final class Unknown
{
    /**
     * Marques d'information inconnue : « xx » isolé (ou « XX »), année « 19xx », taille « 1mxx »,
     * rang « xxè » (en minuscules : « XXe siècle » est un vrai siècle), score « x-x ».
     * « XXL », « Maxxsport » ou un identifiant de vidéo ne sont pas concernés.
     */
    public const RE = '/\b[xX]{2,}\b|\b\d{1,3}x{2,}\b|\b\d\s?m\s?x{2,}\b|\bx{2,}(?:e|è|ème|eme|er|ère)\b|\bx-x\b/u';

    private const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    private const MONTHS_EN = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    /** Référence d'un match : « Sochaux - Strasbourg du xx/09/1990 : x-x - But à la xx' ». */
    private const MATCH = '#^(?<teams>.*?)\s*\b(?:du|on)\s+(?<d>[xX]{2,}|\d{1,2}(?:er)?)\s*/\s*(?<m>[xX]{2,}|\d{1,2})\s*/\s*(?<y>[xX]{2,}|\d{2,4})\b\s*:?\s*(?<rest>.*)$#su';

    public static function has(mixed $v): bool
    {
        $s = is_string($v) ? $v : (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // Chaque marque contient « xx » (ou « x-x ») : sans eux, pas besoin de l'expression complète.
        return (stripos($s, 'xx') !== false || str_contains($s, 'x-x')) && preg_match(self::RE, $s);
    }

    /**
     * Ligne courte (fiche d'identité, naissance, décès, sous-titre, référence d'un match) sans
     * information inconnue ; '' quand il ne reste rien d'utile (« né le xx à xx », « Poids xx kg »).
     */
    public static function line(string $v): string
    {
        if (!self::has($v)) {
            return $v;
        }
        // Référence d'un match (deux équipes séparées par un tiret) : réécrite, ou rien si l'adversaire manque.
        if (preg_match(self::MATCH, $v, $m) && preg_match('/[-–]/u', $m['teams'])) {
            return self::matchLine($m, self::english($v));
        }
        return self::clean($v);
    }

    /** Texte suivi (temps forts, paragraphes) : minute inconnue (« xx’ : »), ligne « Arbitre : xx », autres marques. */
    public static function text(string $t): string
    {
        if (!self::has($t)) {
            return $t;
        }
        if (preg_match('/^\s*[^:]{1,40}:\s*(?:M\.\s*)?[xX]{2,}\s*$/u', $t)) {
            return '';
        }
        // Espaces de bord gardés : le texte est souvent pris entre deux balises.
        preg_match('/^\s*/u', $t, $a);
        preg_match('/\s*$/u', $t, $z);
        $t = (string) preg_replace('/\s*\b[xX]{2,}\s*[’\']\s*:?\s*/u', ' ', $t);
        $mid = self::clean(trim($t));
        return $mid === '' ? '' : $a[0] . $mid . $z[0];
    }

    /**
     * Texte riche : seuls les nœuds de texte sont touchés (balises et attributs intacts) ; un bloc
     * qui contenait une marque et n'a plus de texte, ou plus que son étiquette (« Arbitre : »),
     * disparaît, comme les éléments devenus vides.
     */
    public static function html(string $html): string
    {
        if (!self::has(self::plain($html))) {
            return $html;
        }
        $out = (string) preg_replace_callback('#<(p|h[1-6]|li|blockquote|dd|dt|figcaption)\b([^>]*)>(.*?)</\1>#isu', function ($m) {
            if (!self::has(self::plain($m[3]))) {
                return $m[0];
            }
            $inner = self::textNodes($m[3]);
            $left = trim(self::plain($inner));
            return $left === '' || preg_match('/^[^:]{1,60}:$/u', $left) ? '' : '<' . $m[1] . $m[2] . '>' . $inner . '</' . $m[1] . '>';
        }, $html);
        $out = self::textNodes($out);
        for ($i = 0; $i < 3; $i++) {
            $out = (string) preg_replace('#<(ul|ol|p|li|h[1-6]|blockquote|strong|em|b|u|i|span)(?:\s[^>]*)?>\s*</\1>#u', '', $out);
        }
        return trim(strip_tags($out, '<img><iframe><video><audio><table><figure><svg>')) === '' ? '' : $out;
    }

    /**
     * Fiche prête à afficher sans information inconnue (page, PDF, résumé audio, partage).
     * La fiche enregistrée n'est pas modifiée.
     */
    public static function doc(array $doc): array
    {
        $probe = $doc;
        unset($probe['legacy'], $probe['i18n']);
        if (!self::has($probe)) {
            return $doc;
        }
        // Chiffre clé : « … en xx matchs » se retire ; sinon jamais rempli, pas affiché.
        if (!empty($doc['key_figure']) && is_array($doc['key_figure'])) {
            $k = $doc['key_figure'];
            $text = (string) preg_replace('/\s+(?:en|sur|in)\s+[xX]{2,}\s+(?:matchs?|matches|rencontres?|saisons?|apparitions?|games?)\b/u', '', (string) ($k['text'] ?? ''));
            $doc['key_figure'] = self::has($k['number'] ?? '') || self::has($text) ? null : ['text' => $text] + $k;
        }
        if (isset($doc['match']) && is_array($doc['match'])) {
            $doc['match'] = self::match($doc['match']);
        }
        if (isset($doc['personne']) && is_array($doc['personne'])) {
            $doc['personne'] = self::person($doc['personne']);
        }
        if (self::has($doc['intro'] ?? '')) {
            $doc['intro'] = self::html((string) $doc['intro']);
        }
        foreach (is_array($doc['sections'] ?? null) ? $doc['sections'] : [] as $i => $sec) {
            if (is_array($sec) && self::has($sec['html'] ?? '')) {
                $doc['sections'][$i]['html'] = self::html((string) $sec['html']);
            }
        }
        return $doc;
    }

    private static function match(array $m): array
    {
        foreach (['referee', 'stadium', 'round_text', 'spectators_text', 'event'] as $k) {
            if (self::has($m[$k] ?? '')) {
                $m[$k] = '';
            }
        }
        // Buteurs : « … ; xx pour la Sélection » ou « Dupont 12’, xx 45’ » → équipes et buteurs connus seulement.
        if (self::has($m['goals_text'] ?? '')) {
            $parts = [];
            foreach (preg_split('/\s*;\s*/u', (string) $m['goals_text']) ?: [] as $part) {
                if (preg_match('/^(.*?)(\s+(?:pour|for)\s+.+)$/u', $part, $g)) {
                    $who = self::scorers($g[1]);
                    if ($who !== '' && !self::has($g[2])) {
                        $parts[] = $who . $g[2];
                    }
                } elseif (($c = self::scorers($part)) !== '') {
                    $parts[] = $c;
                }
            }
            $m['goals_text'] = implode(' ; ', $parts);
        }
        if (!empty($m['goals']) && is_array($m['goals'])) {
            $goals = [];
            foreach ($m['goals'] as $g) {
                if (is_array($g) && self::has($g['scorers'] ?? '')) {
                    $g['scorers'] = self::scorers((string) $g['scorers']);
                    if ($g['scorers'] === '' || self::has($g['team'] ?? '')) {
                        continue;
                    }
                }
                $goals[] = $g;
            }
            $m['goals'] = $goals;
        }
        if (!empty($m['header_extra']) && is_array($m['header_extra'])) {
            $m['header_extra'] = array_values(array_filter(array_map(fn ($l) => self::text((string) $l), $m['header_extra']), fn ($l) => trim($l) !== ''));
        }
        // Réactions et brèves : texte simple ou texte riche.
        foreach (['reactions', 'breves'] as $k) {
            if (empty($m[$k]) || !is_array($m[$k])) {
                continue;
            }
            $list = [];
            foreach ($m[$k] as $r) {
                $t = is_array($r) ? (string) ($r['text'] ?? '') : (string) $r;
                if (self::has($t)) {
                    $t = str_contains($t, '<') ? self::html($t) : self::text($t);
                    if (trim(strip_tags($t)) === '') {
                        continue;
                    }
                    $r = is_array($r) ? ['text' => $t] + $r : $t;
                }
                $list[] = $r;
            }
            $m[$k] = $list;
        }
        // Temps forts : minute inconnue retirée ; action réduite à rien (hors but) retirée.
        if (!empty($m['highlights']) && is_array($m['highlights'])) {
            $list = [];
            foreach ($m['highlights'] as $h) {
                if (is_array($h)) {
                    if (self::has($h['minute'] ?? '')) {
                        $h['minute'] = '';
                    }
                    if (self::has($h['text'] ?? '')) {
                        $h['text'] = self::text((string) $h['text']);
                        if (trim($h['text']) === '' && empty($h['goal'])) {
                            continue;
                        }
                    }
                }
                $list[] = $h;
            }
            $m['highlights'] = $list;
        }
        return $m;
    }

    private static function person(array $p): array
    {
        if (self::has($p['subtitle'] ?? '')) {
            $p['subtitle'] = self::line((string) $p['subtitle']);
        }
        foreach (['birth', 'death'] as $k) {
            if (!is_array($p[$k] ?? null)) {
                continue;
            }
            if (self::has($p[$k]['text'] ?? '')) {
                $p[$k]['text'] = self::line((string) $p[$k]['text']);
            }
            if (is_array($p[$k]['place'] ?? null)) {
                foreach (['text', 'city'] as $f) {
                    if (self::has($p[$k]['place'][$f] ?? '')) {
                        $p[$k]['place'][$f] = $f === 'text' ? self::line((string) $p[$k]['place'][$f]) : '';
                    }
                }
            }
            if (is_array($p[$k]['date'] ?? null) && self::has($p[$k]['date']['text'] ?? '')) {
                $p[$k]['date']['text'] = self::dateText((string) $p[$k]['date']['text']);
            }
        }
        // Fiche d'identité : ligne sans rien d'utile retirée.
        if (!empty($p['fiche']) && is_array($p['fiche'])) {
            $rows = [];
            foreach ($p['fiche'] as $r) {
                if (is_array($r) && self::has($r['value'] ?? '')) {
                    $r['value'] = self::line((string) $r['value']);
                    if ($r['value'] === '') {
                        continue;
                    }
                }
                $rows[] = $r;
            }
            $p['fiche'] = $rows;
        }
        if (self::has($p['nickname_text'] ?? '')) {
            $p['nickname_text'] = self::line((string) $p['nickname_text']);
        }
        // Repères des « matchs marquants » : gardés seulement s'il reste une référence complète
        // (deux équipes et la date du jour), sinon c'est le modèle de l'ancien site jamais rempli.
        foreach (['first_match', 'last_match', 'first_goal', 'first_match_coached', 'last_match_coached'] as $k) {
            if (self::has($p[$k] ?? '')) {
                $v = self::line((string) $p[$k]);
                $p[$k] = preg_match('#\s[-–]\s.*\b\d{1,2}/\d{1,2}/\d{4}\b#u', $v) ? $v : '';
            }
        }
        if (self::has($p['height'] ?? '')) {
            $p['height'] = '';
        }
        return $p;
    }

    /** Buteurs : « Dupont 12’, xx 45’ » → « Dupont 12’ » ; minute inconnue retirée (« Dupont xx’ » → « Dupont »). */
    private static function scorers(string $s): string
    {
        $s = (string) preg_replace('/\s*\b[xX]{2,}\s*[’\']/u', '', $s);
        $s = (string) preg_replace('/(?:^|\s*,\s*|\s+et\s+|\s+and\s+)[xX]{2,}\b(?:\s*\d+\s*[’\'])*/u', '', $s);
        $s = trim((string) preg_replace('/^(?:[\s,;–-]|et\s|and\s)+|(?:[\s,;–-]|\set|\sand)+$/u', '', $s));
        return self::has($s) ? '' : $s;
    }

    /** Date écrite (naissance, décès) : « xx/09/1990 » → « septembre 1990 », « xx » → ''. */
    private static function dateText(string $t): string
    {
        $t = trim($t);
        if (preg_match('#(?:^|\s)[xX]{2,}\s*/\s*(\d{1,2})\s*/\s*(\d{4})\s*$#u', $t, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
            return self::MONTHS[(int) $m[1] - 1] . ' ' . $m[2];
        }
        if (preg_match('/(\p{L}{3,})\s+(\d{4})\s*$/u', $t, $m) && in_array(mb_strtolower($m[1]), self::MONTHS, true)) {
            return mb_strtolower($m[1]) . ' ' . $m[2];
        }
        return preg_match('/\b(\d{4})\s*$/u', $t, $m) ? $m[1] : '';
    }

    /** Équipes et date d'un match : la date incomplète se dit en mois ou en année ; rien si l'adversaire ou l'année manquent. */
    private static function matchLine(array $m, bool $en): string
    {
        $teams = trim($m['teams']);
        $sides = preg_split('/\s+[-–]\s+/u', $teams) ?: [];
        if (count($sides) !== 2 || in_array('', array_map('trim', $sides), true) || self::has($teams) || self::has($m['y'])) {
            return ''; // modèle de l'ancien site jamais rempli : rien à montrer
        }
        $when = self::partialDate($m['d'], $m['m'], $m['y'], $en) ?? (($en ? 'on ' : 'du ') . $m['d'] . '/' . $m['m'] . '/' . $m['y']);
        $rest = (string) preg_replace(
            ['/^(?:x-x|[xX]{2,}\s*-\s*[xX]{2,})(?=\s|$)/u', '/\bà\s+la\s+[xX]{2,}\s*[’\']?\s*et\s+/u', '/\s*\bà\s+la\s+[xX]{2,}\s*[’\']?/u'],
            '',
            trim($m['rest'])
        );
        $rest = (string) preg_replace('/^[\s:;,–-]+|[\s:;,–-]+$/u', '', $rest);
        if (self::has($rest) || preg_match('/^buts?$/iu', $rest)) {
            $rest = '';
        }
        return $teams . ' ' . $when . ($rest !== '' ? ' : ' . $rest : '');
    }

    /** Date dont le jour ou le mois manque : « en septembre 1990 », « en 1925 » ; null si elle est complète. */
    private static function partialDate(string $d, string $m, string $y, bool $en): ?string
    {
        $dx = self::has($d);
        $mx = self::has($m);
        if (!$dx && !$mx) {
            return null;
        }
        $month = !$mx && (int) $m >= 1 && (int) $m <= 12 ? ($en ? self::MONTHS_EN : self::MONTHS)[(int) $m - 1] . ' ' : '';
        return ($en ? 'in ' : 'en ') . ($dx ? $month : '') . $y;
    }

    /** Ligne ou phrase sans marque : dates incomplètes, lieu inconnu dont on connaît le pays, mots devenus orphelins. */
    private static function clean(string $v): string
    {
        $en = self::english($v);
        $in = $en ? 'in' : 'en';
        // « le xx/xx/1925 » → « en 1925 », « le xx/09/1990 » → « en septembre 1990 »
        $v = (string) preg_replace_callback('#\b(?:le|du|on)\s+([xX]{2,}|\d{1,2}(?:er)?)\s*/\s*([xX]{2,}|\d{1,2})\s*/\s*(\d{4})\b#u', fn ($m) => self::partialDate($m[1], $m[2], $m[3], $en) ?? $m[0], $v);
        // « le xx xx 1940 », « le xx 1907 » → « en 1940 » ; « le xx juillet 1940 » → « en juillet 1940 »
        $v = (string) preg_replace(['/\b(?:le|on)\s+[xX]{2,}\s+(?:[xX]{2,}\s+)?(\d{4})\b/u', '/\b(?:le|on)\s+[xX]{2,}\s+(\p{L}+)\s+(\d{4})\b/u'], ["$in \$1", "$in \$1 \$2"], $v);
        // « le (années 20) » → « dans les années 20 »
        $v = (string) preg_replace('/\ble\s+\((années\s+\d{2,4})\)/u', 'dans les $1', $v);
        // Lieu inconnu, pays connu : « à xx (Hongrie) », « à (Nigéria) » → « en Hongrie », « au Nigéria »
        $v = (string) preg_replace_callback('/\s*\b(?:à|in|at)\s+(?:[xX]{2,}\s*)?\(([^()\d]+)\)/u', fn ($m) => ' ' . self::inCountry(trim($m[1]), $en), $v);
        // Minute de but inconnue (« But à la xx et à la 89’ » → « But à la 89’ »), score inconnu (« x-x »).
        $v = (string) preg_replace(['/\bà\s+la\s+[xX]{2,}\s*[’\']?\s*et\s+/u', '/\s*\bà\s+la\s+[xX]{2,}\s*[’\']?/u', '/\s*\b(?:x-x|[xX]{2,}\s*-\s*[xX]{2,})\b/u'], '', $v);
        $v = (string) preg_replace('/\s*[-–]\s*buts?\s*$/iu', '', $v);
        // Mots qui n'introduisent plus rien : « le xx », « à xx »…
        $v = (string) preg_replace('/\s*\b(?:le|les|à|en|au|aux|du|de|des|vers|on|in|at|around|about)\s+[xX]{2,}\b/u', '', $v);
        $v = (string) preg_replace(self::RE, '', $v);
        $v = (string) preg_replace(['/\(\s*[’\'?]?\s*\)/u', '/\s{2,}/u'], ['', ' '], $v);
        $v = (string) preg_replace('/^[\s,;:\/–-]+|[\s,;:\/–-]+$/u', '', $v);
        if (preg_match('/^\((.*)\)$/u', $v, $m)) {
            $v = trim($m[1]); // « xx (Italie ?) » → « Italie ? »
        }
        // Plus rien d'utile : un mot seul (« né », « kg », « juillet »).
        return preg_match('/^(?:n[ée]e?|born|kg|cm|m|ans?|years?|' . implode('|', self::MONTHS) . ')$/iu', $v) ? '' : $v;
    }

    /** « en Hongrie », « au Nigéria », « aux Pays-Bas » (« in Hungary » en anglais). */
    private static function inCountry(string $c, bool $en): string
    {
        $q = preg_match('/\s*\?$/u', $c) ? ' ?' : '';
        $c = trim((string) preg_replace('/\s*\?$/u', '', $c));
        if ($en) {
            return 'in ' . $c . $q;
        }
        $k = Names::ascii($c);
        $prep = match (true) {
            (bool) preg_match('/^(pays[- ]bas|etats[- ]unis|emirats|philippines|comores|seychelles|maldives|antilles|bahamas)/', $k) => 'aux',
            (bool) preg_match('/^[aeiouy]/', $k) => 'en',
            in_array($k, ['mexique', 'cambodge', 'mozambique', 'zimbabwe', 'belize'], true) => 'au',
            str_ends_with($k, 'e') => 'en',
            default => 'au',
        };
        return "$prep $c$q";
    }

    /** Ligne anglaise (fiche traduite) : prépositions et mois anglais. */
    private static function english(string $v): bool
    {
        return (bool) preg_match('/\b(?:born|died|on|in|at)\b/iu', $v) && !preg_match('/\b(?:né|née|décédé|décédée|le|à|du)\b/iu', $v);
    }

    private static function plain(string $html): string
    {
        return html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** text() appliqué aux seuls nœuds de texte : balises et attributs (même entre guillemets) intacts. */
    private static function textNodes(string $html): string
    {
        $parts = preg_split('/(<(?:[^<>"\']|"[^"]*"|\'[^\']*\')*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        foreach ($parts as $i => $p) {
            if ($i % 2 === 0 && $p !== '' && self::has(html_entity_decode($p, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                $parts[$i] = htmlspecialchars(self::text(html_entity_decode($p, ENT_QUOTES | ENT_HTML5, 'UTF-8')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }
        return implode('', $parts);
    }
}
