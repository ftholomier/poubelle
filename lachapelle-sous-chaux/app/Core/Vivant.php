<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Le contenu vivant du site : actualités, agenda, Flash Info.
 *
 * C'est ce qui distingue un site de mairie tenu à jour d'une plaquette. Il
 * était pourtant le plus difficile à trouver : les trois rubriques vivaient
 * dans le sous-menu de « Le village », et n'apparaissaient qu'au bas de la
 * page d'accueil. Un administré arrivé par un moteur de recherche sur une
 * fiche de démarche — c'est le cas le plus courant — n'avait aucun moyen de
 * voir qu'il se passait quelque chose dans la commune.
 *
 * Cette classe rassemble le tri de ces trois contenus en un seul endroit.
 * Le bandeau « En ce moment » et la page d'accueil s'en servent, et le
 * contrôleur des pages aussi : sans cela, la règle « un événement reste à
 * venir tout le jour de sa date » aurait fini par exister en trois versions
 * légèrement différentes.
 */
final class Vivant
{
    /** Famille des documents qui portent le bulletin municipal. */
    private const FAMILLE_FLASH = 'flash-info';

    public function __construct(private readonly Content $content)
    {
    }

    /**
     * Actualités publiées, la plus récente d'abord.
     *
     * @return array<int, array<mixed>>
     */
    public function actualites(int $combien = 0): array
    {
        $items = $this->content->publies('actualites');
        usort($items, static fn(array $a, array $b): int
            => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));

        return $combien > 0 ? array_slice($items, 0, $combien) : $items;
    }

    /**
     * Événements à venir, le plus proche d'abord.
     *
     * Un événement reste « à venir » tout le jour de sa date : une brocante
     * du dimanche ne doit pas disparaître de l'agenda le dimanche matin,
     * quand c'est précisément le moment où l'on vérifie l'heure. Les
     * manifestations sur plusieurs jours portent une date de fin.
     *
     * @return array<int, array<mixed>>
     */
    public function agenda(int $combien = 0): array
    {
        $aujourdhui = date('Y-m-d');
        $items = array_values(array_filter(
            $this->content->publies('agenda'),
            static fn(array $e): bool => (string) ($e['fin'] ?? $e['date'] ?? '') >= $aujourdhui
        ));
        usort($items, static fn(array $a, array $b): int
            => strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')));

        return $combien > 0 ? array_slice($items, 0, $combien) : $items;
    }

    /**
     * Événements passés, le plus récent d'abord.
     *
     * @return array<int, array<mixed>>
     */
    public function agendaPasses(): array
    {
        $aujourdhui = date('Y-m-d');
        $items = array_values(array_filter(
            $this->content->publies('agenda'),
            static fn(array $e): bool => (string) ($e['fin'] ?? $e['date'] ?? '') < $aujourdhui
        ));
        usort($items, static fn(array $a, array $b): int
            => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));

        return $items;
    }

    /**
     * Numéros du Flash Info, le plus récent d'abord.
     *
     * @return array<int, array<mixed>>
     */
    public function flashInfos(int $combien = 0): array
    {
        $items = array_values(array_filter(
            $this->content->publies('documents'),
            static fn(array $d): bool => ($d['famille'] ?? '') === self::FAMILLE_FLASH
        ));
        usort($items, static fn(array $a, array $b): int
            => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));

        return $combien > 0 ? array_slice($items, 0, $combien) : $items;
    }

    /** @return array<mixed>|null */
    public function derniereActualite(): ?array
    {
        return $this->actualites(1)[0] ?? null;
    }

    /** @return array<mixed>|null */
    public function prochainEvenement(): ?array
    {
        return $this->agenda(1)[0] ?? null;
    }

    /** @return array<mixed>|null */
    public function dernierFlashInfo(): ?array
    {
        return $this->flashInfos(1)[0] ?? null;
    }

    /**
     * Combien de choses il y a à voir en ce moment.
     *
     * Sert la pastille de l'icône des actualités : un nombre dit ce qu'un
     * pictogramme seul ne dit pas, et c'est ce qui transforme une icône
     * décorative en information.
     *
     * La règle : les rendez-vous encore à venir, plus les actualités des trois
     * derniers mois. Un seuil est nécessaire — sans lui la pastille afficherait
     * le total de tout ce qui a jamais été publié, un nombre qui ne bouge
     * jamais et ne veut rien dire. Trois mois parce qu'une commune de sept
     * cents habitants publie quelques nouvelles par trimestre : au-delà, ce
     * n'est plus une nouveauté, c'est de l'archive.
     */
    public function nouveautes(int $jours = 90): int
    {
        $depuis = date('Y-m-d', strtotime('-' . max(1, $jours) . ' days'));
        $recentes = 0;
        foreach ($this->actualites() as $actu) {
            if ((string) ($actu['date'] ?? '') >= $depuis) {
                $recentes++;
            }
        }

        return $recentes + count($this->agenda());
    }

    /**
     * Y a-t-il seulement quelque chose à annoncer ?
     *
     * Une commune qui n'a rien publié ne doit pas se voir imposer un bandeau
     * vide sur toutes ses pages : le fragment se retire alors de lui-même,
     * comme les avis ou l'assistant.
     */
    public function aQuelqueChose(): bool
    {
        return $this->derniereActualite() !== null
            || $this->prochainEvenement() !== null
            || $this->dernierFlashInfo() !== null;
    }
}
