<?php
declare(strict_types=1);

namespace App\Services;

/**
 * « Régie relit pour vous » : vérifications avant publication d'une annonce.
 * Purement local et déterministe — aucune API, aucun blocage : ce sont des
 * conseils affichés à l'auteur, pas des erreurs de formulaire.
 */
final class JobReview
{
    /**
     * @return array<int, array{level:string, message:string}>
     */
    public static function check(array $job): array
    {
        $notes = [];
        $text = mb_strtolower(
            (string) $job['title'] . ' ' . (string) $job['description'] . ' ' . (string) ($job['conditions'] ?? ''),
        );

        // 1. Le statut du poste doit être explicite.
        $statusWords = ['intermittent', 'cddu', "cdd d'usage", 'cdd d’usage', 'guso', 'cachet',
                        'cdi', 'cdd', 'stage', 'contrat'];
        if (!self::containsAny($text, $statusWords) && empty($job['contract'])) {
            $notes[] = ['level' => 'wait', 'message' => I18n::t('review.status')];
        }

        // 2. La rémunération doit être annoncée.
        $hasSalary = trim((string) ($job['salary'] ?? '')) !== '';
        $payWords = ['€', 'euros', 'rémunération', 'remuneration', 'salaire', 'cachet',
                     'smic', 'défraiement', 'defraiement', 'brut', 'net'];
        if (!$hasSalary && !self::containsAny($text, $payWords)) {
            $notes[] = ['level' => 'wait', 'message' => I18n::t('review.salary')];
        }

        // 3. Écriture inclusive : on signale un intitulé genré sans forme épicène.
        $title = mb_strtolower((string) $job['title']);
        $inclusive = preg_match('/\((?:e|se|trice|euse|ve)\)|·|\.e\b|h\s*\/\s*f|f\s*\/\s*h|·e\b|\bou\b/iu', $title) === 1;
        $gendered = preg_match('/\b(technicien|r[ée]gisseur|cadreur|monteur|chef|assistant|com[ée]dien|'
                             . 'costumier|machiniste|[ée]lectricien|animateur|vendeur|directeur)\b/iu', $title) === 1;
        if ($gendered && !$inclusive) {
            $notes[] = ['level' => 'wait', 'message' => I18n::t('review.inclusive')];
        }

        // 4. Une description trop courte n'attire pas de candidature.
        if (mb_strlen(trim((string) $job['description'])) < 180) {
            $notes[] = ['level' => 'wait', 'message' => I18n::t('review.short')];
        }

        // 5. Un lieu, même approximatif, est attendu.
        if (trim((string) ($job['location']['city'] ?? '')) === ''
            && trim((string) ($job['location']['region'] ?? '')) === ''
            && empty($job['location']['remote'])) {
            $notes[] = ['level' => 'wait', 'message' => I18n::t('review.place')];
        }

        if ($notes === []) {
            $notes[] = ['level' => 'ok', 'message' => I18n::t('review.ok')];
        }
        return $notes;
    }

    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }
}
