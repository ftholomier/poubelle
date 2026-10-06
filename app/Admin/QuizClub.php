<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\QuizLive;

/**
 * Interactif › Quiz du club-house : créer une partie (nombre de questions, temps de réponse,
 * langue, origine des questions), ouvrir son grand écran, voir les parties en cours, les effacer.
 */
final class QuizClub extends Base
{
    public static function index(Request $req): Response
    {
        return self::html('admin/collections/quiz-live', ['games' => QuizLive::all(), 'new' => $req->str('nouvelle')],
            ['title' => 'Quiz du club-house', 'crumb' => 'Interactif', 'nav' => 'quizlive']);
    }

    /** URL du grand écran d'une partie (lien secret de l'animateur). */
    public static function screenUrl(array $g): string
    {
        return ($g['lang'] === 'en' ? '/en' : '') . '/interactif/quiz-live/ecran/' . $g['code'] . '/' . $g['key'] . '/';
    }

    public static function action(Request $req): Response
    {
        $action = $req->str('action');
        if ($action === 'creer') {
            $mix = in_array($req->str('origine'), ['mix', 'site', 'fiches'], true) ? $req->str('origine') : 'mix';
            $r = QuizLive::create((int) $req->str('questions', '12'), (int) $req->str('duree', '20'), $req->str('langue'), $mix, (string) (Auth::user()['name'] ?? ''));
            $g = QuizLive::get($r['code']);
            if (!$g || !$g['questions']) {
                QuizLive::delete($r['code']);
                return self::back('/admin/quiz-club-house', null, 'Aucune question disponible : vérifiez le quiz du site (Interactif › Quiz, frise, carte…).');
            }
            return self::back('/admin/quiz-club-house?nouvelle=' . $r['code'], 'Partie ' . $r['code'] . ' créée : ' . count($g['questions']) . ' questions. Ouvrez le grand écran sur l’ordinateur relié à la télé.');
        }
        if ($action === 'supprimer') {
            QuizLive::delete($req->str('code'));
            return self::back('/admin/quiz-club-house', 'Partie effacée.');
        }
        return self::back('/admin/quiz-club-house', null, 'Action inconnue.');
    }
}
