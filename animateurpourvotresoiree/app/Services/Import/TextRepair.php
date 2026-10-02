<?php
declare(strict_types=1);

namespace App\Services\Import;

/**
 * Répare les textes abîmés de l'ancienne base :
 *  - accents remplacés par « ? » (soir?e → soirée), grâce à un dictionnaire construit
 *    à partir des textes sains de la base elle-même ;
 *  - apostrophes typographiques perdues (d?animation → d'animation) ;
 *  - « ? » isolé valant « à » (20 h ? 21 h) ou « € » (2,40 ? par mois) ;
 *  - retours à la ligne perdus « rn » (musiciens.rnIl → musiciens.\nIl) ;
 *  - apostrophes doublées par d'anciens échappements (LIVEN''''UP → LIVEN'UP).
 */
final class TextRepair
{
    /** @var array<string,array<string,int>> squelette => [mot => fréquence] */
    private array $skeletons = [];
    private array $stats = ['accents' => 0, 'apostrophes' => 0, 'a' => 0, 'euro' => 0, 'rn' => 0];

    public function learn(string $text): void
    {
        if ($text === '' || !preg_match('/[^\x00-\x7F]/', $text)) {
            return;
        }
        if (!preg_match_all('/[\p{L}]+/u', $text, $m)) {
            return;
        }
        foreach ($m[0] as $word) {
            if (!preg_match('/[^\x00-\x7F]/', $word) || mb_strlen($word) < 2) {
                continue;
            }
            $lw = mb_strtolower($word);
            $sk = $this->skeleton($lw);
            $this->skeletons[$sk][$lw] = ($this->skeletons[$sk][$lw] ?? 0) + 1;
        }
    }

    public function seed(array $words): void
    {
        foreach ($words as $w) {
            $lw = mb_strtolower($w);
            $sk = $this->skeleton($lw);
            $this->skeletons[$sk][$lw] = ($this->skeletons[$sk][$lw] ?? 0) + 3;
        }
    }

    private function skeleton(string $lower): string
    {
        return (string) preg_replace('/[^\x00-\x7F]/u', '?', $lower);
    }

    public function export(): array
    {
        $out = [];
        foreach ($this->skeletons as $sk => $words) {
            arsort($words);
            $best = array_key_first($words);
            if ($best !== null && ($words[$best] >= 2 || count($words) === 1)) {
                $out[$sk] = $best;
            }
        }
        return $out;
    }

    public function import(array $map): void
    {
        $this->skeletons = [];
        foreach ($map as $sk => $w) {
            $this->skeletons[$sk] = [$w => 1];
        }
    }

    public function stats(): array
    {
        return $this->stats;
    }

    public function fix(string $s): string
    {
        if ($s === '') {
            return $s;
        }
        $s = preg_replace("/'{2,}/", "'", $s) ?? $s;
        // retours à la ligne « rn » (échappements perdus)
        if (str_contains($s, 'rn')) {
            $before = $s;
            $s = preg_replace('/(?<=[\p{L}\d.!?:;,)»"\'\s>])(?:rn){2,}(?=[\p{Lu}\d\-•*(«"\s<]|$)/u', "\n\n", $s) ?? $s;
            $s = preg_replace('/(?<=[.!?:;,)»"\d])rn(?=[\p{L}\d\-•*(«"<])/u', "\n", $s) ?? $s;
            $s = preg_replace('/(?<=[\p{Ll}])rn(?=[\p{Lu}][\p{Ll}])/u', "\n", $s) ?? $s;
            $s = preg_replace('/^rn|rn$/u', '', $s) ?? $s;
            if ($s !== $before) {
                $this->stats['rn']++;
            }
        }
        if (!str_contains($s, '?')) {
            return $s;
        }
        // mots avec accents perdus
        $s = preg_replace_callback('/[\p{L}]*\?[\p{L}?]*/u', function ($m) {
            $w = $m[0];
            if (!preg_match('/\p{L}/u', $w)) {
                return $w;
            }
            $trailing = '';
            // « bientôt? » : le ? final peut être une vraie ponctuation
            $sk = mb_strtolower($w);
            $cand = $this->best($sk);
            if ($cand === null && str_ends_with($w, '?')) {
                $trailing = '?';
                $sk = mb_substr($sk, 0, -1);
                $w = mb_substr($w, 0, -1);
                $cand = str_contains($sk, '?') ? $this->best($sk) : null;
                if ($cand === null) {
                    return $m[0];
                }
            }
            if ($cand === null) {
                return $m[0];
            }
            $this->stats['accents']++;
            return $this->applyCase($w, $cand) . $trailing;
        }, $s) ?? $s;
        // apostrophe (élision devant voyelle ou h muet) : d?animation, l?équipe, qu?il, aujourd?hui
        $s = preg_replace_callback('/\b(aujourd|jusqu|lorsqu|puisqu|quoiqu|presqu|qu|[dlnmtsjcDLNMTSJC])\?(?=[aeiouyhàâäéèêëîïôöùûüœAEIOUYHÀÂÄÉÈÊËÎÏÔÖÙÛÜŒ])/u', function ($m) {
            $this->stats['apostrophes']++;
            return $m[1] . "'";
        }, $s) ?? $s;
        // montants : 2,40 ? par mois / 50 ? HT
        $s = preg_replace_callback('/(\d+[,.]\d{1,2})\s?\?|(\d+)\s?\?(?=\s*(?:HT\b|TTC\b|par\s|\/))/u', function ($m) {
            $this->stats['euro']++;
            return ($m[1] !== '' ? $m[1] : $m[2]) . ' €';
        }, $s) ?? $s;
        // « ? » isolé suivi d'une minuscule ou d'un chiffre : préposition « à »
        $s = preg_replace_callback('/(^|[\s(])\?(?=\s+[\p{Ll}\d])/u', function ($m) {
            $this->stats['a']++;
            return $m[1] . 'à';
        }, $s) ?? $s;
        $s = preg_replace_callback('/(^|\s)\?(?=\s+(?:La|Le|Les|L\'|Paris|Lyon|Marseille)\b)/u', function ($m) {
            $this->stats['a']++;
            return $m[1] . 'à';
        }, $s) ?? $s;
        return $s;
    }

    private function best(string $skLower): ?string
    {
        $words = $this->skeletons[$skLower] ?? null;
        if (!$words) {
            return null;
        }
        arsort($words);
        return (string) array_key_first($words);
    }

    private function applyCase(string $original, string $lower): string
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $original) ?? '';
        if ($letters !== '' && $letters === mb_strtoupper($letters) && mb_strlen($letters) > 1) {
            return mb_strtoupper($lower);
        }
        $first = mb_substr($original, 0, 1);
        if ($first !== mb_strtolower($first)) {
            return mb_strtoupper(mb_substr($lower, 0, 1)) . mb_substr($lower, 1);
        }
        return $lower;
    }

    /** Mots fréquents du domaine, pour compléter le dictionnaire. */
    public static function baseWords(): array
    {
        return ['à', 'soirée', 'soirées', 'été', 'déjà', 'très', 'après', 'fête', 'fêtes', 'réception', 'événement', 'évènement', 'événements', 'évènements', 'matériel', 'qualité', 'spécialisé', 'expérience', 'général', 'numéro', 'téléphone', 'intéressé', 'intéressée', 'privée', 'privé', 'année', 'années', 'idée', 'idées', 'région', 'réservé', 'réservée', 'détails', 'précis', 'précision', 'créé', 'réalisé', 'répertoire', 'variété', 'variétés', 'équipe', 'électronique', 'éclairage', 'première', 'deuxième', 'dernière', 'frère', 'sœur', 'père', 'mère', 'cœur', 'œuvre', 'bébé', 'noël', 'mariée', 'mariés', 'journée', 'génial', 'activité', 'activités', 'spécialité', 'personnalisé', 'personnalisée', 'animé', 'animée', 'animés', 'souhaité', 'souhaitée', 'organisé', 'organisée', 'thématique', 'café', 'théâtre', 'apéritif', 'dîner', 'déjeuner', 'goûter', 'âge', 'âgés', 'élèves', 'écoles', 'école', 'société', 'sociétés', 'durée', 'tarifé', 'été', 'diffusé', 'demandé', 'proposé', 'prévu', 'prévue', 'réponse', 'réponses', 'merci', 'pénible', 'possibilité', 'disponibilité', 'disponibilités', 'intéresse', 'intéresserait', 'préférence', 'désolé', 'désolée', 'réunion', 'thème', 'thèmes', 'célébrer', 'célébration', 'rétro', 'génération', 'dansée', 'chorégraphie', 'déguisé', 'déguisée', 'déguisement', 'repérage', 'sécurité', 'santé', 'piñata', 'karaoké', 'karaokés', 'côté', 'là', 'où', 'ça', 'voilà', 'déroulement', 'lieu-dit', 'récemment', 'accédé', 'réservée', 'datés', 'été', 'été', 'mis', 'à jour'];
    }
}
