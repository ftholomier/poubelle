#!/usr/bin/env php
<?php
/**
 * Crée les pages éditoriales que l'ancien site n'avait pas : sous WordPress,
 * les pages « Déposez votre CV », « Déposez une offre » etc. ne contenaient
 * qu'un shortcode, sans un mot d'explication.
 *
 * Réexécutable : une page déjà présente n'est pas écrasée (--force pour forcer).
 *
 *   php bin/seed-pages.php [--force]
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Domain\PageRepository;
use App\Services\Knowledge;
use App\Storage\Index;

$force = in_array('--force', $argv, true);

$pages = [
    [
        'slug'  => 'comment-deposer-un-cv',
        'title' => 'Déposer son CV sur intermittent.fr',
        'body'  => <<<'HTML'
        <p>Déposer un CV sur intermittent.fr est gratuit, sans abonnement ni commission. Votre
        profil rejoint un annuaire consulté par les compagnies, les prestataires techniques, les
        salles et les productions qui recrutent.</p>

        <h2>Ce qu'il faut préparer</h2>
        <ul>
          <li>Votre métier principal, tel qu'un employeur le chercherait : « régisseur son »,
              « costumière », « chef opérateur », « monteuse ».</li>
          <li>Votre ville de départ, qui sert aux recherches par région.</li>
          <li>Vos compétences : matériel maîtrisé, logiciels, habilitations, langues.</li>
          <li>Un CV au format PDF ou DOCX, 5 Mo maximum.</li>
        </ul>

        <h2>Les étapes</h2>
        <ol>
          <li>Remplissez le formulaire de dépôt : métier, ville et adresse e-mail sont obligatoires,
              le reste est facultatif.</li>
          <li>Ajoutez vos compétences en cliquant dans la liste proposée, puis complétez librement.</li>
          <li>Joignez votre CV, puis publiez. Vous pouvez aussi enregistrer un brouillon et revenir
              plus tard.</li>
        </ol>

        <h2>Qui voit quoi</h2>
        <p>Votre fiche publique affiche votre nom, votre métier, votre ville, vos compétences et
        votre présentation. <strong>Votre adresse e-mail reste masquée par défaut</strong> : elle
        n'apparaît que si vous cochez explicitement la case correspondante. Vous pouvez à tout
        moment retirer votre profil de l'annuaire ou demander la suppression de vos données.</p>

        <h2>Nos conseils</h2>
        <ul>
          <li>Un intitulé précis rapporte plus de contacts qu'un intitulé général : « régisseur son
              spectacle vivant » plutôt que « technicien ».</li>
          <li>Mentionnez vos disponibilités et votre mobilité : beaucoup de missions sont en tournée.</li>
          <li>Tenez votre fiche à jour : les profils récents remontent en tête de l'annuaire.</li>
        </ul>
        HTML,
    ],
    [
        'slug'  => 'comment-deposer-une-annonce',
        'title' => 'Publier une offre d\'emploi',
        'body'  => <<<'HTML'
        <p>La publication d'une annonce est immédiate et gratuite. Aucune commission n'est prélevée
        sur les embauches, et les candidatures vous parviennent directement par e-mail.</p>

        <h2>Ce que doit contenir une bonne annonce</h2>
        <ul>
          <li><strong>Le statut du poste</strong> : CDD d'usage, CDI intermittent, cachet, GUSO,
              stage. C'est la première information que cherchent les candidats.</li>
          <li><strong>La rémunération</strong>, même sous forme de fourchette. Les annonces chiffrées
              reçoivent nettement plus de réponses.</li>
          <li><strong>Le lieu et les dates</strong>, en précisant les déplacements et la prise en
              charge des défraiements.</li>
          <li><strong>Les missions concrètes</strong> et l'équipe dans laquelle s'insère le poste.</li>
        </ul>

        <h2>La relecture automatique</h2>
        <p>Avant publication, l'assistant du site relit votre annonce et signale ce qui manque
        souvent : statut non précisé, rémunération absente, intitulé genré sans forme épicène,
        description trop courte. Ce sont des suggestions, jamais des blocages : vous restez maître
        du texte publié.</p>

        <h2>Après la publication</h2>
        <p>Votre annonce apparaît immédiatement dans la liste des offres et dans l'annuaire des
        employeurs, avec une fiche qui regroupe toutes vos publications. Pour la modifier ou la
        retirer, contactez-nous depuis la page de mentions légales.</p>
        HTML,
    ],
    [
        'slug'  => 'questions-frequentes',
        'title' => 'Questions fréquentes',
        'body'  => <<<'HTML'
        <h2>Le site est-il vraiment gratuit ?</h2>
        <p>Oui, des deux côtés. Déposer un CV, déposer une annonce, consulter l'annuaire et postuler
        ne coûtent rien et n'ont jamais rien coûté. Le site vit de la publicité affichée dans les
        emplacements prévus à cet effet. Aucune commission n'est prélevée sur une embauche.</p>

        <h2>Faut-il créer un compte ?</h2>
        <p>Non pour déposer un CV ou une annonce. Un compte sert à retrouver et modifier vos
        publications, et à suivre les candidatures reçues.</p>

        <h2>Qu'est-ce que le GUSO ?</h2>
        <p>Le Guichet unique du spectacle occasionnel est le dispositif qui permet à un employeur
        dont l'activité principale n'est pas le spectacle d'embaucher un artiste ou un technicien
        du spectacle en une seule déclaration. Il regroupe l'ensemble des cotisations sociales.
        Pour les règles applicables à votre situation, référez-vous au
        <a href="https://www.guso.fr" rel="noopener noreferrer" target="_blank">site officiel du GUSO</a>.</p>

        <h2>Comment fonctionne le statut d'intermittent ?</h2>
        <p>Le régime d'assurance chômage des intermittents du spectacle (annexes 8 et 10) ouvre des
        droits sous condition d'un nombre d'heures travaillées sur une période de référence. Les
        seuils, les périodes et les modalités de calcul évoluent régulièrement et dépendent de
        chaque situation individuelle. <strong>Ce site ne délivre pas de conseil juridique.</strong>
        Adressez-vous à
        <a href="https://www.francetravail.fr" rel="noopener noreferrer" target="_blank">France Travail
        spectacle</a>, à <a href="https://www.audiens.org" rel="noopener noreferrer" target="_blank">Audiens</a>
        ou à un syndicat professionnel pour une réponse qui engage.</p>

        <h2>Comment supprimer mes données ?</h2>
        <p>Écrivez à l'adresse indiquée dans les mentions légales en précisant l'adresse e-mail
        associée à votre fiche. Votre profil et les fichiers joints sont supprimés, et non
        simplement masqués.</p>

        <h2>Une annonce vous paraît suspecte ?</h2>
        <p>Signalez-la : une annonce qui demande un paiement, des coordonnées bancaires ou des
        documents d'identité avant tout entretien n'a rien à faire ici et sera retirée.</p>
        HTML,
    ],
    [
        'slug'  => 'a-propos',
        'title' => 'À propos d\'intermittent.fr',
        'body'  => <<<'HTML'
        <p>intermittent.fr met en relation, depuis 2005, les intermittents du spectacle et les
        structures qui recrutent : compagnies, prestataires techniques, salles, productions
        audiovisuelles et cinématographiques.</p>

        <h2>Notre principe</h2>
        <p>Le site est gratuit des deux côtés du plateau, sans abonnement ni commission. Il se
        finance par la publicité, affichée dans des emplacements identifiés et chargée uniquement
        après votre consentement.</p>

        <h2>Ce que vous y trouvez</h2>
        <ul>
          <li>Des offres d'emploi déposées directement par les employeurs, classées par famille de
              métier, type de contrat et région.</li>
          <li>Un annuaire de CV d'intermittents, consultable librement.</li>
          <li>Un annuaire des structures qui recrutent, avec leurs publications.</li>
        </ul>

        <h2>Modération</h2>
        <p>Les annonces sont relues et les contenus manifestement frauduleux sont retirés. Le site
        n'est ni un intermédiaire de placement, ni un employeur : il met en relation, la
        contractualisation se fait directement entre les parties.</p>
        HTML,
    ],
];

/**
 * Convertit le Markdown simple de bin/content/ en HTML.
 * Volontairement minimal : titres, paragraphes, listes et liens suffisent aux
 * pages éditoriales, et ça évite une dépendance pour quatre balises.
 */
function markdownToHtml(string $markdown): string
{
    $out = '';
    $inList = false;
    $paragraph = [];

    $flush = static function () use (&$paragraph, &$out): void {
        if ($paragraph !== []) {
            $out .= '<p>' . implode('<br>', $paragraph) . "</p>\n";
            $paragraph = [];
        }
    };
    $inline = static function (string $text): string {
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // [libellé](url) -> lien, en marquant les liens sortants.
        return (string) preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            static function (array $m): string {
                $href = $m[2];
                $external = str_starts_with($href, 'http');
                return sprintf('<a href="%s"%s>%s</a>', $href,
                    $external ? ' rel="noopener noreferrer" target="_blank"' : '', $m[1]);
            },
            $text,
        );
    };

    foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
        $line = rtrim($line);

        if ($line === '') {
            if ($inList) { $out .= "</ul>\n"; $inList = false; }
            $flush();
            continue;
        }
        if (str_starts_with($line, '# ')) {
            if ($inList) { $out .= "</ul>\n"; $inList = false; }
            $flush();
            continue;   // le titre de niveau 1 est le titre de la page
        }
        if (str_starts_with($line, '### ')) {
            if ($inList) { $out .= "</ul>\n"; $inList = false; }
            $flush();
            $out .= '<h3>' . $inline(substr($line, 4)) . "</h3>\n";
            continue;
        }
        if (str_starts_with($line, '## ')) {
            if ($inList) { $out .= "</ul>\n"; $inList = false; }
            $flush();
            $heading = substr($line, 3);
            $out .= '<h2 id="' . slugify($heading, 40) . '">' . $inline($heading) . "</h2>\n";
            continue;
        }
        if (str_starts_with($line, '* ')) {
            $flush();
            if (!$inList) { $out .= "<ul>\n"; $inList = true; }
            $out .= '<li>' . $inline(substr($line, 2)) . "</li>\n";
            continue;
        }
        if ($inList) { $out .= "</ul>\n"; $inList = false; }
        $paragraph[] = $inline($line);
    }
    if ($inList) { $out .= "</ul>\n"; }
    $flush();

    return trim($out);
}

// Les pages dont le texte vit dans bin/content/ : elles sont toujours
// réécrites, car ce fichier est la source de vérité.
$fromFile = [
    'mentions-legales' => 'Politique de confidentialité',
];
foreach ($fromFile as $slug => $title) {
    $file = __DIR__ . '/content/' . $slug . '.md';
    if (!is_file($file)) {
        continue;
    }
    $body = markdownToHtml((string) file_get_contents($file));
    $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($body)));

    PageRepository::save([
        'id'      => $slug,
        'slug'    => $slug,
        'title'   => $title,
        'status'  => 'publish',
        'excerpt' => str_excerpt($text, 220),
        'body'    => $body,
        'menu'    => true,
        'seo'     => ['title' => $title, 'description' => str_excerpt($text, 155)],
        'published_at' => date('c'),
    ], 'fr');
    printf("   écrite depuis bin/content : %s (%d caractères)\n", $slug, strlen($body));
}

$created = $skipped = 0;
foreach ($pages as $page) {
    if (!$force && PageRepository::find($page['slug'], 'fr') !== null) {
        printf("   (existe déjà) %s\n", $page['slug']);
        $skipped++;
        continue;
    }

    $body = preg_replace('/^\s+/m', '', $page['body']) ?? $page['body'];
    $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($body)));

    PageRepository::save([
        'id'      => $page['slug'],
        'slug'    => $page['slug'],
        'title'   => $page['title'],
        'status'  => 'publish',
        'excerpt' => str_excerpt($text, 220),
        'body'    => $body,
        'menu'    => true,
        'seo'     => ['title' => $page['title'], 'description' => str_excerpt($text, 155)],
        'published_at' => date('c'),
    ], 'fr');

    printf("   créée : %s\n", $page['slug']);
    $created++;
}

Index::rebuild('pages');
$stats = Knowledge::rebuild();

printf("\n%d page(s) créée(s), %d déjà présente(s). Base de connaissance : %d fragments.\n",
    $created, $skipped, $stats['count']);
