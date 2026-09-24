<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Json;
use App\Storage\Lock;

/**
 * Les conversations avec l'assistant Régie, suivies par e-mail.
 *
 * Une conversation n'a pas de fin déclarée : le visiteur ferme la fenêtre, ou
 * s'en va. Elle est tenue pour terminée après une demi-heure sans nouvelle
 * question, et la tâche planifiée envoie alors l'échange entier, tel que
 * l'historique le garde : anonyme, adresses e-mail et numéros masqués. Les
 * conversations terminées au même passage partent dans un seul message ; un
 * visiteur qui revient plus tard dans la même conversation vaut un nouvel
 * envoi, de la suite seulement.
 */
final class RegieMail
{
    /** Au-delà, plus de rattrapage : une alerte rallumée ou une adresse ajoutée n'envoie pas des semaines d'archives. */
    private const LOOKBACK = 2 * 86400;

    /** Conversations détaillées dans un message ; les suivantes sont comptées, avec le lien vers l'historique. */
    private const MAX_PER_MAIL = 30;

    private const LANGUES = ['fr' => 'français', 'en' => 'anglais', 'es' => 'espagnol', 'de' => 'allemand',
                             'it' => 'italien', 'pt' => 'portugais', 'nl' => 'néerlandais'];

    /** Minutes sans question après lesquelles une conversation est terminée. */
    public static function idleMinutes(): int
    {
        return max(5, (int) Config::get('regie.mail_idle_minutes', 30));
    }

    private static function stateFile(): string
    {
        return Config::path('data') . '/private/regie-mail.json';
    }

    /**
     * Envoie les conversations terminées depuis le dernier passage.
     *
     * @param  int|null $now instant du passage, pour les essais
     * @return string compte rendu pour la tâche planifiée, vide s'il n'y avait rien à faire
     */
    public static function run(?int $now = null): string
    {
        if (RegieHistory::retention() === 0) {
            return '';
        }
        $now ??= time();
        // Deux passages simultanés n'envoient pas deux fois la même conversation.
        return (string) Lock::transaction('regie-mail', static fn(): string => self::pass($now));
    }

    private static function pass(int $now): string
    {
        $state = Json::read(self::stateFile());
        // Fil => instant du dernier échange déjà envoyé.
        $sent = array_filter((array) ($state['sent'] ?? []), 'is_int');
        $since = $now - self::LOOKBACK;
        $idle = self::idleMinutes() * 60;

        $months = [];
        for ($t = (int) strtotime(date('Y-m-01', $since)); $t <= $now; $t = (int) strtotime('+1 month', $t)) {
            $months[] = date('Y-m', $t);
        }

        $due = [];
        // Du plus ancien au plus récent : le message se lit dans l'ordre des faits.
        foreach (array_reverse(RegieHistory::conversations(RegieHistory::exchanges($months))) as $thread) {
            $exchanges = $thread['exchanges'];
            $last = (int) $exchanges[count($exchanges) - 1]['ts'];
            if ($last > $now - $idle) {
                continue;   // en cours : on attend qu'elle se taise
            }
            $from = max((int) ($sent[$thread['conv']] ?? 0), $since);
            $fresh = array_values(array_filter($exchanges, static fn(array $x): bool => $x['ts'] > $from));
            if ($fresh !== []) {
                $due[] = ['thread' => $thread, 'fresh' => $fresh, 'last' => $last,
                          'resumed' => count($fresh) < count($exchanges)];
            }
        }
        if ($due === []) {
            return '';
        }

        // Alerte coupée : les conversations sont seulement notées comme vues,
        // pour que la rallumer ne déverse pas le passé.
        $enabled = Notifier::enabled('regie');
        if ($enabled) {
            [$title, $body] = self::compose($due);
            // Échec (aucune adresse, plafond horaire, serveur d'envoi) : rien
            // n'est noté, le passage suivant réessaie.
            if (!Notifier::message('regie', $title, $body, true)) {
                return count($due) . ' conversation(s) avec Régie en attente d’envoi';
            }
        }

        foreach ($due as $item) {
            $sent[$item['thread']['conv']] = $item['last'];
        }
        // Ce qui a quitté la fenêtre de rattrapage ne sert plus à rien.
        $sent = array_filter($sent, static fn(int $ts): bool => $ts >= $since);
        Json::write(self::stateFile(), ['sent' => $sent, 'at' => date('c', $now)]);

        return $enabled ? count($due) . ' conversation(s) avec Régie envoyée(s) par e-mail' : '';
    }

    /**
     * @param  array<int, array{thread:array, fresh:array, last:int, resumed:bool}> $due
     * @return array{0:string, 1:string} objet et corps du message
     */
    private static function compose(array $due): array
    {
        $base = rtrim((string) Config::get('site.url'), '/');
        $total = count($due);
        $questions = array_sum(array_map(static fn(array $d): int => count($d['fresh']), $due));

        if ($total === 1) {
            $start = (int) $due[0]['fresh'][0]['ts'];
            $title = 'Conversation avec Régie, le ' . date('d/m', $start) . ' à ' . date('H:i', $start)
                   . ' (' . self::plural($questions, 'question') . ')';
        } else {
            $title = $total . ' conversations avec Régie (' . self::plural($questions, 'question') . ')';
        }

        $body = "Rien n'identifie les visiteurs : ni adresse IP, ni compte. Les adresses e-mail et "
              . "numéros de téléphone qu'ils ont tapés sont masqués, leurs adresses web rendues "
              . "inertes (exemple[.]com).\n";

        foreach (array_slice($due, 0, self::MAX_PER_MAIL) as $i => $item) {
            $fresh = $item['fresh'];
            $first = $fresh[0];
            $start = (int) $first['ts'];
            $end = (int) $fresh[count($fresh) - 1]['ts'];

            $body .= "\n" . ($total > 1 ? '=== Conversation ' . ($i + 1) . ' sur ' . $total . " ===\n" : '');
            $when = 'Le ' . date('d/m/Y', $start) . ', '
                  . (date('H:i', $end) !== date('H:i', $start)
                      ? 'de ' . date('H:i', $start) . ' à ' . date('H:i', $end)
                      : 'à ' . date('H:i', $start));
            $lang = (string) $first['lang'];
            $body .= $when . ($lang !== '' && $lang !== 'fr' ? ', en ' . (self::LANGUES[$lang] ?? $lang) : '') . "\n";
            if ($first['page'] !== '') {
                $body .= 'Page : ' . $base . $first['page'] . "\n";
            }
            if ($item['resumed']) {
                $opened = (int) $item['thread']['exchanges'][0]['ts'];
                $body .= 'Suite d’une conversation commencée le ' . date('d/m', $opened) . ' à ' . date('H:i', $opened) . ".\n";
            }

            foreach ($fresh as $x) {
                $body .= "\n" . date('H:i', (int) $x['ts']) . " · Question\n" . self::defang((string) $x['q']) . "\n"
                       . "Réponse de Régie\n" . self::answer((string) $x['a'], (array) $x['links'], $base) . "\n";
                $tags = self::tags($x);
                if ($tags !== '') {
                    $body .= '(' . $tags . ")\n";
                }
            }
        }
        if ($total > self::MAX_PER_MAIL) {
            $body .= "\n… et " . ($total - self::MAX_PER_MAIL) . " autre(s) conversation(s), à lire dans l'historique.\n";
        }

        return [$title, $body . "\nHistorique complet : " . $base . "/admin/assistant\n"];
    }

    /**
     * Réponse en texte simple. Les pages du site qu'elle citait vraiment
     * deviennent des adresses complètes, cliquables dans le message ; un
     * chemin inventé ne laisse que son libellé.
     *
     * @param array<string, string> $links chemin interne => libellé
     */
    private static function answer(string $text, array $links, string $base): string
    {
        $out = '';
        $offset = 0;
        while (preg_match(Regie::LINK, $text, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = (int) $m[0][1];
            $out .= self::defang(substr($text, $offset, $start - $offset));
            $label = self::defang((string) $m[1][0]);
            $path = '/' . trim((string) $m[2][0], '/');
            $out .= isset($links[$path]) ? $label . ' (' . $base . I18n::url($path, 'fr') . ')' : $label;
            $offset = $start + strlen((string) $m[0][0]);
        }
        $out .= self::defang(substr($text, $offset));
        return trim(Regie::tidy($out));
    }

    /**
     * Une adresse web tapée par un visiteur — ou répétée par le modèle — ne
     * devient pas un lien cliquable dans la boîte de l'exploitant :
     * https://exemple.com s'écrit https[:]//exemple[.]com, lisible mais inerte.
     */
    private static function defang(string $text): string
    {
        $text = (string) preg_replace('#\b([a-z][a-z0-9+.\-]*):(?=//)#i', '$1[:]', $text);
        return (string) preg_replace('/(?<=[\p{L}\p{N}])\.(?=\p{L})/u', '[.]', $text);
    }

    /** @param array<string, mixed> $x */
    private static function tags(array $x): string
    {
        $tags = [];
        if ($x['note'] === 'gemini_empty') {
            $tags[] = 'Gemini n’a pas répondu : réponse de secours tirée du site';
        } elseif ($x['source'] === 'index') {
            $tags[] = 'réponse tirée du site, sans l’IA';
        }
        if ($x['source'] === 'none') {
            $tags[] = 'sans réponse';
        } elseif ($x['links'] === []) {
            $tags[] = 'sans lien vers le site';
        }
        return implode(' · ', $tags);
    }

    private static function plural(int $n, string $word): string
    {
        return $n . ' ' . $word . ($n > 1 ? 's' : '');
    }
}
