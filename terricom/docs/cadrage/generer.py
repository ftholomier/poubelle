"""Génère docs/cadrage/fonctionnalites.html : la note de cadrage « Fonctionnalités à développer dans le périmètre
de la plateforme », mise en page à la charte terricom (A4 portrait).

Le texte est celui de la note des fondateurs, sans ajout de fond : seules la mise en forme, les regroupements
par thème et les étiquettes (à développer, extension, option à étudier) sont de notre fait.
Usage : python3 docs/cadrage/generer.py && node scripts/cadrage.mjs [--apercus <dossier>]
"""
from pathlib import Path

ICI = Path(__file__).parent
HEAD = (ICI.parent / 'dossier' / 'dossier.html').read_text()
POLICES = HEAD[HEAD.index('@font-face'):HEAD.index('@page')]

ICONES = {
    'informer': '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
    'promouvoir': '<path d="M3 10v4l11 5V5L3 10z"/><path d="M14 8.5a4 4 0 0 1 0 7"/><path d="M6 14.5l1.5 5h3l-1.3-4.2"/>',
    'animer': '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z"/>',
    'connecter': '<circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 18H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.5"/>',
    'participer': '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 14.2c3 .2 5.5 2.6 5.5 5.8"/>',
    'faciliter': '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M8.5 15l2.5 2.5 4.5-5"/>',
    'mesurer': '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'piloter': '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5 5-2z"/>',
}
PILIERS = [
    ('informer', 'Informer', 'actualités, agenda, tourisme, entreprises, associations, services.'),
    ('promouvoir', 'Promouvoir', 'campagnes, offres, bons plans, commerces, événements.'),
    ('animer', 'Animer', 'circuits, challenges, opérations, marchés, événements.'),
    ('connecter', 'Connecter', 'habitants, entreprises, associations, communes et intercommunalité.'),
    ('participer', 'Faire participer', 'sondages, consultations, idées, signalements, votes.'),
    ('faciliter', 'Faciliter', 'inscriptions, réservations, demandes, contacts.'),
    ('mesurer', 'Mesurer', 'fréquentation, contacts, campagnes, participation et activité territoriale.'),
    ('piloter', 'Piloter', 'tableaux de bord, animation, statistiques et suivi des actions.'),
]
NOM_PILIER = {k: n for k, n, _ in PILIERS}


def icone(k, taille=18):
    return (f'<svg viewBox="0 0 24 24" width="{taille}" height="{taille}" fill="none" stroke="currentColor" stroke-width="1.8" '
            f'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{ICONES[k]}</svg>')


# Fonctionnalités : numéro, titre, statut, piliers, paragraphes (texte de la note).
F = {
    2: ('Signalements habitants', 'dev', ['participer'], [
        'Permettre à un habitant de signaler simplement un problème ou une situation locale : éclairage, propreté, dégradation, mobilier, équipement, etc. Le signalement peut comprendre une catégorie, une description, une photo et une localisation.',
        'Le back-office communal doit pouvoir recevoir le signalement, le qualifier, le faire évoluer dans un statut et éventuellement informer l’habitant de son traitement. L’objectif n’est pas de créer une GMAO complète, mais une interface citoyenne de remontée et de suivi.']),
    3: ('Sondages et consultations citoyennes', 'dev', ['participer', 'mesurer'], [
        'Permettre à une commune ou à l’intercommunalité de créer des consultations : question unique, questionnaire, choix multiples, avis, satisfaction ou consultation sur un projet local.',
        'Les résultats doivent être accessibles au gestionnaire sous forme de statistiques simples et exportables. Des règles de période, de visibilité et de consentement doivent être prévues.']),
    4: ('Boîte à idées citoyenne', 'dev', ['participer'], [
        'Créer un espace où les habitants peuvent proposer des idées ou suggestions concernant la vie locale. Les propositions peuvent être catégorisées, modérées, publiées et commentées.',
        'Le système peut permettre de mettre en avant certaines idées ou de suivre leur statut : reçue, étudiée, retenue, réalisée, etc.']),
    5: ('Votes et participation citoyenne', 'dev', ['participer'], [
        'Permettre d’organiser des votes ou opérations participatives sur des sujets locaux. Cette fonction doit rester un outil de participation et d’animation, sans prétendre remplacer un dispositif électoral officiel.']),
    6: ('Alertes locales ciblées', 'dev', ['informer'], [
        'Développer un système d’alertes territoriales permettant de cibler une commune, une zone, une catégorie d’intérêt ou un type de public : événement modifié, information locale, alerte pratique, fermeture exceptionnelle, opération commerciale, etc.',
        'La fonction doit compléter les notifications déjà prévues dans terricom en donnant au gestionnaire davantage de contrôle sur le ciblage, les catégories et les préférences de l’habitant.']),
    7: ('Réservation d’équipements communaux', 'dev', ['faciliter'], [
        'Permettre aux communes de présenter leurs équipements et de proposer, lorsque pertinent, des demandes ou réservations : salles, terrains, équipements ou espaces municipaux.',
        'Le module doit pouvoir gérer disponibilités, demandes, validation, annulation et notifications. Il ne doit pas devenir un logiciel général de gestion du patrimoine.']),
    8: ('Associations : annuaire et espace association', 'dev', ['informer', 'connecter'], [
        'Ajouter les associations comme catégorie d’acteurs du territoire, avec une fiche publique comprenant coordonnées, présentation, activités, actualités et événements.',
        'Un espace association pourrait permettre à une association de gérer sa fiche, proposer des événements, publier des informations et recevoir des contacts.']),
    9: ('Inscriptions aux événements', 'dev', ['faciliter'], [
        'Permettre aux organisateurs de proposer une inscription à un événement depuis terricom, avec formulaire, nombre de places, liste des inscrits, confirmation et notifications.',
        'Cette fonction complète l’agenda et les formulaires existants.']),
    10: ('Billetterie locale', 'option', ['faciliter'], [
        'Prévoir éventuellement une billetterie simple pour certains événements territoriaux. Cette fonctionnalité est à cadrer séparément afin de ne pas transformer terricom en plateforme e-commerce générale.']),
    11: ('Offres et bons plans locaux', 'dev', ['promouvoir'], [
        'Permettre aux entreprises et communes de publier des offres, promotions, avantages ou bons plans temporaires. Les contenus peuvent être ciblés par commune, période, catégorie ou public.']),
    12: ('Programme de fidélité territorial', 'dev', ['animer', 'promouvoir'], [
        'Développer une logique de récompenses liées aux commerces, événements, circuits ou opérations locales. L’objectif est d’encourager la fréquentation et la participation à la vie économique du territoire.']),
    13: ('Carte de fidélité multi-commerces', 'dev', ['animer', 'promouvoir'], [
        'Permettre éventuellement un dispositif de fidélité commun à plusieurs entreprises locales : points, tampons, récompenses ou avantages. Le module doit rester orienté animation commerciale et ne pas devenir une marketplace.']),
    14: ('Chèques cadeaux territoriaux', 'option', ['promouvoir'], [
        'Prévoir éventuellement un dispositif de chèques ou bons cadeaux utilisables auprès de commerçants partenaires. Cette fonction nécessite un cadrage financier et opérationnel spécifique.']),
    15: ('Mise en relation habitants / entreprises', 'dev', ['connecter', 'faciliter'], [
        'Renforcer la capacité de mise en relation directe entre habitants et entreprises : demande de contact, demande d’information, demande de devis ou orientation vers un professionnel.']),
    16: ('Demandes de devis locaux', 'dev', ['connecter', 'faciliter'], [
        'Permettre à un habitant ou à une entreprise de déposer une demande structurée et de la transmettre à un ou plusieurs professionnels locaux pertinents. Le système doit rester une mise en relation et ne pas devenir une marketplace de prestations.']),
    17: ('Petites annonces locales', 'option', ['connecter'], [
        'Prévoir éventuellement une rubrique de petites annonces territoriales, avec catégories, publication, modération et contact. Cette fonctionnalité nécessite une politique claire de modération et de responsabilité.']),
    18: ('Emploi local', 'ext', ['informer', 'connecter'], [
        'terricom dispose déjà d’une dimension recrutement. Celle-ci pourrait être étendue vers un véritable espace emploi territorial : offres, candidatures ou orientation vers les offres, alternance, stages et apprentissage.']),
    19: ('Formation et apprentissage local', 'dev', ['informer'], [
        'Ajouter un référencement des organismes, formations, apprentissages, stages et dispositifs disponibles sur le territoire, avec recherche par domaine, public et localisation.']),
    20: ('Mobilité et covoiturage local', 'dev', ['informer'], [
        'Ajouter éventuellement une information territoriale sur la mobilité : transports, points de covoiturage, initiatives locales, vélo, mobilité douce ou événements liés à la mobilité. Une mise en relation de covoiturage pourrait être étudiée séparément.']),
    21: ('Agenda personnalisé habitant', 'dev', ['informer'], [
        'Permettre à l’habitant de sélectionner ses centres d’intérêt, communes et catégories afin de disposer d’un agenda personnalisé.']),
    22: ('Favoris et suivi d’acteurs', 'dev', ['connecter'], [
        'Permettre de mettre en favoris des entreprises, commerces, lieux, événements, associations ou contenus et de recevoir des informations pertinentes sur ces éléments.']),
    23: ('Compte habitant personnalisé', 'dev', ['connecter', 'faciliter'], [
        'Structurer un espace personnel permettant de gérer préférences, favoris, inscriptions, notifications et consentements. Le compte doit rester centré sur les services et l’animation territoriale.']),
    24: ('Parcours, challenges et gamification', 'ext', ['animer'], [
        'Étendre les circuits touristiques existants vers des parcours de découverte, challenges, missions, badges, points et récompenses. La gamification peut concerner tourisme, patrimoine, commerce, événements ou découverte du territoire.']),
    25: ('Assistant conversationnel territorial', 'ext', ['informer'], [
        'Centraliser les capacités IA existantes dans un assistant destiné aux habitants et visiteurs : recherche d’informations, événements, commerces, lieux, horaires, tourisme, activités et services présents dans terricom.']),
}
STATUTS = {'dev': ('À développer', 'st-dev'), 'ext': ('Extension de l’existant', 'st-ext'), 'option': ('Option à étudier', 'st-opt')}

GROUPES = [
    ('Participation citoyenne', 'Donner la parole aux habitants, et leur rendre compte.', [2, 3, 4, 5]),
    ('Services communaux', 'Informer au bon moment, faciliter l’usage des équipements.', [6, 7]),
    ('Associations et événements', 'Tous les acteurs de la vie locale, et leurs rendez-vous.', [8, 9, 10]),
    ('Commerce local et fidélité', 'Faire revenir les habitants chez leurs commerçants.', [11, 12, 13, 14]),
    ('Mise en relation', 'Du besoin de l’habitant au professionnel d’à côté.', [15, 16, 17]),
    ('Emploi, formation, mobilité', 'Vivre et travailler sur le territoire.', [18, 19, 20]),
    ('L’espace de l’habitant', 'Un territoire à la carte, selon ses centres d’intérêt.', [21, 22, 23]),
    ('Découvrir et être guidé', 'Jouer le territoire, et trouver la bonne réponse.', [24, 25]),
]
# pages : liste de groupes (et de numéros) par page, pour tenir en A4
PAGES = [[0], [1, 2], [3], [4, 5], [6, 7]]


def carte(n):
    titre, statut, piliers, paras = F[n]
    lib, cls = STATUTS[statut]
    tags = ''.join(f'<span class="tag">{icone(p, 12)}{NOM_PILIER[p]}</span>' for p in piliers)
    corps = ''.join(f'<p>{x}</p>' for x in paras)
    return (f'<article class="feat f-{statut}"><div class="fh"><span class="fn">{n}</span><h3>{titre}</h3>'
            f'<span class="st {cls}">{lib}</span></div>{corps}<div class="tags">{tags}</div></article>')


def groupe(g):
    titre, sous, nums = GROUPES[g]
    return (f'<section class="grp"><div class="gh"><span class="eyebrow">{titre}</span><p class="gs">{sous}</p></div>'
            + ''.join(carte(n) for n in nums) + '</section>')


def pied(n):
    return (f'<div class="foot"><span class="brand">terricom<i>.</i></span><span>Fonctionnalités à développer · note de cadrage</span>'
            f'<span class="num">{n}</span></div>')


pages = []
# 1 · couverture
pills = ''.join(f'<span class="pill">{icone(k, 14)}{n}</span>' for k, n, _ in PILIERS)
pages.append(('dark cover', f'''
<div class="blob" style="width:150mm;height:150mm;right:-45mm;top:-55mm;background:var(--green)"></div>
<div class="blob" style="width:70mm;height:70mm;left:-25mm;bottom:-30mm;background:#23332C"></div>
<div class="top"><span>terricom.fr</span><span>Document interne · septembre 2026</span></div>
<div class="logo"><span class="mark"><span>t</span></span><span class="wm">terricom<i>.</i></span></div>
<span class="eyebrow" style="color:var(--amber);margin-top:26mm">Note de cadrage</span>
<h1>Fonctionnalités à développer <em>dans le périmètre de la plateforme.</em></h1>
<p class="lead">Les fonctionnalités manquantes qui restent cohérentes avec le positionnement de terricom : informer, promouvoir, animer, connecter, faire participer, faciliter, mesurer et piloter la vie du territoire.</p>
<div class="pills">{pills}</div>
<div class="covnote"><b>24 fonctionnalités</b>, dont 3 extensions de l’existant et 3 options à étudier · 1 frontière à conserver</div>
''', False))

# 2 · positionnement + sommaire
lignes = []
for titre, _, nums in GROUPES:
    items = ''.join(f'<li><span class="fn s">{n}</span>{F[n][0]}<span class="st mini {STATUTS[F[n][1]][1]}">{STATUTS[F[n][1]][0]}</span></li>' for n in nums)
    lignes.append(f'<div class="toc-g"><h4>{titre}</h4><ul>{items}</ul></div>')
pages.append(('', f'''
<span class="eyebrow">1 · Positionnement fonctionnel</span>
<h2>Une plateforme d’<em>animation territoriale.</em></h2>
<div class="split2">
<div><p class="lead-d">terricom est une plateforme d’animation territoriale. Son périmètre relie l’intercommunalité, les communes, les entreprises et commerces, les associations, les habitants et les visiteurs.</p>
<p>L’objectif n’est pas de devenir un logiciel administratif généraliste ni de remplacer les logiciels métiers de la collectivité. Les développements proposés restent donc centrés sur : informer, promouvoir, animer, connecter, faire participer, faciliter et mesurer la vie du territoire.</p></div>
<div class="legend"><h4>Lecture du document</h4>
<p><span class="st st-dev">À développer</span> une fonctionnalité nouvelle, dans le périmètre.</p>
<p><span class="st st-ext">Extension de l’existant</span> une brique déjà présente dans terricom, à étendre.</p>
<p><span class="st st-opt">Option à étudier</span> à cadrer séparément avant toute décision.</p>
<p style="margin-top:3mm">Les étiquettes <span class="tag">{icone('participer', 12)}Faire participer</span> renvoient au périmètre cible (section 27).</p></div>
</div>
<h3 class="h3s">Sommaire des fonctionnalités</h3>
<div class="toc">{''.join(lignes)}</div>
''', True))

for grp in PAGES:
    pages.append(('', ''.join(groupe(g) for g in grp), True))

# frontière + synthèse
tuiles = ''.join(f'<div class="pil"><span class="pic">{icone(k, 20)}</span><h4>{n}</h4><p>{d[0].upper() + d[1:]}</p></div>' for k, n, d in PILIERS)
pages.append(('', f'''
<span class="eyebrow">26 · Frontière à conserver</span>
<div class="frontier"><h2 style="color:var(--cream)">Pas de <em>marketplace e-commerce.</em></h2>
<p>La marketplace e-commerce générale ne fait pas partie du périmètre à ajouter. Le positionnement actuel de terricom est de générer visibilité, contacts et fréquentation vers les acteurs locaux, sans devenir une plateforme de vente en ligne générale.</p>
<p class="fr-note">C’est la même ligne pour la billetterie (10), la carte de fidélité (13), les demandes de devis (16) et les petites annonces (17) : de l’animation et de la mise en relation, pas de la vente en ligne.</p></div>
<span class="eyebrow" style="margin-top:4mm">27 · Synthèse du périmètre cible</span>
<h2>Huit verbes <em>pour un territoire vivant.</em></h2>
<div class="pils">{tuiles}</div>
''', True))

# consigne
briques = ['Comptes', 'Rôles', 'Communes', 'Entreprises', 'Établissements', 'Événements', 'Campagnes', 'Notifications', 'Formulaires', 'Statistiques', 'Cartes', 'Briques IA']
hors = ['Logiciel administratif généraliste', 'SIG métier', 'Logiciel RH ou finances', 'Marketplace e-commerce complète']
pages.append(('', f'''
<span class="eyebrow">28 · Consigne générale pour l’IA de développement</span>
<h2>Des extensions <em>cohérentes de terricom.</em></h2>
<p class="lead-d">Toutes les fonctionnalités de cette note doivent être conçues comme des extensions cohérentes de terricom. Elles doivent réutiliser autant que possible les briques déjà présentes dans la plateforme.</p>
<div class="bl"><h4>À réutiliser</h4><div class="chips">{''.join(f'<span class="chip ok">{b}</span>' for b in briques)}</div></div>
<div class="bl"><h4>Ce que terricom ne doit pas devenir</h4><div class="chips">{''.join(f'<span class="chip no">{b}</span>' for b in hors)}</div></div>
<div class="final"><p>L’objectif est de <b>renforcer l’animation territoriale</b> sans transformer terricom en logiciel administratif généraliste, en SIG métier, en logiciel RH/finances ou en marketplace e-commerce complète.</p></div>
<div class="sign"><span class="mark"><span>t</span></span><span class="wm" style="font-size:40px">terricom<i>.</i></span><span class="bl2">Le territoire, <em>en vitrine.</em></span></div>
''', True))

CSS = POLICES + '''
@page { size: A4; margin: 0; }
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body { font-family: 'Instrument', system-ui, sans-serif; color: var(--ink); background: #9a9a9a; font-size: 12.2px; line-height: 1.5; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
@media screen { .page { margin: 12px auto; box-shadow: 0 6px 30px rgba(0,0,0,.25); } }
@media print { body { background: none; } }
.page { width: 210mm; height: 297mm; position: relative; overflow: hidden; background: var(--cream); break-after: page; padding: 16mm 17mm 22mm; display: flex; flex-direction: column; gap: 4mm; }
.page:last-child { break-after: auto; }
.page.dark { background: var(--ink); color: var(--cream); }
.blob { position: absolute; border-radius: 50%; filter: blur(0); opacity: .9; }
.foot { position: absolute; left: 17mm; right: 17mm; bottom: 9mm; display: flex; justify-content: space-between; align-items: center; font-size: 9px; color: var(--muted); border-top: 1px solid var(--line); padding-top: 2.6mm; }
.foot .brand { font-family: 'Bricolage'; font-weight: 800; font-size: 11px; color: var(--ink); letter-spacing: -0.02em; }
.foot .brand i, .wm i { color: var(--amber); font-style: normal; }
.foot .num { font-weight: 700; color: var(--ink); }
.eyebrow { font-size: 10px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: var(--brick); }
h1, h2, h3, h4 { font-family: 'Bricolage'; letter-spacing: -0.03em; margin: 0; }
h1 em, h2 em { font-style: normal; color: var(--green); }
.dark h1 em { color: var(--amber); }
h2 { font-size: 30px; line-height: 1.02; font-weight: 800; }
.mark { width: 34px; height: 34px; border-radius: 50% 50% 50% 14%; transform: rotate(-45deg); background: var(--amber); display: inline-grid; place-items: center; flex-shrink: 0; }
.mark span { transform: rotate(45deg); font-family: 'Bricolage'; font-weight: 800; font-size: 22px; line-height: 1; color: var(--ink); margin-top: -3px; }
.wm { font-family: 'Bricolage'; font-weight: 800; letter-spacing: -0.05em; line-height: .9; }
/* couverture */
.cover .top { position: relative; display: flex; justify-content: space-between; font-size: 10px; color: var(--sage); }
.cover .logo { position: relative; display: flex; align-items: center; gap: 12px; margin-top: 30mm; }
.cover .logo .mark { width: 16mm; height: 16mm; } .cover .logo .mark span { font-size: 38px; }
.cover .logo .wm { font-size: 64px; color: var(--cream); }
.cover h1 { position: relative; font-size: 44px; line-height: 1.02; font-weight: 800; color: var(--cream); max-width: 160mm; margin-top: 3mm; }
.cover .lead { position: relative; font-size: 15px; line-height: 1.5; color: var(--sage2); max-width: 150mm; margin: 4mm 0 0; }
.pills { position: relative; display: flex; flex-wrap: wrap; gap: 6px; margin-top: auto; }
.pill { display: inline-flex; align-items: center; gap: 6px; border: 1px solid rgba(255,255,255,.25); border-radius: 999px; padding: 5px 11px; font-size: 11.5px; color: var(--sage2); }
.pill svg { color: var(--amber); }
.covnote { position: relative; font-size: 11px; color: var(--sage); margin-top: 5mm; }
/* page 2 */
.split2 { display: grid; grid-template-columns: 1.25fr 1fr; gap: 7mm; align-items: start; }
.lead-d { font-size: 14.5px; line-height: 1.5; color: var(--ink2); margin: 0 0 3mm; }
.split2 p { margin: 0 0 2.5mm; color: var(--text2); }
.legend { background: var(--paper); border: 1px solid var(--line); border-radius: 14px; padding: 5mm; }
.legend h4 { font-size: 14px; margin-bottom: 2.5mm; }
.legend p { font-size: 11px; margin: 0 0 2mm; }
.h3s { font-size: 18px; margin-top: 2mm; }
.toc { display: grid; grid-template-columns: 1fr 1fr; gap: 3mm 7mm; }
.toc-g h4 { font-size: 12.5px; color: var(--green); margin-bottom: 1mm; }
.toc-g ul { list-style: none; margin: 0; padding: 0; }
.toc-g li { display: flex; align-items: center; gap: 6px; font-size: 11px; padding: 1.1mm 0; border-bottom: 1px solid var(--line); }
.toc-g li .mini { margin-left: auto; }
/* fonctionnalités */
.grp { display: flex; flex-direction: column; gap: 3mm; }
.grp + .grp { margin-top: 3mm; }
.gh { display: flex; align-items: baseline; gap: 10px; border-bottom: 2px solid var(--ink); padding-bottom: 1.5mm; }
.gh .eyebrow { font-size: 12px; }
.gs { margin: 0; font-size: 11.5px; color: var(--muted); font-style: italic; }
.feat { background: var(--paper); border: 1px solid var(--line); border-radius: 14px; padding: 4mm 5mm; border-left: 5px solid var(--green); }
.feat.f-ext { border-left-color: var(--amber); }
.feat.f-option { border-left-color: var(--sage); border-style: dashed; border-left-style: solid; }
.fh { display: flex; align-items: center; gap: 8px; margin-bottom: 1.5mm; }
.fh h3 { font-size: 16.5px; font-weight: 800; }
.fn { display: inline-grid; place-items: center; min-width: 24px; height: 24px; padding: 0 5px; border-radius: 8px; background: var(--ink); color: var(--amber); font-family: 'Bricolage'; font-weight: 800; font-size: 12px; }
.fn.s { min-width: 20px; height: 20px; font-size: 10.5px; border-radius: 6px; }
.st { margin-left: auto; font-size: 9.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; border-radius: 999px; padding: 3px 9px; white-space: nowrap; }
.st-dev { background: var(--mint); color: var(--green); }
.st-ext { background: #FBE6C9; color: #8A4E13; }
.st-opt { background: var(--sand); color: var(--muted); }
.st.mini { font-size: 8px; padding: 2px 7px; }
.legend .st { margin-left: 0; margin-right: 4px; }
.feat p { margin: 0 0 1.5mm; color: var(--text2); font-size: 11.8px; }
.tags { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 1.5mm; }
.tag { display: inline-flex; align-items: center; gap: 4px; font-size: 9.5px; font-weight: 700; color: var(--green); background: var(--mint); border-radius: 999px; padding: 2px 8px; }
/* frontière et synthèse */
.frontier { background: var(--ink); color: var(--sage2); border-radius: 18px; padding: 7mm 8mm; }
.frontier h2 em { color: var(--amber); }
.frontier p { font-size: 13px; margin: 3mm 0 0; }
.fr-note { color: var(--sage); font-size: 11.5px !important; border-top: 1px solid rgba(255,255,255,.15); padding-top: 3mm; }
.pils { display: grid; grid-template-columns: 1fr 1fr; gap: 3mm; }
.pil { background: var(--paper); border: 1px solid var(--line); border-radius: 14px; padding: 4mm; display: grid; grid-template-columns: 34px 1fr; column-gap: 10px; }
.pil .pic { grid-row: span 2; width: 34px; height: 34px; border-radius: 10px; background: var(--green); color: var(--amber); display: grid; place-items: center; }
.pil h4 { font-size: 14.5px; text-transform: uppercase; letter-spacing: .02em; }
.pil p { margin: 1mm 0 0; font-size: 11.2px; color: var(--text2); }
/* consigne */
.bl { background: var(--paper); border: 1px solid var(--line); border-radius: 14px; padding: 5mm; }
.bl h4 { font-size: 15px; margin-bottom: 3mm; }
.chips { display: flex; flex-wrap: wrap; gap: 6px; }
.chip { font-size: 11.5px; font-weight: 600; border-radius: 999px; padding: 4px 11px; }
.chip.ok { background: var(--mint); color: var(--green); }
.chip.no { background: #F6DAD5; color: #9C3328; text-decoration: line-through; text-decoration-thickness: 1px; }
.final { background: var(--green); color: var(--cream); border-radius: 16px; padding: 6mm 7mm; }
.final p { margin: 0; font-size: 15px; line-height: 1.5; }
.final b { color: var(--amber); }
.sign { margin-top: auto; display: flex; align-items: center; gap: 10px; }
.sign .bl2 { margin-left: auto; font-family: 'Bricolage'; font-weight: 800; font-size: 18px; letter-spacing: -0.02em; }
.sign .bl2 em { font-style: normal; color: var(--green); }
'''

html = ['<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>terricom · Fonctionnalités à développer</title>', f'<style>{CSS}</style></head><body>']
for i, (cls, corps, numero) in enumerate(pages, start=1):
    html.append(f'<section class="page {cls}">{corps}{pied(i) if numero else ""}</section>')
html.append('</body></html>')
(ICI / 'fonctionnalites.html').write_text('\n'.join(html))
print(f'{len(pages)} pages → docs/cadrage/fonctionnalites.html')
