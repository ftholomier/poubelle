<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * La file d'attente et le journal des publications.
 *
 * Une publication passe toujours par ici, qu'elle parte tout de suite ou dans
 * trois jours : c'est ce qui fait qu'il existe un seul endroit où savoir ce
 * qui est parti, ce qui attend, et ce qui a échoué. Une mairie qui ne voit pas
 * qu'une publication a échoué la croit partie.
 *
 * **Pourquoi une file plutôt que la programmation de Facebook.** Facebook sait
 * attendre une date ; Instagram ne le sait pas. Programmer chez Meta aurait
 * donc marché pour la moitié du besoin, et il aurait fallu deux mécanismes au
 * lieu d'un — dont un invisible depuis le back-office.
 *
 * **Pourquoi deux déclencheurs.** La file est dépilée par une tâche planifiée,
 * et à défaut par les visites du back-office. Sur un hébergement mutualisé, un
 * cron se règle à la main dans un panneau, et personne ne le fait le jour de
 * la mise en ligne. Sans le second déclencheur, les publications programmées
 * ne partiraient jamais et rien ne le dirait. Avec lui, elles partent dès que
 * quelqu'un ouvre l'administration — en retard, mais elles partent, et l'écran
 * affiche le retard.
 *
 * Les écritures sont atomiques, comme partout ici : fichier temporaire puis
 * `rename()`. Deux administrateurs qui publient en même temps ne peuvent pas
 * se tronquer mutuellement le journal.
 */
final class Publications
{
    /** Au-delà, une publication en attente est considérée perdue. */
    private const RETARD_ABANDON = 604800;   // 7 jours

    /** Ce que le journal garde. Au-delà, l'écran devient illisible. */
    private const JOURNAL_MAX = 200;

    /** Nombre d'essais avant de renoncer et de le dire. */
    public const ESSAIS_MAX = 4;

    /**
     * Le temps d'attente après chaque essai raté, en secondes.
     *
     * Compter les essais ne suffit pas : trois essais faits dans la même
     * minute, parce que trois écrans du back-office ont été ouverts coup sur
     * coup, épuisent le quota sans rien tenter de neuf. Meta bloque parfois
     * une Page pour quelques minutes (codes 368 et 613), et une panne réseau
     * dure rarement moins. Un recul croissant donne à la cause le temps de
     * disparaître, et il vaut aussi pour la tâche planifiée.
     */
    private const RECULS = [300, 1800, 7200];   // 5 min, 30 min, 2 h

    public function __construct(private readonly string $dossier)
    {
    }

    // -------------------------------------------------------------- lecture

    /** @return list<array<string, mixed>> */
    public function file(): array
    {
        return $this->lire('file.json');
    }

    /** @return list<array<string, mixed>> */
    public function journal(): array
    {
        return $this->lire('journal.json');
    }

    /**
     * Les publications dont l'heure est venue.
     *
     * Deux dates les retiennent : `quand`, l'heure demandée par la mairie, et
     * `reprise`, posée par `noterEchec()` après un essai raté. La seconde
     * n'écrase pas la première — l'écran doit continuer d'afficher l'heure
     * voulue, pas l'heure du prochain essai.
     *
     * @return list<array<string, mixed>>
     */
    public function aEnvoyer(int $maintenant): array
    {
        return array_values(array_filter(
            $this->file(),
            static fn(array $p): bool => (int) ($p['quand'] ?? 0) <= $maintenant
                                      && (int) ($p['reprise'] ?? 0) <= $maintenant
        ));
    }

    /** Combien auraient dû partir et n'ont pas encore été dépilées. */
    public function enRetard(int $maintenant): int
    {
        return count($this->aEnvoyer($maintenant));
    }

    // -------------------------------------------------------------- écriture

    /**
     * Met une publication en file. Rend son identifiant.
     *
     * @param array<string, mixed> $publication
     */
    public function empiler(array $publication): string
    {
        $id = date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $publication['id'] = $id;
        $publication['pose_le'] = time();
        $publication['essais'] = 0;
        // Une reprise déjà posée par l'appelant est respectée : c'est le cas
        // d'un envoi immédiat dont un réseau a échoué et qui revient en file.
        $publication['reprise'] = (int) ($publication['reprise'] ?? 0);

        $file = $this->file();
        $file[] = $publication;
        $this->ecrire('file.json', $file);

        return $id;
    }

    public function retirer(string $id): void
    {
        $this->ecrire('file.json', array_values(array_filter(
            $this->file(),
            static fn(array $p): bool => ($p['id'] ?? '') !== $id
        )));
    }

    /**
     * Note un essai raté sans retirer la publication de la file.
     *
     * Au bout de quatre essais, ou d'une semaine de retard, elle passe au
     * journal en échec : une file qui grossit indéfiniment est une file que
     * personne ne regarde plus. Entre deux essais, un recul croissant — sans
     * lui, le compteur d'essais s'épuisait en une minute.
     *
     * **L'échec partiel passe aussi par ici.** Facebook accepté, Instagram
     * refusé : la publication reste en file avec les seuls réseaux qui restent
     * à servir, et les identifiants déjà obtenus sont gardés pour que le
     * journal, à la fin, dise ce qui est parti où. Auparavant, un envoi
     * partiel était retiré de la file et inscrit comme réussi ; le réseau
     * manquant n'était jamais retenté et personne ne l'apprenait.
     *
     * @param list<string>|null     $reseauxRestants ceux qu'il reste à servir
     * @param array<string, string> $dejaPartis      réseau => identifiant obtenu
     */
    public function noterEchec(
        string $id,
        string $motif,
        ?array $reseauxRestants = null,
        array $dejaPartis = []
    ): void {
        $file = $this->file();
        $reste = [];
        foreach ($file as $p) {
            if (($p['id'] ?? '') !== $id) {
                $reste[] = $p;
                continue;
            }

            $p['essais'] = (int) ($p['essais'] ?? 0) + 1;
            $p['dernier_motif'] = $motif;

            // Ce qui a été demandé au départ, pour que le journal le rappelle
            // même après que la liste des réseaux a été réduite aux manquants.
            $p['reseaux_demandes'] ??= array_values((array) ($p['reseaux'] ?? []));
            if ($dejaPartis !== []) {
                $p['posts_acquis'] = array_merge((array) ($p['posts_acquis'] ?? []), $dejaPartis);
            }
            if ($reseauxRestants !== null) {
                $p['reseaux'] = array_values($reseauxRestants);
            }

            $trop = $p['essais'] >= self::ESSAIS_MAX
                 || (time() - (int) ($p['pose_le'] ?? time())) > self::RETARD_ABANDON;
            if ($trop) {
                $this->journaliser($p, (array) ($p['posts_acquis'] ?? []), $motif, false);
                continue;
            }

            $p['reprise'] = time() + $this->recul($p['essais']);
            $reste[] = $p;
        }
        $this->ecrire('file.json', $reste);
    }

    /** Le temps d'attente dû après le n-ième essai raté. */
    private function recul(int $essais): int
    {
        $reculs = self::RECULS;
        $rang = max(0, min($essais - 1, count($reculs) - 1));

        return $reculs[$rang];
    }

    /**
     * Inscrit une publication partie — ou définitivement échouée — au journal.
     *
     * @param array<string, mixed> $publication
     * @param array<string, string> $identifiants réseau => identifiant du post
     */
    public function journaliser(array $publication, array $identifiants, string $motif, bool $succes): void
    {
        $journal = $this->journal();
        array_unshift($journal, [
            'id'        => (string) ($publication['id'] ?? ''),
            'le'        => time(),
            'titre'     => (string) ($publication['titre'] ?? ''),
            'texte'     => mb_substr((string) ($publication['texte'] ?? ''), 0, 300),
            'image'     => (string) ($publication['image'] ?? ''),
            'source'    => (string) ($publication['source'] ?? 'libre'),
            'reseaux'   => array_values((array) (
                $publication['reseaux_demandes'] ?? $publication['reseaux'] ?? []
            )),
            'posts'     => $identifiants,
            'succes'    => $succes,
            'motif'     => $motif,
        ]);

        $this->ecrire('journal.json', array_slice($journal, 0, self::JOURNAL_MAX));
    }

    public function viderJournal(): void
    {
        $this->ecrire('journal.json', []);
    }

    // ---------------------------------------------------------------- verrou

    /**
     * Prend le verrou du dépilage. Rend `null` s'il est déjà pris.
     *
     * Deux déclencheurs valent deux dépilages possibles au même instant : la
     * tâche planifiée et la visite d'un écran du back-office. Tous deux lisent
     * la même file, y trouvent la même publication et l'envoient chacun de leur
     * côté — la mairie publie deux fois, et Meta n'y voit rien à redire. Les
     * écritures atomiques ne protègent pas de cela : elles garantissent qu'un
     * fichier n'est jamais coupé en deux, pas qu'une publication n'est lue
     * qu'une fois.
     *
     * `LOCK_NB` plutôt qu'une attente : celui qui arrive second n'a rien à
     * faire, l'autre dépile déjà. Attendre ferait patienter l'écran pour un
     * travail que quelqu'un d'autre est en train de finir.
     *
     * @return resource|null
     */
    public function verrouiller()
    {
        if (!$this->dossierPret()) {
            return null;
        }

        $poignee = @fopen($this->dossier . '/depilage.lock', 'c');
        if ($poignee === false) {
            return null;
        }
        if (!flock($poignee, LOCK_EX | LOCK_NB)) {
            fclose($poignee);
            return null;
        }

        return $poignee;
    }

    /** @param resource|null $poignee */
    public function relacher($poignee): void
    {
        if (is_resource($poignee)) {
            flock($poignee, LOCK_UN);
            fclose($poignee);
        }
    }

    /** Quand la file a-t-elle été dépilée pour la dernière fois ? */
    public function dernierDepilage(): int
    {
        $chemin = $this->dossier . '/dernier-depilage.txt';

        return is_file($chemin) ? (int) trim((string) @file_get_contents($chemin)) : 0;
    }

    /**
     * Note l'instant du dépilage.
     *
     * Écrit en place, sans passer par un temporaire : le pire que puisse faire
     * une écriture concurrente est de rendre l'horodatage illisible, donc nul,
     * donc de permettre un dépilage de plus. C'est sans conséquence, et cela
     * évite de semer des fichiers temporaires à chaque affichage d'écran.
     */
    public function noterDepilage(int $quand): void
    {
        if ($this->dossierPret()) {
            @file_put_contents($this->dossier . '/dernier-depilage.txt', (string) $quand, LOCK_EX);
        }
    }

    private function dossierPret(): bool
    {
        return is_dir($this->dossier)
            || (@mkdir($this->dossier, 0755, true) || is_dir($this->dossier));
    }

    // ------------------------------------------------------------------ E/S

    /** @return list<array<string, mixed>> */
    private function lire(string $fichier): array
    {
        $chemin = $this->dossier . '/' . $fichier;
        if (!is_file($chemin)) {
            return [];
        }
        try {
            $json = json_decode((string) file_get_contents($chemin), true);
        } catch (Throwable) {
            return [];
        }

        return is_array($json) ? array_values(array_filter($json, 'is_array')) : [];
    }

    /** @param list<array<string, mixed>> $donnees */
    private function ecrire(string $fichier, array $donnees): void
    {
        if (!is_dir($this->dossier) && !@mkdir($this->dossier, 0755, true) && !is_dir($this->dossier)) {
            throw new RuntimeException('Le dossier ' . $this->dossier . ' n’a pas pu être créé.');
        }

        // Temporaire puis rename() : un lecteur obtient l'ancien fichier entier
        // ou le nouveau entier, jamais un JSON coupé en deux.
        $temporaire = $this->dossier . '/.' . $fichier . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $ok = file_put_contents(
            $temporaire,
            json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            LOCK_EX
        );
        if ($ok === false || !@rename($temporaire, $this->dossier . '/' . $fichier)) {
            @unlink($temporaire);
            throw new RuntimeException('Écriture impossible dans ' . $this->dossier . '.');
        }
    }
}
