<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Crédit écrit dans la légende d'une photo (« Gomel Photo Jean-Yves Caddedu », « Jacky Colin.
 * Crédit Est Républicain. », « ©PHOTOPQR/L'ALSACE ; texte ») : séparé en légende + crédit (nom seul).
 */
final class CaptionCredit
{
    private const MARK = '(?:cr[ée]dits?\s+photos?|photos?\s+cr[ée]dits?|cr[ée]dits?|photos?|ph\.|clich[ée]|source)';

    /** @return array{caption:string,credit:string}|null */
    public static function split(string $caption): ?array
    {
        $c = trim(preg_replace('/\s+/u', ' ', $caption));
        if ($c === '') {
            return null;
        }
        // « ©AGENCE/Auteur ; texte de la légende » (crédit en tête). Séparateur : « ; » ou un tiret
        // entouré d'espaces (« ©Jean-Pierre Dupont ; … » garde son prénom composé).
        if (preg_match('/^©\s*([^;]{2,80}?)\s*(?:;|\s[\-–—]\s)\s*(.+)$/u', $c, $m)) {
            return ['caption' => self::tidy($m[2]), 'credit' => trim($m[1], " \t.,;:-–—()")];
        }
        // Crédit en fin de légende : « … Photo Prénom Nom », « … Crédit photo L’Est Républicain. », « … © Nom ».
        $re = '/^(.*?)(?:^|[\s.,;:(\-–—])((?i:' . self::MARK . ')\s*[:\-–]?\s*|©\s*)((?:[lLdD][’\']\s?)?[\p{Lu}0-9][^.;()]{1,70}?)\s*\)?\s*\.?\s*$/u';
        if (!preg_match($re, $c, $m)) {
            return null;
        }
        // Mot « photo » au fil de la phrase (« pour la photo Officielle 1938 », « Hommage à Photo Club
        // Montbéliard ») : ce n'est un crédit qu'après une ponctuation ou en tête, ou, après une simple
        // espace, derrière un nom propre ou un nombre (« Gomel Photo Laurent Rea », « Corchia photo
        // Canal+ » : la forme la plus courante des légendes reprises), jamais devant « Club ».
        $before = rtrim($m[1]);
        if ($before !== '' && !preg_match('/[.,;:!?…»)(\-–—]$/u', $before) && !str_starts_with($m[2], '©')) {
            $last = (string) preg_replace('/^.*\s/u', '', $before);
            if (!preg_match('/^[\p{Lu}0-9]/u', $last) || preg_match('/^club\b/iu', $m[3])) {
                return null;
            }
        }
        $credit = self::name($m[3]);
        $words = count(preg_split('/\s+/u', $credit));
        if ($credit === '' || $words > 7) {
            return null;
        }
        return ['caption' => self::tidy($m[1]), 'credit' => $credit];
    }

    private static function name(string $s): string
    {
        $s = trim($s, " \t.,;:-–—()");
        return (string) preg_replace('/^(?:' . self::MARK . ')\s*[:\-–]?\s*/iu', '', $s);
    }

    private static function tidy(string $s): string
    {
        $s = trim($s, " \t,;:-–—(");
        return $s === '.' ? '' : $s;
    }
}
