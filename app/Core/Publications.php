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
    public const ESSAIS_MAX = 3;

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

    /** Les publications dont l'heure est venue. @return list<array<string, mixed>> */
    public function aEnvoyer(int $maintenant): array
    {
        return array_values(array_filter(
            $this->file(),
            static fn(array $p): bool => (int) ($p['quand'] ?? 0) <= $maintenant
        ));
    }

    /** Combien attendent encore leur heure. */
    public function enAttente(): int
    {
        return count($this->file());
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
     * Au bout de trois essais, ou d'une semaine de retard, elle passe au
     * journal en échec : une file qui grossit indéfiniment est une file que
     * personne ne regarde plus.
     */
    public function noterEchec(string $id, string $motif): void
    {
        $file = $this->file();
        $reste = [];
        foreach ($file as $p) {
            if (($p['id'] ?? '') !== $id) {
                $reste[] = $p;
                continue;
            }
            $p['essais'] = (int) ($p['essais'] ?? 0) + 1;
            $p['dernier_motif'] = $motif;
            $trop = $p['essais'] >= self::ESSAIS_MAX
                 || (time() - (int) ($p['pose_le'] ?? time())) > self::RETARD_ABANDON;
            if ($trop) {
                $this->journaliser($p, [], $motif, false);
                continue;
            }
            $reste[] = $p;
        }
        $this->ecrire('file.json', $reste);
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
            'reseaux'   => array_values((array) ($publication['reseaux'] ?? [])),
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
