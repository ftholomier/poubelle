<?php
declare(strict_types=1);

namespace App\Services;

use App\Data\Activity;
use App\Data\Fiches;
use App\Data\Media;

/**
 * Légende et crédit d'une photo, gardés à deux endroits : la médiathèque (la photo elle-même) et la
 * galerie de chaque fiche qui la montre (copie propre à la fiche). Ils sont reportés de l'un à l'autre,
 * sans jamais écraser un texte différent saisi exprès :
 * - médiathèque → fiches : une fiche qui avait la même légende (ou le même crédit) que la médiathèque
 *   suit sa modification ; une fiche qui a encore l'ancienne légende avec le crédit dedans (ancien
 *   site : « Maïga, Monsoreau, Janot Photo L’Est républicain ») prend le découpage légende + crédit ;
 *   un champ vide de la fiche est complété ;
 * - fiche → médiathèque : de même dans l'autre sens, quand un historien enregistre une fiche (photo
 *   ajoutée avec sa légende et son crédit, ou légende corrigée), puis vers les autres fiches qui
 *   montrent la même photo avec la même légende.
 */
final class CaptionSync
{
    public const AUTHOR = 'Légendes et crédits des photos';

    /** Mots qui annoncent un crédit dans une légende (« Photo », « Crédit photo », « Cliché »…). */
    private const MARK = '(?:cr[ée]dits? photos?|photos? cr[ée]dits?|cr[ée]dits?|photos?|ph|clich[ée]s?|source)';

    /** Pour comparer : minuscules, apostrophes droites, ponctuation et espaces réduits. */
    public static function norm(string $s): string
    {
        $s = mb_strtolower(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $s = str_replace(['’', '‘', '`', '´', 'ʼ'], "'", $s);
        return trim((string) preg_replace('/[\s.,;:!?()\[\]«»"\-–—©\/]+/u', ' ', $s));
    }

    /**
     * $old est-elle la légende d'origine dont $caption + $credit sont le découpage (crédit écrit dans
     * la légende, en fin : « … Photo L’Est républicain », ou en tête : « ©AGENCE ; texte ») ?
     */
    public static function isSplitOf(string $old, string $caption, string $credit): bool
    {
        $o = self::norm($old);
        $c = self::norm($caption);
        $k = self::norm($credit);
        if ($k === '' || $o === '' || $o === $c) {
            return false;
        }
        if (str_ends_with($o, $k)) {
            $rest = trim(substr($o, 0, -strlen($k)));
            if (trim((string) preg_replace('/(?:^|\s)' . self::MARK . '$/u', '', $rest)) === $c) {
                return true;
            }
        }
        return str_starts_with($o, $k) && trim(substr($o, strlen($k))) === $c;
    }

    private static function pair(?array $a): array
    {
        return ['caption' => trim((string) ($a['caption'] ?? '')), 'credit' => trim((string) ($a['credit'] ?? ''))];
    }

    /**
     * Ce que devient la légende et le crédit d'une photo de fiche quand la médiathèque passe de $old à
     * $new ; null si rien ne change (texte propre à la fiche gardé).
     */
    public static function follow(array $item, array $old, array $new): ?array
    {
        $cur = self::pair($item);
        $old = self::pair($old);
        $new = self::pair($new);
        $out = $cur;
        if ($cur['credit'] === '' && $new['credit'] !== '' && self::isSplitOf($cur['caption'], $new['caption'], $new['credit'])) {
            $out = $new;
        } else {
            foreach (['caption', 'credit'] as $k) {
                if ($new[$k] !== '' && ($cur[$k] === '' || self::norm($cur[$k]) === self::norm($old[$k]))) {
                    $out[$k] = $new[$k];
                } elseif ($new[$k] === '' && $old[$k] !== '' && self::norm($cur[$k]) === self::norm($old[$k])) {
                    $out[$k] = ''; // retiré de la médiathèque, la fiche avait le même texte
                }
            }
        }
        return $out === $cur ? null : $out;
    }

    /**
     * Médias modifiés : fiches qui les montrent mises à jour (galerie), sauf $except.
     * @param array<string,array{old:array,new:array}> $changes chemin => valeurs d'avant et d'après
     * @return list<int> fiches modifiées
     */
    public static function toFiches(array $changes, ?array $user, ?int $except = null): array
    {
        $byFiche = [];
        $usage = Media::usage();
        foreach ($changes as $rel => $ch) {
            foreach ((array) ($usage[$rel] ?? []) as $id) {
                if (is_int($id) && $id !== $except) {
                    $byFiche[$id][] = (string) $rel;
                }
            }
        }
        $done = [];
        foreach ($byFiche as $id => $rels) {
            $doc = Fiches::fresh($id);
            if (!$doc || ($doc['status'] ?? '') === 'corbeille' || empty($doc['gallery'])) {
                continue;
            }
            $changed = false;
            foreach ($doc['gallery'] as $i => $g) {
                $rel = (string) ($g['image'] ?? '');
                if (!in_array($rel, $rels, true) || !($v = self::follow((array) $g, $changes[$rel]['old'], $changes[$rel]['new']))) {
                    continue;
                }
                $doc['gallery'][$i] = array_merge((array) $g, $v);
                $changed = true;
            }
            if ($changed) {
                Fiches::save($doc, $user, 'Légende et crédit des photos repris de la médiathèque');
                $done[] = $id;
            }
        }
        return $done;
    }

    /**
     * Fiche enregistrée par un historien : légendes et crédits de sa galerie reportés dans la
     * médiathèque (champ vide complété ; texte qui était celui de la fiche avant la modification mis
     * à jour ; ancienne légende avec le crédit dedans remplacée par le découpage de la fiche), puis
     * vers les autres fiches qui montraient la même photo avec la même légende.
     * @return list<string> médias mis à jour
     */
    public static function fromFiche(?array $before, array $after, ?array $user): array
    {
        $prev = [];
        foreach ((array) ($before['gallery'] ?? []) as $g) {
            $prev[(string) ($g['image'] ?? '')] ??= self::pair((array) $g);
        }
        $changes = [];
        foreach ((array) ($after['gallery'] ?? []) as $g) {
            $rel = (string) ($g['image'] ?? '');
            $m = $rel !== '' && !isset($changes[$rel]) ? Media::get($rel) : null;
            if (!$m) {
                continue;
            }
            $cur = self::pair($m);
            $was = $prev[$rel] ?? null;
            $out = self::pushed($cur, self::pair((array) $g), $was);
            if ($out !== $cur) {
                $changes[$rel] = ['old' => $cur, 'new' => $out];
            }
        }
        if (!$changes) {
            return [];
        }
        Media::putMany(array_map(fn ($c) => $c['new'], $changes));
        \App\Data\Derived::markDirty();
        Activity::log($user, 'a repris dans la médiathèque la légende et le crédit de ' . count($changes) . ' photo' . (count($changes) > 1 ? 's' : '') . ' de la fiche', $after);
        self::toFiches($changes, $user, (int) ($after['id'] ?? 0));
        return array_keys($changes);
    }

    /** Légende et crédit de la médiathèque ($media) après report de ceux d'une fiche ($fiche, $was avant modification). */
    private static function pushed(array $media, array $fiche, ?array $was): array
    {
        if ($media['credit'] === '' && $fiche['credit'] !== '' && self::isSplitOf($media['caption'], $fiche['caption'], $fiche['credit'])) {
            return $fiche;
        }
        $out = $media;
        foreach (['caption', 'credit'] as $k) {
            if ($fiche[$k] === '') {
                continue; // un champ vidé dans la fiche n'efface rien dans la médiathèque
            }
            $changedInFiche = $was !== null && self::norm($was[$k]) !== self::norm($fiche[$k]);
            if ($media[$k] === '' || ($changedInFiche && self::norm($media[$k]) === self::norm($was[$k]))) {
                $out[$k] = $fiche[$k];
            }
        }
        return $out;
    }

    /**
     * Texte qu'avait la photo $rel dans une version précédente de la fiche, quand il est celui de la
     * médiathèque ($media) : preuve que la médiathèque n'a pas suivi une modification faite dans la fiche.
     */
    private static function previous(int $id, string $rel, array $media): ?array
    {
        foreach (Fiches::versions($id) as $v) {
            $doc = Fiches::version($id, (int) ($v['n'] ?? 0));
            foreach ((array) ($doc['gallery'] ?? []) as $g) {
                if ((string) ($g['image'] ?? '') !== $rel) {
                    continue;
                }
                $p = self::pair((array) $g);
                if (($media['caption'] === '' || self::norm($p['caption']) === self::norm($media['caption']))
                    && ($media['credit'] === '' || self::norm($p['credit']) === self::norm($media['credit']))) {
                    return $p;
                }
            }
        }
        return null;
    }

    /**
     * Rattrapage (une fois) : photos des fiches et de la médiathèque remises d'accord. D'abord la
     * médiathèque complétée d'après les fiches (champ vide seulement, ou ancienne légende avec le
     * crédit dedans ; jamais quand deux fiches proposent des textes différents), puis chaque fiche
     * d'après la médiathèque (même règle que follow(), sans valeur « d'avant »).
     * @param list<int>|null $only seulement ces fiches (essais) ; $dry : compter sans rien écrire
     * @return array{media:int,fiches:list<int>}
     */
    public static function catchUp(?array $only = null, bool $dry = false): array
    {
        $ids = [];
        foreach ($only === null ? Media::usage() : [$only] as $list) {
            foreach ((array) $list as $id) {
                if (is_int($id)) {
                    $ids[$id] = true;
                }
            }
        }
        ksort($ids);
        // 1. Médiathèque ← fiches.
        $proposals = [];
        foreach (array_keys($ids) as $id) {
            $doc = Fiches::get($id);
            if (!$doc || ($doc['status'] ?? '') === 'corbeille') {
                unset($ids[$id]);
                continue;
            }
            foreach ((array) ($doc['gallery'] ?? []) as $g) {
                $rel = (string) ($g['image'] ?? '');
                $m = $rel !== '' ? Media::get($rel) : null;
                if (!$m) {
                    continue;
                }
                $cur = self::pair($m);
                $item = self::pair((array) $g);
                // Médiathèque restée sur un ancien texte de cette fiche (légende corrigée ou crédit
                // ajouté dans la fiche avant ce report) : retrouvé dans l'historique de la fiche.
                $was = $cur !== $item && ($cur['caption'] !== '' || $cur['credit'] !== '') ? self::previous($id, $rel, $cur) : null;
                $out = self::pushed($cur, $item, $was);
                if ($out !== $cur) {
                    $proposals[$rel][json_encode($out, JSON_UNESCAPED_UNICODE)] = $out;
                }
            }
        }
        $media = [];
        foreach ($proposals as $rel => $p) {
            if (count($p) === 1) { // deux fiches en désaccord : on ne choisit pas
                $media[$rel] = reset($p);
            }
        }
        if ($media && !$dry) {
            Media::putMany($media);
        }
        // 2. Fiches ← médiathèque.
        $fiches = [];
        foreach (array_keys($ids) as $id) {
            $doc = Fiches::fresh($id);
            if (!$doc || empty($doc['gallery'])) {
                continue;
            }
            $changed = false;
            foreach ($doc['gallery'] as $i => $g) {
                $rel = (string) ($g['image'] ?? '');
                $m = $dry && isset($media[$rel]) ? $media[$rel] : Media::get($rel);
                if ($m && ($v = self::follow((array) $g, ['caption' => '', 'credit' => ''], self::pair($m)))) {
                    $doc['gallery'][$i] = array_merge((array) $g, $v);
                    $changed = true;
                }
            }
            if ($changed) {
                if (!$dry) {
                    Fiches::save($doc, ['name' => self::AUTHOR], 'Légende et crédit des photos repris de la médiathèque');
                }
                $fiches[] = $id;
            }
        }
        if (!$dry && ($media || $fiches)) {
            \App\Data\Derived::markDirty();
        }
        return ['media' => count($media), 'fiches' => $fiches];
    }
}
