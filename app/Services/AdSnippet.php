<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Lecture du code fourni par AdSense.
 *
 * L'éditeur colle le bloc tel que Google le lui donne ; on en extrait les
 * quelques valeurs utiles plutôt que de le réinjecter dans les pages.
 *
 * Réinjecter tel quel coûterait cher : un <script> collé obligerait à ouvrir
 * la politique de sécurité de la page à l'inline, ce qui rouvrirait la porte
 * aux injections que cette politique ferme. En relisant les valeurs et en
 * réécrivant nous-mêmes le balisage, on obtient exactement le même rendu avec
 * une politique intacte — et le code reste correct même si Google change la
 * forme de son extrait.
 */
final class AdSnippet
{
    /**
     * @return array{client:string, slot:string, format:string, layout:string,
     *               mode:string, notes:string[]}
     */
    public static function parse(string $snippet): array
    {
        $out = [
            'client' => '', 'slot' => '', 'format' => '', 'layout' => '',
            'mode'   => 'auto', 'notes' => [],
        ];

        $snippet = trim($snippet);
        if ($snippet === '') {
            return $out;
        }

        // L'identifiant éditeur apparaît soit dans l'URL du script, soit sur
        // l'unité ; les deux graphies mènent au même compte.
        if (preg_match('/ca-pub-(\d{10,20})/', $snippet, $m) === 1) {
            $out['client'] = 'ca-pub-' . $m[1];
        } else {
            $out['notes'][] = 'Aucun identifiant éditeur (ca-pub-…) trouvé dans ce code.';
        }

        if (preg_match('/data-ad-slot\s*=\s*["\']?(\d{6,20})/', $snippet, $m) === 1) {
            $out['slot'] = $m[1];
            $out['mode'] = 'slots';
        }

        if (preg_match('/data-ad-format\s*=\s*["\']([a-z-]{2,20})["\']/i', $snippet, $m) === 1) {
            $out['format'] = strtolower($m[1]);
        }

        if (preg_match('/data-ad-layout-key\s*=\s*["\']([^"\']{2,64})["\']/', $snippet, $m) === 1) {
            $out['layout'] = $m[1];
        }

        if ($out['slot'] === '') {
            $out['notes'][] = 'Aucune unité (data-ad-slot) dans ce code : c’est l’extrait des '
                            . 'annonces automatiques. Le mode automatique sera retenu.';
        }
        if ($out['format'] === 'fluid' && $out['layout'] === '') {
            $out['notes'][] = 'Format « fluid » sans clé de mise en page : cette unité est prévue '
                            . 'pour un flux et ne se remplira pas ailleurs.';
        }

        return $out;
    }

    /** Ce qui sera réellement posé dans les pages, en une phrase. */
    public static function summary(array $parsed): string
    {
        if ($parsed['client'] === '') {
            return 'Rien d’exploitable dans ce code.';
        }
        if ($parsed['mode'] === 'auto') {
            return sprintf('Compte %s, annonces automatiques : Google place les annonces lui-même.',
                $parsed['client']);
        }
        return sprintf('Compte %s, unité %s%s : elle sert tous les emplacements du site.',
            $parsed['client'], $parsed['slot'],
            $parsed['format'] !== '' ? ' (format ' . $parsed['format'] . ')' : '');
    }
}
