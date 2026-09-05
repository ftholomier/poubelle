<?php
declare(strict_types=1);

namespace App\Core;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Texte mis en forme par la mairie, ramené à ce que la charte autorise.
 *
 * Partout ailleurs dans ce dépôt la règle est « e() sur toute sortie ». Ici on
 * y déroge, et c'est la seule dérogation : le corps d'un bloc peut porter du
 * gras, une liste, un bouton. La contrepartie est que rien ne sort d'ici sans
 * être passé par cette classe, à l'écriture comme à l'affichage.
 *
 * Le parti pris n'est pas « nettoyer le HTML dangereux » — c'est une liste
 * blanche : tout ce qui n'est pas nommé ci-dessous disparaît, balise comme
 * attribut. Un `<script>`, un `onclick`, un `style` en ligne, un `<iframe>`,
 * une classe inventée : rien n'a de chemin pour arriver jusqu'à la page. Le
 * texte, lui, est conservé — retirer une balise ne fait pas perdre son contenu.
 *
 * L'autre raison d'être de cette liste est la mise en page. Une mairie qui
 * colle du contenu venu d'un traitement de texte y apporte des tailles en
 * points, des polices, des couleurs de fond : la page se disloque, et personne
 * ne sait pourquoi. En n'acceptant que des classes de charte — pas une couleur
 * libre, pas un style en ligne — le texte reste dans le système de design quoi
 * qu'on y colle.
 */
final class TexteRiche
{
    /**
     * Balises acceptées, et pour chacune les attributs acceptés.
     *
     * Pas de titre dans cette liste : les blocs ont leurs propres champs de
     * titre, qui portent la hiérarchie de la page. Laisser écrire un <h2> dans
     * le corps ferait sauter les niveaux, ce que mise-en-page.py refuse — et à
     * raison, c'est ce que suit un lecteur d'écran.
     */
    private const BALISES = [
        'p'      => [],
        'br'     => [],
        'strong' => [],
        'em'     => [],
        'ul'     => [],
        'ol'     => [],
        'li'     => [],
        'span'   => ['class'],
        // Les ordinaux abrégés — XIIIᵉ siècle, 1ᵉʳ avril — sont du texte
        // courant dans une prose municipale. Sans balise, la liste blanche
        // les aplatit en « XIIIe », qu'un lecteur d'écran prononce mal.
        'sup'    => [],
        'a'      => ['href', 'class'],
    ];

    /**
     * Ce que les notes de l'assistant acceptent en plus.
     *
     * Elles ne sont pas publiées dans une page : ce sont des consignes libres
     * qu'on relit à l'écran et qu'on donne à l'assistant. Un sous-titre y
     * organise une longue note sans mettre en cause la hiérarchie de titres
     * d'aucune page, et une citation sert à recopier un texte de référence.
     */
    private const BALISES_NOTES = [
        'h3'         => [],
        'blockquote' => [],
    ];

    /** Balises qui n'ont pas le droit de rester vides. */
    private const NON_VIDES = ['p', 'strong', 'em', 'ul', 'ol', 'li', 'span', 'a',
                               'h3', 'blockquote'];

    /**
     * Balises dont on jette aussi le contenu.
     *
     * Ailleurs, retirer une balise garde son texte — c'est ce qu'on veut d'un
     * <h2> ou d'un <font> collés depuis un traitement de texte. Ici non :
     * déballer un <script> recracherait son code en toutes lettres au milieu de
     * la page. Inoffensif, puisque le texte est échappé, mais absurde à lire.
     */
    private const AVEC_CONTENU = ['script', 'style', 'iframe', 'object', 'embed',
                                  'svg', 'math', 'template', 'noscript', 'head'];

    /**
     * Balises renommées avant filtrage.
     *
     * document.execCommand — le seul moyen de mettre en gras sans dépendance —
     * produit <b> et <i> dans Chromium, <strong> et <em> ailleurs, et cela a
     * changé plusieurs fois selon les versions. Les refuser reviendrait à
     * effacer en silence la mise en forme d'un rédacteur sur Chrome ; les
     * accepter tels quels laisserait deux écritures du même gras dans le
     * contenu. On les ramène donc à la forme sémantique, une seule, celle que
     * les lecteurs d'écran annoncent.
     */
    private const SYNONYMES = ['b' => 'strong', 'i' => 'em'];

    /**
     * Couleurs de texte proposées, en clair pour le back-office.
     *
     * Ce sont des classes, pas des couleurs. Une couleur écrite en dur dans le
     * contenu survit à un changement de charte et devient un corps étranger ;
     * pire, elle ne sait pas sur quel fond elle sera posée — les sections
     * alternent, et un vert foncé choisi sur le crème devient illisible sur
     * l'ardoise. Les classes, elles, ont une variante par fond dans site.css.
     *
     * Deux couleurs et rien de plus, parce que la charte n'en a pas d'autres
     * qui tiennent 4,5:1 sur les trois fonds de section. Un « gris doux » y
     * figurait : il rendait exactement la couleur du texte courant, et
     * proposer un réglage sans effet est pire que ne pas le proposer.
     */
    public const COULEURS = [
        ''            => 'Couleur du texte',
        'texte-vert'  => 'Bleu de la commune',
        'texte-encre' => 'Encre (plus soutenu)',
    ];

    /** Tailles proposées. Deux crans, pas un curseur : la charte tient à son échelle. */
    public const TAILLES = [
        ''            => 'Taille normale',
        'texte-grand' => 'Plus grand',
        'texte-petit' => 'Plus petit',
    ];

    /** Classe du bouton posé dans le texte. */
    public const BOUTON = 'bouton';

    /** Schémas d'adresse acceptés pour un lien. */
    private const SCHEMAS = ['http', 'https', 'mailto', 'tel'];

    /**
     * Ramène un texte mis en forme à la liste blanche.
     *
     * Accepte aussi l'ancienne forme — un tableau de paragraphes en texte brut —
     * pour que le contenu écrit avant l'éditeur continue de s'afficher, et
     * qu'un fichier JSON repris à la main reste valable.
     *
     * @param mixed $valeur chaîne HTML, ou tableau de paragraphes en texte brut
     * @param bool  $notes  profil élargi des notes de l'assistant
     */
    public static function nettoyer(mixed $valeur, bool $notes = false): string
    {
        if (is_array($valeur)) {
            $html = '';
            foreach ($valeur as $paragraphe) {
                $texte = trim((string) $paragraphe);
                if ($texte !== '') {
                    $html .= '<p>' . htmlspecialchars($texte, ENT_QUOTES, 'UTF-8') . '</p>';
                }
            }
            return $html;
        }

        $html = trim((string) $valeur);
        if ($html === '') {
            return '';
        }

        // Un texte sans la moindre balise vient d'un formulaire sans JavaScript
        // ou d'un copier-coller : on le prend pour ce qu'il est, du texte, et on
        // en fait des paragraphes aux lignes vides comme le faisait le champ
        // d'origine. Sans cela, coller trois paragraphes en donnerait un seul.
        if (!str_contains($html, '<')) {
            return self::nettoyer(preg_split('/\R{2,}/u', $html) ?: [], $notes);
        }

        $doc = new DOMDocument();
        $ancien = libxml_use_internal_errors(true);
        // Le préambule XML est la façon la plus sûre d'annoncer l'UTF-8 à
        // libxml : sans lui, un « é » ressort en deux caractères.
        $ok = $doc->loadHTML(
            '<?xml encoding="utf-8" ?><body>' . $html . '</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($ancien);

        if (!$ok) {
            // HTML irrécupérable : on garde le texte, on jette la mise en forme.
            return self::nettoyer([strip_tags($html)], $notes);
        }

        $corps = $doc->getElementsByTagName('body')->item(0);
        if (!$corps instanceof DOMElement) {
            return '';
        }

        self::filtrer($corps, $notes);
        self::rhabiller($doc, $corps, $notes);

        $sortie = '';
        foreach (iterator_to_array($corps->childNodes) as $enfant) {
            $sortie .= $doc->saveHTML($enfant);
        }

        return trim($sortie);
    }

    /**
     * Le texte seul, sans balise — pour un extrait, une méta description, un
     * corpus d'assistant. Les balises de bloc laissent une espace derrière
     * elles, sinon deux paragraphes se recollent en un mot.
     */
    public static function enTexte(mixed $valeur): string
    {
        $html = self::nettoyer($valeur);
        $html = preg_replace('#</(p|li|ul|ol)>#', '$0 ', $html) ?? $html;
        $texte = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $texte) ?? '');
    }

    /** Classes admises sur un <span>. */
    private static function classesSpan(): array
    {
        return array_filter(array_merge(array_keys(self::COULEURS), array_keys(self::TAILLES)));
    }

    /**
     * Descend l'arbre et n'y laisse que ce qui est nommé.
     *
     * On parcourt une copie de la liste des enfants : retirer un nœud pendant
     * qu'on itère sur la liste vivante en saute un sur deux.
     */
    private static function filtrer(DOMElement $parent, bool $notes = false): void
    {
        $balises = $notes ? self::BALISES + self::BALISES_NOTES : self::BALISES;
        foreach (iterator_to_array($parent->childNodes) as $noeud) {
            if ($noeud instanceof DOMText) {
                continue;
            }
            if (!$noeud instanceof DOMElement) {
                // commentaire, instruction de traitement, CDATA : rien à faire ici
                $parent->removeChild($noeud);
                continue;
            }

            $balise = strtolower($noeud->nodeName);
            if (isset(self::SYNONYMES[$balise])) {
                $noeud = self::renommer($noeud, self::SYNONYMES[$balise]);
                $balise = self::SYNONYMES[$balise];
            }
            if (in_array($balise, self::AVEC_CONTENU, true)) {
                $parent->removeChild($noeud);
                continue;
            }
            if (!isset($balises[$balise])) {
                self::filtrer($noeud, $notes);
                self::remplacerParSonContenu($noeud);
                continue;
            }

            self::nettoyerAttributs($noeud, $balise, $balises);
            self::filtrer($noeud, $notes);

            if (in_array($balise, self::NON_VIDES, true) && trim($noeud->textContent) === ''
                && $noeud->getElementsByTagName('br')->length === 0) {
                $parent->removeChild($noeud);
                continue;
            }

            // Un lien dont l'adresse a été refusée n'est plus un lien : le
            // laisser cliquable sans destination trompe le visiteur, et un
            // lecteur d'écran l'annonce quand même comme un lien.
            if ($balise === 'a' && !$noeud->hasAttribute('href')) {
                self::remplacerParSonContenu($noeud);
            }
        }
    }

    /**
     * Remet dans un paragraphe le texte resté à nu à la racine.
     *
     * Déballer un <h2> ou un <div> laisse son texte directement sous la racine,
     * où aucune règle de style ne l'atteint : il s'affiche à la police du
     * conteneur, sans interligne ni marge, et détonne au milieu des
     * paragraphes voisins. Les fragments qui se suivent sont regroupés dans un
     * même paragraphe, faute de quoi une phrase coupée par un <em> en
     * donnerait trois.
     */
    private static function rhabiller(DOMDocument $doc, DOMElement $corps, bool $notes = false): void
    {
        $blocs = $notes ? ['p', 'ul', 'ol', 'h3', 'blockquote'] : ['p', 'ul', 'ol'];
        $paragraphe = null;

        foreach (iterator_to_array($corps->childNodes) as $noeud) {
            $estBloc = $noeud instanceof DOMElement
                && in_array(strtolower($noeud->nodeName), $blocs, true);

            if ($estBloc) {
                $paragraphe = null;
                continue;
            }
            if ($noeud instanceof DOMText && trim($noeud->textContent) === '') {
                continue;
            }

            if ($paragraphe === null) {
                $paragraphe = $doc->createElement('p');
                $corps->insertBefore($paragraphe, $noeud);
            }
            $paragraphe->appendChild($noeud);
        }
    }

    /** Remplace un élément par le même sous un autre nom, contenu compris. */
    private static function renommer(DOMElement $noeud, string $nom): DOMElement
    {
        $doc = $noeud->ownerDocument;
        $parent = $noeud->parentNode;
        if ($doc === null || $parent === null) {
            return $noeud;
        }
        $neuf = $doc->createElement($nom);
        while ($noeud->firstChild !== null) {
            $neuf->appendChild($noeud->firstChild);
        }
        $parent->replaceChild($neuf, $noeud);
        return $neuf;
    }

    /** Retire une balise en gardant son texte et ses enfants déjà filtrés. */
    private static function remplacerParSonContenu(DOMElement $noeud): void
    {
        $parent = $noeud->parentNode;
        if ($parent === null) {
            return;
        }
        while ($noeud->firstChild !== null) {
            $parent->insertBefore($noeud->firstChild, $noeud);
        }
        $parent->removeChild($noeud);
    }

    /** @param array<string, string[]> $balises liste blanche en vigueur */
    private static function nettoyerAttributs(DOMElement $noeud, string $balise, array $balises): void
    {
        $admis = $balises[$balise] ?? [];
        foreach (iterator_to_array($noeud->attributes ?? []) as $attribut) {
            if (!in_array(strtolower($attribut->nodeName), $admis, true)) {
                $noeud->removeAttribute($attribut->nodeName);
            }
        }

        if ($balise === 'span') {
            $classes = self::classesRetenues($noeud, self::classesSpan());
            if ($classes === '') {
                // un span sans classe utile n'apporte rien : il disparaîtra,
                // son contenu restant
                $noeud->removeAttribute('class');
                return;
            }
            $noeud->setAttribute('class', $classes);
            return;
        }

        if ($balise !== 'a') {
            return;
        }

        $href = self::adresse((string) $noeud->getAttribute('href'));
        if ($href === null) {
            $noeud->removeAttribute('href');
            $noeud->removeAttribute('class');
            return;
        }
        $noeud->setAttribute('href', $href);

        $classes = self::classesRetenues($noeud, [self::BOUTON]);
        if ($classes === '') {
            $noeud->removeAttribute('class');
        } else {
            $noeud->setAttribute('class', $classes);
        }

        // Un lien sortant s'ouvre ailleurs, et noopener évite que la page
        // ouverte puisse manipuler celle-ci. C'est posé ici plutôt que laissé à
        // l'éditeur : le contenu peut aussi arriver par le JSON.
        if (preg_match('#^https?://#i', $href)) {
            $noeud->setAttribute('target', '_blank');
            $noeud->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /** @param string[] $admises */
    private static function classesRetenues(DOMElement $noeud, array $admises): string
    {
        $retenues = array_values(array_intersect(
            preg_split('/\s+/', trim((string) $noeud->getAttribute('class'))) ?: [],
            $admises
        ));
        return implode(' ', array_unique($retenues));
    }

    /**
     * Adresse acceptable pour un lien, ou null.
     *
     * Le danger n'est pas seulement `javascript:` : `data:` sert un document
     * complet, et un schéma inconnu peut lancer une application. D'où la liste
     * blanche de schémas, plus les adresses internes qui n'en portent pas.
     */
    private static function adresse(string $brut): ?string
    {
        $url = trim($brut);
        if ($url === '') {
            return null;
        }
        // les espaces et retours servent à couper « java\nscript: »
        $url = preg_replace('/[\x00-\x20]/', '', $url) ?? '';

        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }

        $schema = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($schema !== '' && in_array($schema, self::SCHEMAS, true)) {
            return $url;
        }

        return null;
    }
}
