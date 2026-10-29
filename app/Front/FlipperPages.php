<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Club;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\Carnet;
use App\Services\Flipper;
use App\Services\I18n;
use App\Services\QuizChampionship;

/**
 * Le flipper du musée : la page /interactif/flipper/ (jeu 3D en plein écran, public/assets/js/flipper.js)
 * et l'API /api/flipper (jeton de partie, score, tableau d'honneur, entrée au classement avec le
 * compte supporter). Jamais en cache : la page dépend du compte ouvert sur l'appareil.
 */
final class FlipperPages
{
    public static function page(Request $req): Response
    {
        $c = Carnet::current();
        $me = $c ? QuizChampionship::player($c['id']) : null;
        $cfg = Flipper::config();
        $res = Pages::render('interactif/flipper', [
            'data' => self::data($cfg, $me !== null, (bool) $c),
            'title' => t($cfg['title']),
            'welcome' => isset($req->query['bienvenue']) && $me,
            'pseudo' => $me['pseudo'] ?? null,
        ], [
            'title' => t($cfg['title']),
            'description' => t('Un vrai flipper en 3D aux couleurs {club_de} : bumpers, rampe, cibles, multiball et tableau d’honneur. Sur téléphone, tablette ou ordinateur.'),
            'active' => 'interactif', 'body_class' => 'page-flipper', 'bare' => true, 'no_cookie' => true, 'no_chat' => true,
            'styles' => ['css/flipper.css'], 'scripts' => ['js/flipper.js'],
        ]);
        $res->headers['Cache-Control'] = 'private, no-store';
        return $res;
    }

    /** Données du jeu pour le navigateur : textes traduits, couleurs, lettres, faits du musée. */
    private static function data(array $cfg, bool $member, bool $account): array
    {
        $letters = implode('-', str_split($cfg['letters']));
        $digits = implode('-', str_split($cfg['digits']));
        return [
            'lang' => I18n::lang(),
            'api' => '/api/flipper',
            'url' => base_url() . url('/interactif/flipper/'),
            'title' => t($cfg['title']),
            'subtitle' => Club::s('site') . ' · ' . t('depuis {y}', ['y' => Club::s('founded')]),
            'logo' => (string) Club::get('logo_400', '/assets/img/logo-sochaux-retro-400.png'),
            'colors' => ['navy' => Club::color('navy'), 'yellow' => Club::color('yellow'), 'blue' => Club::color('blue')],
            'letters' => $cfg['letters'],
            'digits' => $cfg['digits'],
            'labels' => [
                'ramp' => mb_strtoupper(t($cfg['ramp'])), 'bumpers' => mb_strtoupper(t($cfg['bumpers'])), 'bumperCap' => mb_strtoupper(t($cfg['cap'])),
                'targets' => mb_strtoupper(t('Abattez les cibles {l}', ['l' => $letters])), 'cup' => mb_strtoupper(t('La Coupe')),
                'kickback' => mb_strtoupper(t($cfg['kickback'])), 'plunger' => mb_strtoupper(t('Coup d’envoi')),
                'ramp2' => mb_strtoupper(t($cfg['ramp2'])), 'orbit' => mb_strtoupper(t($cfg['orbit'])), 'rampName' => t($cfg['ramp']),
            ],
            'facts' => Flipper::facts(),
            'member' => $member,
            'account' => $account,
            // Textes de l'écran à points (règles du jeu).
            'text' => [
                'player' => t('Joueur {n}'), 'ball' => t('Bille {n}/{t}'), 'launchHint' => t('Tirez le lanceur'), 'tiltLost' => t('Bille perdue'),
                'warning' => t('Attention !'), 'lion' => t($cfg['roar']), 'lionSub' => t('Points doublés pendant 25 secondes'),
                'skill' => t('Coup d’envoi parfait'), 'mult' => t('Bonus ×{n}'), 'kickbackLit' => t('{k} allumé', ['k' => t($cfg['kickback'])]),
                'extraBall' => t('Bille en plus !'), 'qualified' => t('Qualifiés !'), 'finaleLit' => t('Finale : visez la Coupe !'),
                'nextRound' => t('Prochain tour : {r}'), 'r32' => t('32es de finale'), 'r16' => t('16es de finale'), 'r8' => t('8es de finale'),
                'r4' => t('Quarts de finale'), 'r2' => t('Demi-finale'), 'final' => t('Finale'), 'multiball' => t('Multiball : jackpot sur la rampe'),
                'cupHole' => t('La Coupe'), 'kickback' => t($cfg['kickback']), 'kickbackSub' => t('Les jeunes sauvent la bille'),
                'saved' => t('Bille sauvée'), 'cupWon' => t('Coupe gagnée !'), 'cupWonSub' => t('Le trophée est à vous'), 'cupLost' => t('Finale perdue'),
                'bonus' => t('Bonus'), 'again' => t('Rejouez !'), 'jackpotHint' => t('Jackpot sur les rampes'), 'cupRound' => t('Épopée en Coupe · {r}'),
                'ramp2' => t($cfg['ramp2']), 'promo' => t('Promotion !'), 'combo' => t('Combo !'), 'orbit' => t($cfg['orbit']),
            ],
            'ui' => [
                'insertCoin' => t('Appuyez sur « Jouer »'), 'over' => t('Fin de partie'), 'record' => t('Record'), 'p' => t('J'), 'ball' => t('Bille'),
                'soundOn' => t('Allumer le son'), 'soundOff' => t('Couper le son'), 'newRecord' => t('Nouveau record personnel !'),
                'multiNoRank' => t('Les parties à plusieurs ne comptent pas au tableau d’honneur.'), 'empty' => t('Personne encore : à vous d’ouvrir le tableau !'),
                'loading' => t('Chargement…'), 'offline' => t('Connexion perdue, réessayez.'), 'copied' => t('Score copié : collez-le où vous voulez.'),
                'shareText' => t('{n} points au {t} !', ['t' => t($cfg['title'])]),
                'mailed' => t('Un lien vient d’être envoyé à cette adresse : ouvrez-le sur cet appareil pour entrer au tableau d’honneur (pensez aux indésirables).'),
                'joined' => t('C’est noté : vos prochaines parties comptent au tableau d’honneur.'),
                'missions' => [
                    'skill' => t('Coup d’envoi parfait'), 'lanes' => t('Couloirs {d}', ['d' => $digits]), 'letters' => t('Cibles {l}', ['l' => $letters]),
                    'lion' => rtrim(t($cfg['roar']), ' !'), 'jackpot' => t('Jackpot'), 'coupe' => t('Coupe gagnée'), 'extra' => t('Bille en plus'),
                    'promo' => t('Promotion'), 'orbit' => t($cfg['orbit']), 'combo' => t('Combo'),
                ],
            ],
        ];
    }

    public static function api(Request $req): Response
    {
        $in = $req->json() ?: [];
        if (($in['lang'] ?? '') === 'en') {
            I18n::set('en');
        }
        $action = (string) ($in['action'] ?? '');
        $c = Carnet::current();
        $me = $c ? QuizChampionship::player($c['id']) : null;
        switch ($action) {
            case 'commencer':
                // Une salle (club-house, mode soirée) partage parfois une connexion : limite large.
                if (!RateLimiter::hit('flipper', $req->ip(), 600, 3600)) {
                    return Response::json(['error' => t('Trop de demandes, réessayez plus tard.')], 429);
                }
                return Response::json(['ok' => true, 'tok' => Flipper::token()]);
            case 'score':
                if (!RateLimiter::hit('flipper-score', $req->ip(), 300, 3600)) {
                    return Response::json(['error' => t('Trop de demandes, réessayez plus tard.')], 429);
                }
                $tok = (string) ($in['tok'] ?? '');
                $score = (int) ($in['score'] ?? -1);
                $week = Flipper::week();
                $top = fn () => Flipper::publicRows(Flipper::ranking($week), $c['id'] ?? null);
                if (!$c || !$me) {
                    // Pas encore de compte (ou pas de pseudo) : le joueur peut entrer au tableau ;
                    // le même jeton reste valable pour enregistrer ce score juste après.
                    $g = Flipper::check($tok);
                    $err = $g ? Flipper::plausible($score, $g['t']) : t('Partie expirée : rejouez pour entrer au tableau d’honneur.');
                    return Response::json(['ok' => true, 'ranked' => false, 'join' => $err === null, 'error' => $err, 'top' => $top()]);
                }
                $err = Flipper::record($c['id'], (string) $me['pseudo'], $score, $tok);
                if ($err) {
                    return Response::json(['ok' => false, 'ranked' => false, 'error' => $err, 'top' => $top()]);
                }
                $rank = Flipper::rankOf($week, $c['id']);
                $label = !QuizChampionship::confirmed($c['id'])
                    ? t('Dernière étape : ouvrez le lien reçu par e-mail (pensez aux indésirables). Votre score apparaîtra alors au tableau d’honneur.')
                    : ($rank ? t('{r} cette semaine au tableau d’honneur', ['r' => ordinal($rank)]) : '');
                return Response::json(['ok' => true, 'ranked' => true, 'rank' => $rank, 'label' => $label, 'top' => $top()]);
            case 'classement':
                $mine = $c['id'] ?? null;
                return Response::json(['ok' => true, 'week' => Flipper::publicRows(Flipper::ranking(Flipper::week()), $mine),
                    'all' => Flipper::publicRows(Flipper::ranking('all'), $mine)]);
            case 'inscrire':
                // Pseudo (compte ouvert sans pseudo) ou entrée au classement avec e-mail + pseudo.
                $name = (string) ($in['name'] ?? '');
                if ($c) {
                    $err = QuizChampionship::setPseudo($c['id'], $name);
                    return $err ? Response::json(['error' => $err], 422) : Response::json(['ok' => true, 'member' => true]);
                }
                $acc = QuizLivePages::account($req, trim((string) ($in['email'] ?? '')), $name, 'flipper');
                if (isset($acc['error'])) {
                    return Response::json(['error' => $acc['error']], $acc['status']);
                }
                return Response::json(['ok' => true, 'member' => $acc['cid'] !== null, 'mailed' => $acc['mailed']]);
        }
        return Response::json(['error' => t('Ressource introuvable')], 404);
    }
}
