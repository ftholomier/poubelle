<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Club;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\Babyfoot;
use App\Services\Carnet;
use App\Services\Flipper;
use App\Services\I18n;
use App\Services\QuizChampionship;

/**
 * Le flipper du musée « au stade » : la page /interactif/flipper/ (jeu 3D en plein écran, public/assets/js/flipper.js)
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
            'description' => t('Un flipper en 3D au milieu d’un stade, aux couleurs {club_de} : une épopée en Coupe, des buts à marquer sur le mini-terrain, multiball et tableau d’honneur. Sur téléphone, tablette ou ordinateur.'),
            'active' => 'interactif', 'body_class' => 'page-flipper', 'bare' => true, 'no_cookie' => true, 'no_chat' => true,
            'styles' => ['css/flipper.css'], 'scripts' => ['js/flipper.js'],
        ]);
        $res->headers['Cache-Control'] = 'private, no-store';
        return $res;
    }

    /**
     * Données du jeu pour le navigateur : textes traduits, couleurs et tenue du club, adversaires de
     * la Coupe (tirés de l'histoire du club), faits du musée.
     */
    private static function data(array $cfg, bool $member, bool $account): array
    {
        $bf = Babyfoot::config();
        $opps = array_map(fn ($o) => ['name' => $o['name'], 'ini' => $o['ini'], 'kit' => $o['kit']], Babyfoot::opponents(12));
        $up = fn (string $s) => mb_strtoupper(t($s));
        return [
            'lang' => I18n::lang(),
            'api' => '/api/flipper',
            'url' => base_url() . url('/interactif/flipper/'),
            'title' => t($cfg['title']),
            'site' => Club::s('site'),
            'short' => $bf['short'],
            'kit' => $bf['kit'],
            'opponents' => $opps,
            'logo' => (string) Club::get('logo_400', '/assets/img/logo-sochaux-retro-400.png'),
            'colors' => ['navy' => Club::color('navy'), 'yellow' => Club::color('yellow'), 'blue' => Club::color('blue')],
            // Textes peints sur le plateau (rampes, poches, couloirs, tablier).
            'labels' => [
                'passe' => $up('Passe'), 'dribble' => $up('Dribble'), 'centre' => $up('Centre'), 'ballonLong' => $up('Ballon long'),
                'bumpers' => mb_strtoupper(t($cfg['bumpers'])), 'bumperCap' => mb_strtoupper(t($cfg['cap'])),
                'home' => $bf['short'], 'away' => $up('Visiteurs'), 'combo' => 'COMBO', 'balls' => t('Billes'),
                'halves' => [t('1re mi-temps'), t('2e mi-temps')],
                'lines' => ['att' => $up('Attaquant'), 'mil' => $up('Milieu'), 'def' => $up('Défenseur')],
                'modes' => ['jackpot' => $up('Jackpot'), 'super' => $up('Super jackpot'), 'skill' => $up('Coup d’envoi'), 'extra' => $up('Bille en plus'), 'time' => $up('Temps additionnel')],
                'trick' => $up('Geste technique'), 'trap' => $up('Contrôle'), 'attack' => $up('Attaque'), 'tackle' => $up('Tacle'),
                'onetwo' => $up('Une-deux'), 'goal' => $up('But !'),
            ],
            'facts' => Flipper::facts(),
            'member' => $member,
            'account' => $account,
            // Textes de l'écran à points (règles du jeu, rules.js).
            'text' => [
                'player' => t('Joueur {n}'), 'ball' => t('Bille {n}/{t}'), 'launchHint' => t('Tirez le lanceur'), 'tiltLost' => t('Bille perdue'),
                'warning' => t('Attention !'), 'lion' => t($cfg['roar']), 'lionSub' => t('Points doublés pendant 25 secondes'),
                'half' => t('Mi-temps'), 'scoreLine' => $bf['short'] . ' {h} – {a} {o}', 'kickoff' => t('Coup d’envoi'), 'vs' => t('{r} contre {o}'),
                'r8' => t('8es de finale'), 'r4' => t('Quarts de finale'), 'r2' => t('Demi-finale'), 'final' => t('Finale'),
                'win' => t('Victoire !'), 'draw' => t('Match nul'), 'loss' => t('Défaite'),
                'nextRound' => t('Qualifiés ! Prochain tour : {r}'), 'replay' => t('Match à rejouer'), 'out' => t('Éliminés de la Coupe'),
                'cupWon' => t('Coupe gagnée !'), 'cupWonSub' => t('Le trophée est à vous'),
                'goal' => t('BUT !'), 'keeper' => t('Arrêt du gardien !'), 'missed' => t('Occasion manquée'), 'awayGoal' => t('But de {o}'),
                'tackle' => t('Tacle !'), 'attack' => t('Attaque lancée !'), 'attackSub' => t('Chaque passe élimine deux joueurs'),
                'superLit' => t('Super jackpot allumé'), 'onetwo' => t('Une-deux !'),
                'eliminated' => t('Joueur éliminé'), 'left' => t('Encore {n} adversaires'), 'leftOne' => t('Encore un adversaire'),
                'line_att' => t('attaquant'), 'line_mil' => t('milieu'), 'line_def' => t('défenseur'),
                'lockLit' => t('Poche {l} allumée'), 'lockLitSub' => t('Visez la poche qui clignote'),
                'locked' => t('Bille verrouillée : {l}'), 'locks' => t('{n}/3 billes verrouillées'),
                'multiball' => t('Multiball !'), 'multiballSub' => t('Chaque but est un jackpot'), 'multiballEnd' => t('Fin du multiball'),
                'mini' => t('Face au but !'), 'miniSub' => t('Les petits batteurs frappent au but'),
                'skill' => t('Coup d’envoi parfait'), 'skillLit' => t('Coup d’envoi allumé'), 'extraBall' => t('Bille en plus !'),
                'moreTime' => t('Temps additionnel : 5 minutes'), 'trick' => t('Geste technique !'), 'fact' => t('Le saviez-vous ?'),
                'saved' => t('Bille sauvée'), 'bonus' => t('Bonus'), 'again' => t('Rejouez !'),
                'jackpotHint' => t('Marquez : jackpot !'), 'lockHint' => t('Visez la poche allumée'),
            ],
            'ui' => [
                'insertCoin' => t('Appuyez sur « Jouer »'), 'over' => t('Fin de la partie'), 'record' => t('Record'), 'p' => t('J'), 'ball' => t('Bille'),
                'soundOn' => t('Allumer le son'), 'soundOff' => t('Couper le son'), 'newRecord' => t('Nouveau record personnel !'),
                'multiNoRank' => t('Les parties à plusieurs ne comptent pas au tableau d’honneur.'), 'empty' => t('Personne encore : à vous d’ouvrir le tableau !'),
                'loading' => t('Chargement…'), 'offline' => t('Connexion perdue, réessayez.'), 'copied' => t('Score copié : collez-le où vous voulez.'),
                'shareText' => t('{n} points au {t} !', ['t' => t($cfg['title'])]),
                'mailed' => t('Un lien vient d’être envoyé à cette adresse : ouvrez-le sur cet appareil pour entrer au tableau d’honneur (pensez aux indésirables).'),
                'joined' => t('C’est noté : vos prochaines parties comptent au tableau d’honneur.'),
                'visitors' => t('Visiteurs'),
                'cupRun' => t('Votre parcours en Coupe'), 'cups' => t('Coupes gagnées : {n}'),
                'win' => t('Victoire'), 'draw' => t('Nul'), 'loss' => t('Défaite'), 'stopped' => t('Match interrompu'),
                'missions' => [
                    'but' => t('But marqué'), 'victoire' => t('Match gagné'), 'coupe' => t('Coupe gagnée'), 'combo' => t('Jauge COMBO pleine'),
                    'lock' => t('Bille verrouillée'), 'multiball' => t('Multiball'), 'jackpot' => t('Jackpot'), 'super' => t('Super jackpot'),
                    'skill' => t('Coup d’envoi parfait'), 'trick' => t('Geste technique'), 'tacle' => t('Tacle'), 'lion' => rtrim(t($cfg['roar']), ' !'),
                    'extra' => t('Bille en plus'),
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
