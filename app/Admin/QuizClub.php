<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\QuizChampionship;
use App\Services\QuizLive;

/**
 * Interactif › Quiz du club-house : créer une partie (nombre de questions, temps de réponse,
 * langue, origine des questions), ouvrir son grand écran, voir les parties en cours, les effacer.
 */
final class QuizClub extends Base
{
    public static function index(Request $req): Response
    {
        $d = QuizChampionship::data();
        $banned = [];
        foreach ($d['players'] as $id => $p) {
            if (!empty($p['banned'])) {
                $banned[] = ['id' => (string) $id, 'pseudo' => $p['pseudo']];
            }
        }
        return self::html('admin/collections/quiz-live', ['games' => QuizLive::all(), 'new' => $req->str('nouvelle'),
            'season' => QuizChampionship::season(), 'ranking' => QuizChampionship::ranking(), 'stats' => QuizChampionship::stats(), 'banned' => $banned],
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
            $r = QuizLive::create((int) $req->str('questions', '12'), (int) $req->str('duree', '20'), $req->str('langue'), $mix, (string) (Auth::user()['name'] ?? ''), $req->str('amicale') === '1');
            $g = QuizLive::get($r['code']);
            if (!$g || !$g['questions']) {
                QuizLive::delete($r['code']);
                return self::back('/admin/quiz-club-house', null, 'Aucune question disponible : vérifiez le quiz du site (Interactif › Quiz, frise, carte…).');
            }
            return self::back('/admin/quiz-club-house?nouvelle=' . $r['code'], 'Partie ' . $r['code'] . ' créée : ' . count($g['questions']) . ' questions. Ouvrez le grand écran sur l’ordinateur relié à la télé.');
        }
        $id = $req->str('joueur');
        if (in_array($action, ['renommer', 'exclure', 'reintegrer'], true) && QuizChampionship::player($id)) {
            $name = (string) QuizChampionship::player($id)['pseudo'];
            match ($action) {
                'renommer' => QuizChampionship::resetPseudo($id),
                'exclure' => QuizChampionship::ban($id, true),
                'reintegrer' => QuizChampionship::ban($id, false),
            };
            return self::back('/admin/quiz-club-house#championnat', match ($action) {
                'renommer' => '« ' . $name . ' » remplacé par « ' . QuizChampionship::player($id)['pseudo'] . ' » : le joueur peut choisir un autre pseudo.',
                'exclure' => '« ' . $name . ' » retiré du classement : ses prochaines parties ne comptent plus.',
                default => '« ' . $name . ' » de retour au classement.',
            });
        }
        if ($action === 'supprimer') {
            QuizLive::delete($req->str('code'));
            return self::back('/admin/quiz-club-house', 'Partie effacée.');
        }
        return self::back('/admin/quiz-club-house', null, 'Action inconnue.');
    }
}
