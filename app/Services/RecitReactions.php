<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Réactions d'après match reprises du récit : chaque puce (ou paragraphe) de la section « Réactions
 * d'après match » de l'onglet Récits devient une réaction (onglet « Compo et événements »), affichée
 * sur le site dans un encadré avec guillemet. Format habituel : « Nom : « citation » ».
 * - Liste vide ou seulement d'attente (« À venir », « Néant », « Pas de réactions… ») : les réactions
 *   du récit prennent la place.
 * - Liste qui reflète le récit (cas des matchs repris de l'ancien site) : elle suit le récit quand il
 *   change (correction, ajout, retrait d'une puce).
 * - Liste saisie à la main, différente du récit : jamais réécrite ; seules les puces nouvellement
 *   tapées dans le récit sont ajoutées.
 */
final class RecitReactions
{
    /** @return list<array{who:string,text:string}> réactions du récit, attentes (« À venir »…) comprises */
    public static function extract(array $doc): array
    {
        $out = [];
        foreach (\App\Front\Fiche::splitResume((array) ($doc['sections'] ?? []))['sections'] as $s) {
            if (($s['key'] ?? '') !== 'reactions') {
                continue;
            }
            $html = (string) $s['html'];
            $blocks = preg_match_all('#<(li|p)\b[^>]*>(.*?)</\1>#is', $html, $m) && $m[2] ? $m[2] : (preg_split('#<br\s*/?>|\n#i', $html) ?: []);
            foreach ($blocks as $b) {
                foreach (self::speakers((string) $b) as $one) {
                    $out = array_merge($out, self::parse($one));
                }
            }
        }
        return $out;
    }

    /**
     * Bloc coupé aux retours à la ligne (texte collé, Maj+Entrée) qui ouvrent une nouvelle réaction :
     * nom en gras suivi de « : », ou « Nom : « citation » ». Sinon la ligne continue la citation.
     * @return list<string>
     */
    private static function speakers(string $b): array
    {
        $out = [];
        foreach (preg_split('#<br\s*/?>#i', $b) ?: [$b] as $seg) {
            $plain = trim(html_entity_decode(strip_tags($seg), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $new = preg_match('#^\s*<(strong|b)\b[^>]*>[^<]{2,70}:\s*(?:&nbsp;|\s)*</\1>#iu', $seg)
                || preg_match('/^\p{Lu}[^:«»"“”]{1,60}:\s*[«"“]/u', $plain);
            if ($out && !$new) {
                $out[count($out) - 1] .= '<br>' . $seg;
                continue;
            }
            $out[] = $seg;
        }
        return $out;
    }

    /** @return list<array{who:string,text:string}> la réaction d'un bloc (aucune s'il est vide) */
    private static function parse(string $b): array
    {
        // Une seule ligne : les retours à la ligne du code ou d'un texte collé ne coupent pas la citation.
        $txt = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode(strip_tags((string) preg_replace('#<br\s*/?>#i', ' ', $b)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($txt === '') {
            return [];
        }
        $who = '';
        if (preg_match('/^([^:«»"“”]{2,70}?)\s*:\s*(.+)$/us', $txt, $mm) && !preg_match('/^\d/u', $mm[1])) {
            [$who, $txt] = [trim($mm[1]), $mm[2]];
        }
        $txt = trim($txt, " «»\"“”");
        return $txt === '' ? [] : [['who' => mb_substr($who, 0, 120), 'text' => mb_substr($txt, 0, 6000)]];
    }

    /** Réaction d'attente reprise de l'ancien site : « À venir », « Néant », « Pas de réactions pour ce match »… */
    public static function placeholder(array $r): bool
    {
        $t = mb_strtolower(trim(strip_tags((string) ($r['text'] ?? '')), " \t\n.…!«»\"“”"));
        return $t === '' || (bool) preg_match('/^(r[ée]actions? )?(à|a) venir$|^n[ée]ant$|^rien$|^-+$|^en attente$|^aucune?$|^(pas de|aucune?) r[ée]actions?\b.{0,40}$/u', $t);
    }

    /**
     * Applique au document ; true s'il a changé. $orig : la fiche avant l'enregistrement (formulaire) ;
     * sans $orig (passe unique sur tous les matchs), seules les listes vides ou d'attente sont remplies.
     */
    public static function apply(array &$doc, ?array $orig = null): bool
    {
        if (($doc['type'] ?? '') !== 'match') {
            return false;
        }
        $from = self::real(self::extract($doc));
        $real = self::real((array) ($doc['match']['reactions'] ?? []));
        if (!$real) {
            // Rien de saisi (ou seulement « À venir ») : les réactions du récit prennent la place.
            if (!$from) {
                return false;
            }
            $doc['match']['reactions'] = $from;
            return true;
        }
        if ($orig === null) {
            return false;
        }
        $before = self::real(self::extract($orig));
        if (self::same($before, $from)) {
            return false;
        }
        if (self::same($real, $before)) {
            // La liste était le reflet du récit : elle suit le récit (correction, ajout, retrait d'une puce).
            $doc['match']['reactions'] = $from;
            return true;
        }
        // Liste saisie à la main : seules les puces nouvellement tapées dans le récit sont ajoutées.
        $added = false;
        foreach ($from as $r) {
            if (!self::known($before, $r['text']) && !self::known($real, $r['text'])) {
                $real[] = $r;
                $added = true;
            }
        }
        if (!$added) {
            return false;
        }
        $doc['match']['reactions'] = $real;
        return true;
    }

    /** @return list<array> réactions hors attentes (« À venir »…) */
    private static function real(array $list): array
    {
        return array_values(array_filter($list, fn ($r) => is_array($r) && !self::placeholder($r)));
    }

    /** Mêmes réactions (auteurs et textes, à la ponctuation et à la mise en forme près) ? */
    private static function same(array $a, array $b): bool
    {
        $key = fn (array $l) => array_map(fn ($r) => preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(html_entity_decode(strip_tags((string) ($r['who'] ?? '') . '|' . (string) ($r['text'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), $l);
        return $key($a) === $key($b);
    }

    /** Citation déjà présente (mêmes mots pour l'essentiel) ? */
    private static function known(array $list, string $text): bool
    {
        $words = fn (string $t) => array_unique(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(strip_tags($t))) ?: [], fn ($w) => mb_strlen($w) > 2));
        $b = $words($text);
        foreach ($list as $r) {
            $a = $words((string) ($r['text'] ?? ''));
            if ($a && $b && count(array_intersect($a, $b)) / count(array_unique(array_merge($a, $b))) >= 0.5) {
                return true;
            }
        }
        return false;
    }
}
