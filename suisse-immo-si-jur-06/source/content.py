"""Contenu de la fiche SI-JUR-06 — Prospection téléphonique (Suisse Immo).

Source unique pour le HTML/PDF, le Word et le formulaire remplissable.

Mini-balisage dans les textes :
  **gras**
  [[nom]]        champ à compléter, affiché « [À COMPLÉTER] »
  [[nom|…]]      champ court, affiché « […] »
  {{cb:nom}}     case à cocher
Les espaces insécables et apostrophes typographiques sont posés par fr().
"""

import json
import re

REF = "SI-JUR-06"
VERSION = "Version 1 · septembre 2026"
DOC_TITLE = "Prospection téléphonique"
FOOTER_LEFT = f"Suisse Immo · {REF}"
FOOTER_CENTER = "Prospection téléphonique"
TOTAL_PAGES = 7

NBSP = " "


def fr(s: str) -> str:
    """Typographie française : apostrophes, insécables, guillemets."""
    s = s.replace("'", "’")
    s = re.sub(r" ([:;?!])", NBSP + r"\1", s)
    s = s.replace("« ", "«" + NBSP).replace(" »", NBSP + "»")
    s = re.sub(r"(\d) (\d{3})\b", r"\1" + NBSP + r"\2", s)
    s = re.sub(r"(\d) (h|€|%|mois|ans?|jours)\b", r"\1" + NBSP + r"\2", s)
    s = re.sub(r"(\d) €", r"\1" + NBSP + "€", s)
    s = re.sub(r"\b(n°|art\.|p\.) ", r"\1" + NBSP, s)
    s = re.sub(r"\b([LDR])\. (\d)", r"\1." + NBSP + r"\2", s)
    s = s.replace("Suisse Immo", "Suisse" + NBSP + "Immo").replace("SUISSE IMMO", "SUISSE" + NBSP + "IMMO")
    return s


def walk(obj):
    if isinstance(obj, str):
        return fr(obj)
    if isinstance(obj, list):
        return [walk(o) for o in obj]
    if isinstance(obj, dict):
        return {k: (v if k in ("t", "id", "name", "kind", "tone", "widths") else walk(v))
                for k, v in obj.items()}
    return obj


# ---------------------------------------------------------------------------
# Page 1 — couverture, l'essentiel, sommaire
# ---------------------------------------------------------------------------
page1 = [
    {"t": "cover",
     "kicker": "Réglementation · Prospection",
     "title": DOC_TITLE,
     "subtitle": "Depuis le 11 août 2026, un particulier ne peut plus être appelé à des fins "
                 "commerciales sans son accord préalable. Mode d'emploi pour les agences et les "
                 "agents commerciaux du réseau.",
     "meta": [
         ["Pour qui", "Responsables d'agence, agents commerciaux et assistant(e)s du réseau Suisse Immo"],
         ["Textes", "Code de la consommation, art. L. 223-1 et suivants ; décret n° 2026-662 du 23 juillet 2026"],
         ["En vigueur", "Depuis le 11 août 2026"],
         ["À jour au", "27 septembre 2026"],
     ]},
    {"t": "h2", "text": "L'essentiel"},
    {"t": "points", "items": [
        ["Pas d'accord, pas d'appel.",
         "Un particulier ne peut plus être appelé à des fins commerciales s'il n'a pas donné son "
         "accord au préalable. Le silence ne vaut pas accord, et la liste Bloctel a disparu."],
        ["Un accord par société.",
         "L'accord est donné à la société qui exploite l'agence et qui le recueille. Il ne vaut ni "
         "pour les autres agences du réseau, ni pour la tête de réseau."],
        ["Douze mois au plus.",
         "L'accord dure un an au maximum et n'est jamais reconduit tacitement. Il se retire à tout "
         "moment, aussi simplement qu'il a été donné."],
        ["Une preuve pendant trois ans.",
         "Chaque accord est enregistré, horodaté et conservé trois ans. La personne peut en obtenir "
         "gratuitement une copie sur support durable."],
        ["Des appels encadrés.",
         "Même avec un accord, on n'appelle que du lundi au vendredi, hors jours fériés, de 10 h à "
         "13 h et de 14 h à 20 h, et au plus quatre fois sur trente jours."],
        ["Des sanctions lourdes.",
         "Un contrat obtenu par un appel illicite est nul, et un mandat nul ne donne droit à aucun "
         "honoraire. L'amende peut atteindre 375 000 € pour une société."],
    ]},
    {"t": "toc", "items": [
        ["1", "Le principe", "2"],
        ["2", "Ce qui reste permis", "3"],
        ["3", "Recueillir l'accord", "4"],
        ["4", "Prouver : le registre des accords", "5"],
        ["5", "Pendant et après l'appel", "5"],
        ["6", "Prestataires, fichiers et sanctions", "6"],
        ["A", "Annexe · Formulaire de recueil du consentement", "7"],
    ]},
    {"t": "callout",
     "text": "**Dans le réseau Suisse Immo.** Chaque agence est une société distincte, titulaire de "
             "sa propre carte professionnelle. Le formulaire en annexe se complète donc au nom de la "
             "société qui exploite l'agence, et les agents commerciaux l'utilisent au nom de l'agence "
             "dont ils détiennent l'attestation d'habilitation."},
]

# ---------------------------------------------------------------------------
# Page 2 — 1. Le principe
# ---------------------------------------------------------------------------
page2 = [
    {"t": "h2", "num": "1", "text": "Le principe"},
    {"t": "lead",
     "text": "Jusqu'au 10 août 2026, un professionnel pouvait appeler tout particulier qui ne s'était "
             "pas inscrit sur la liste d'opposition Bloctel. Depuis le 11 août, la logique est "
             "inversée : il est interdit de démarcher par téléphone un consommateur qui n'a pas "
             "exprimé son consentement au préalable, que l'appel soit passé directement ou par un "
             "tiers agissant pour le compte du professionnel."},
    {"t": "p",
     "text": "La charge de la preuve pèse sur le professionnel. En cas de contrôle ou de litige, "
             "l'agence doit démontrer que l'accord a été recueilli dans les formes prévues ; faute de "
             "preuve, l'appel est traité comme un appel passé sans accord."},
    {"t": "h3", "text": "Un accord valable réunit cinq qualités"},
    {"t": "table", "kind": "def", "widths": [22, 78],
     "head": ["Qualité", "Concrètement"],
     "rows": [
         ["**Libre**", "Le refus n'a aucune conséquence : il ne conditionne ni une estimation, ni une "
                       "visite, ni la remise d'un document."],
         ["**Spécifique**", "Il vise la prospection par téléphone, pour une société identifiée et un "
                            "objet précis. Un accord donné « au réseau » ou « à nos partenaires » ne "
                            "suffit pas."],
         ["**Éclairé**", "La personne a pris connaissance, avant de s'engager, des mentions imposées "
                         "par le décret du 23 juillet 2026 (voir p. 4)."],
         ["**Univoque**", "Il résulte d'un acte positif clair : une case cochée par la personne "
                          "elle-même. Une case pré-cochée, le silence ou l'absence d'opposition ne "
                          "valent pas accord."],
         ["**Révocable**", "Il se retire à tout moment, sans justification, aussi simplement qu'il a "
                           "été donné."],
     ]},
    {"t": "h3", "text": "Qui est concerné"},
    {"t": "p",
     "text": "Tout appel de prospection vers un particulier : propriétaire vendeur ou bailleur, "
             "acquéreur, locataire, ancien client. Peu importe qui compose le numéro : responsable "
             "d'agence, salarié, agent commercial ou prestataire. L'appel qu'un agent commercial passe "
             "au nom de l'agence engage l'agence comme si elle l'avait passé elle-même."},
    {"t": "p",
     "text": "Le texte protège les consommateurs. Les appels à des professionnels agissant dans le "
             "cadre de leur activité (commerçant, société, marchand de biens) n'entrent pas dans ce "
             "régime. Pour une SCI familiale ou une indivision, appliquez par prudence la même règle "
             "qu'à un particulier."},
    {"t": "h3", "text": "Ce qui n'est pas de la prospection"},
    {"t": "ul", "items": [
        "**Répondre à une demande** de la personne : rappel demandé, demande d'estimation, demande de "
        "visite. L'appel reste limité à l'objet de la demande.",
        "**Appeler dans le cadre d'un contrat en cours**, lorsque l'appel a un rapport avec l'objet de "
        "ce contrat : par exemple, le suivi d'un mandat en cours d'exécution (voir p. 3).",
    ]},
    {"t": "callout",
     "text": "**Bloctel a disparu avec la réforme.** Vérifier qu'un numéro ne figure pas sur une liste "
             "d'opposition ne protège plus de rien : seul compte l'accord préalable, et la preuve que "
             "l'agence en conserve."},
]

# ---------------------------------------------------------------------------
# Page 3 — 2. Ce qui reste permis
# ---------------------------------------------------------------------------
page3 = [
    {"t": "h2", "num": "2", "text": "Ce qui reste permis"},
    {"t": "lead",
     "text": "Le tableau couvre les situations les plus fréquentes en agence. En cas de doute, une "
             "seule règle : sans accord valide au registre de l'agence, on n'appelle pas."},
    {"t": "table", "kind": "cases", "widths": [33, 15, 52],
     "head": ["Situation", "Appel", "Condition ou raison"],
     "rows": [
         ["Particulier qui vend ou loue par annonce (entre particuliers, Leboncoin…)",
          {"tone": "no", "text": "Non"},
          "Publier une annonce n'est pas consentir à être démarché. La mention « agences acceptées » "
          "ne suffit pas : elle ne désigne pas votre agence et ne laisse aucune preuve conforme au décret."],
         ["Vendeur dont l'agence détient un mandat en cours",
          {"tone": "yes", "text": "Oui"},
          "Pour tout ce qui touche ce mandat : compte rendu, visites, offres, ajustement du prix, "
          "services liés à la vente."],
         ["Ce même vendeur, pour un projet sans lien avec le mandat",
          {"tone": "cond", "text": "Avec accord"},
          "L'exception ne couvre que l'objet du contrat en cours."],
         ["Ancien client, vente conclue ou mandat expiré",
          {"tone": "cond", "text": "Avec accord"},
          "L'exception tombe avec la fin du contrat : proposez le formulaire avant l'échéance du mandat."],
         ["Personne qui a demandé une estimation, un rappel ou une visite",
          {"tone": "yes", "text": "Oui, pour répondre"},
          "Pour la relancer ensuite ou lui proposer autre chose, recueillez son accord lors du rendez-vous."],
         ["Acquéreur titulaire d'un mandat de recherche",
          {"tone": "yes", "text": "Oui"},
          "Dans la limite de l'objet du mandat de recherche."],
         ["Personne recommandée par un client ou un proche",
          {"tone": "cond", "text": "Avec accord"},
          "Personne ne peut consentir à la place d'un autre. Laissez vos coordonnées : c'est à la "
          "personne recommandée de vous contacter."],
         ["Contact issu d'un fichier acheté, loué ou transmis",
          {"tone": "cond", "text": "Avec accord"},
          "Uniquement si le fournisseur prouve, contact par contact, un accord donné au nom de votre "
          "agence (voir p. 6)."],
         ["Contact transmis par une autre agence Suisse Immo",
          {"tone": "cond", "text": "Avec accord"},
          "L'accord donné à une agence ne passe pas à une autre : chaque agence recueille le sien."],
         ["Professionnel agissant pour son activité (commerce, société)",
          {"tone": "out", "text": "Hors champ"},
          "Le texte protège les consommateurs. Restez courtois et cessez à la première demande."],
         ["Prospection par SMS ou par e-mail",
          {"tone": "cond", "text": "Avec accord"},
          "Accord distinct, par une case séparée du formulaire. Seule exception : un client de l'agence, "
          "pour des services analogues, s'il peut refuser à chaque envoi (CPCE, art. L. 34-5)."],
         ["Boîtage, courrier, porte-à-porte",
          {"tone": "out", "text": "Hors champ"},
          "Ces canaux ne relèvent pas du régime téléphonique. Aucun appel de suivi, en revanche, sans accord."],
     ]},
    {"t": "callout",
     "text": "**Le bon réflexe.** Chaque rendez-vous en face à face (estimation, visite, signature, "
             "salon) est l'occasion de proposer le formulaire en annexe. Ce sont ces accords qui "
             "rendront possibles les appels des douze mois suivants."},
]

# ---------------------------------------------------------------------------
# Page 4 — 3. Recueillir l'accord
# ---------------------------------------------------------------------------
page4 = [
    {"t": "h2", "num": "3", "text": "Recueillir l'accord"},
    {"t": "h3", "text": "Les mentions obligatoires", "first": True},
    {"t": "p",
     "text": "Le décret n° 2026-662 du 23 juillet 2026 impose une demande claire et compréhensible, "
             "qui indique au minimum :"},
    {"t": "ul", "items": [
        "l'identité du professionnel au profit duquel l'accord est demandé et, le cas échéant, celle "
        "du tiers qui le recueille pour son compte ;",
        "l'objet précis de la prospection envisagée ;",
        "la durée de l'accord, qui ne peut dépasser un an ;",
        "la possibilité de retirer l'accord à tout moment, et la façon de le faire ;",
        "le droit d'accéder à la preuve de l'accord, conservée sur support durable.",
    ]},
    {"t": "p",
     "text": "S'y ajoutent les informations dues au titre du RGPD (art. 13) : responsable du "
             "traitement, finalité, base légale, durée de conservation, droits de la personne et "
             "possibilité de saisir la CNIL. Le formulaire en annexe réunit l'ensemble."},
    {"t": "h3", "text": "Les règles de forme"},
    {"t": "ul", "items": [
        "**Une demande à part.** Jamais noyée dans un mandat, un bon de visite ou des conditions générales.",
        "**Une case par canal.** Le téléphone d'un côté, le courriel et le SMS de l'autre. Aucune case "
        "pré-cochée.",
        "**Un vrai choix.** La case « Je refuse » figure au même niveau que les autres, et le refus ne "
        "change rien aux prestations.",
        "**Une seule société.** Celle qui exploite l'agence. Pas d'accord « pour le réseau » ni « pour "
        "nos partenaires ».",
        "**Un texte figé.** Le formulaire s'emploie tel quel. Toute modification crée une nouvelle "
        "version, à numéroter et à conserver.",
    ]},
    {"t": "h3", "text": "Où et comment le recueillir"},
    {"t": "table", "kind": "def", "widths": [27, 73],
     "head": ["Canal", "Mode opératoire"],
     "rows": [
         ["**En agence ou en rendez-vous**",
          "Formulaire papier signé, ou formulaire à remplir sur tablette. Inscription au registre le "
          "jour même, puis archivage de l'original."],
         ["**Sur le site internet**",
          "Formulaire en ligne reprenant le même texte, cases non pré-cochées, horodatage automatique "
          "de chaque réponse."],
         ["**Au cours d'un appel entrant**",
          "Un accord donné oralement se prouve mal : envoyez le lien du formulaire en ligne ou faites-le "
          "signer au premier rendez-vous."],
         ["**Par un agent commercial**",
          "Le même formulaire, au nom de l'agence dont il détient l'attestation d'habilitation. Jamais "
          "de formulaire personnel, ni de fichier tenu en dehors de l'agence."],
     ]},
    {"t": "callout",
     "text": "**L'accord suit la société, pas l'enseigne.** Chaque agence Suisse Immo est une société "
             "distincte, titulaire de sa propre carte professionnelle. Un accord recueilli par l'une ne "
             "permet ni aux autres agences du réseau, ni à la tête de réseau, d'appeler la personne."},
]

# ---------------------------------------------------------------------------
# Page 5 — 4. Registre · 5. Pendant l'appel
# ---------------------------------------------------------------------------
page5 = [
    {"t": "h2", "num": "4", "text": "Prouver : le registre des accords"},
    {"t": "p",
     "text": "Chaque agence tient un registre des accords, papier ou numérique, sous la responsabilité "
             "du responsable d'agence. Le décret impose un enregistrement horodaté de chaque accord."},
    {"t": "table", "kind": "def", "widths": [22, 78],
     "head": ["Rubrique", "Contenu"],
     "rows": [
         ["**Recueil**", "Date et heure : horodatage automatique en ligne, saisie le jour même pour le papier."],
         ["**Canal**", "Papier, tablette ou formulaire en ligne."],
         ["**Texte présenté**", "Version exacte du formulaire : SI-JUR-06, version 1."],
         ["**Choix**", "Téléphone, courriel et SMS, ou refus ; créneau de préférence le cas échéant."],
         ["**Collaborateur**", "Salarié ou agent commercial qui a recueilli l'accord."],
         ["**Échéance**", "Date du recueil plus douze mois au maximum."],
         ["**Retrait**", "Date, heure et canal du retrait, qui prend effet immédiatement."],
         ["**Appels**", "Date, heure et issue de chaque appel (plafond : quatre sur trente jours)."],
     ]},
    {"t": "p",
     "text": "La preuve se conserve au moins trois ans à compter du recueil, sur support durable. La "
             "personne peut en obtenir gratuitement une copie à tout moment : répondez sans attendre, et "
             "au plus tard dans le délai d'un mois prévu par le RGPD."},
    {"t": "h2", "num": "5", "text": "Pendant et après l'appel", "spaced": True},
    {"t": "h3", "text": "Avant de composer le numéro", "first": True},
    {"t": "checklist", "items": [
        "L'accord figure au registre, il date de moins de douze mois et n'a pas été retiré.",
        "L'objet de l'appel entre dans l'objet de l'accord.",
        "Le jour et l'heure sont permis : du lundi au vendredi, hors jours fériés, de 10 h à 13 h ou "
        "de 14 h à 20 h.",
        "La personne a reçu moins de quatre sollicitations au cours des trente derniers jours.",
        "Elle n'a pas refusé un appel au cours des soixante derniers jours.",
    ]},
    {"t": "h3", "text": "Au décroché"},
    {"t": "ul", "items": [
        "**Présentez-vous** : votre nom, l'agence, la société pour le compte de laquelle vous appelez et "
        "le caractère commercial de l'appel (C. consom., art. L. 221-16).",
        "**Appelez depuis un numéro identifiable**, jamais en numéro masqué. N'utilisez ni automate "
        "d'appel ni message préenregistré : ils obéissent à des règles plus strictes.",
        "**Un retrait, même oral, s'applique aussitôt** : notez-le au registre, puis plus aucun appel "
        "ni message.",
    ]},
    {"t": "h3", "text": "Après l'appel"},
    {"t": "ul", "items": [
        "Toute proposition faite par téléphone se confirme par écrit : le particulier n'est engagé "
        "qu'une fois que l'offre a été signée et acceptée sur support durable (C. consom., art. L. 221-16).",
        "Un mandat signé à distance ou hors de l'agence ouvre un délai de rétractation de quatorze "
        "jours ; remettez le formulaire de rétractation avec le mandat (C. consom., art. L. 221-18).",
        "Mettez le registre à jour : appel passé, issue, refus ou retrait.",
    ]},
]

# ---------------------------------------------------------------------------
# Page 6 — après l'appel · 6. Prestataires, fichiers et sanctions
# ---------------------------------------------------------------------------
page6 = [
    {"t": "h2", "num": "6", "text": "Prestataires, fichiers et sanctions"},
    {"t": "p",
     "text": "Confier des appels à un centre d'appels, ou acheter un fichier, ne transfère pas le "
             "risque. L'agence doit toujours pouvoir prouver que chaque personne appelée lui a donné son "
             "accord, pour l'objet de l'appel."},
    {"t": "h3", "text": "Ce que prévoit tout contrat avec un prestataire"},
    {"t": "ul", "items": [
        "l'obligation de n'appeler que des personnes ayant donné à l'agence un accord valide ;",
        "la remise, à première demande, de la preuve individuelle de chaque accord ;",
        "le respect des créneaux, du plafond d'appels et des retraits signalés par l'agence ;",
        "l'interdiction de sous-traiter sans l'accord écrit de l'agence ;",
        "les garanties exigées d'un sous-traitant de données personnelles (RGPD, art. 28).",
    ]},
    {"t": "h3", "text": "Ce que risquent l'agence et l'appelant"},
    {"t": "table", "kind": "def", "widths": [24, 76],
     "head": ["Sanction", "Portée"],
     "rows": [
         ["**Nullité**", "Le contrat conclu à la suite d'un appel illicite est nul. Pour un mandat : "
                         "aucun honoraire, et une vente fragilisée."],
         ["**Amende administrative**", "Prononcée par la DGCCRF : jusqu'à 75 000 € pour une personne "
                                       "physique et 375 000 € pour une personne morale "
                                       "(C. consom., art. L. 242-16)."],
         ["**Agent commercial**", "Son appel illicite expose l'agence, et peut l'exposer lui-même à "
                                  "l'amende prévue pour les personnes physiques."],
     ]},
    {"t": "callout",
     "text": "La responsabilité de l'agence est présumée dès lors qu'elle a tiré profit de "
             "sollicitations illicites, y compris lorsqu'elles ont été réalisées par un prestataire. "
             "Tout contrat conclu avec un centre d'appels ou un fournisseur de fichiers doit stipuler "
             "l'obligation de fournir, à première demande, la preuve individuelle du consentement de "
             "chaque personne contactée."},
    {"t": "refs",
     "text": "**Textes de référence.** Code de la consommation, art. L. 221-16, L. 221-18, L. 223-1 "
             "et suivants (rédaction issue de la loi n° 2025-594 du 30 juin 2025), L. 242-16 et "
             "D. 223-8 ; décret n° 2026-662 du 23 juillet 2026 ; Code des postes et des communications "
             "électroniques, art. L. 34-5 ; RGPD, art. 6, 7, 13 et 28. Fiche d'information à jour au "
             "27 septembre 2026 : elle ne remplace pas une consultation juridique."},
]

# ---------------------------------------------------------------------------
# Page 7 — Annexe : formulaire
# ---------------------------------------------------------------------------
page7 = [
    {"t": "annex_head", "tag": "Annexe", "title": "Formulaire de recueil du consentement",
     "note": "À remettre ou à afficher sous cette forme exacte (papier, formulaire en ligne, tablette "
             "en agence). Aucune case n'est pré-cochée. Le refus est sans conséquence sur les "
             "prestations de l'agence. Les champs de l'agence se complètent une fois, avant la "
             "première utilisation. Version du texte : SI-JUR-06 v1."},
    {"t": "form", "parts": [
        {"t": "ident", "lines": [
            "**SUISSE IMMO** — agence de [[agence_ville]]",
            "Société exploitante (raison sociale, forme, RCS) : [[societe]]",
            "Adresse : [[agence_adresse]] · Téléphone : [[agence_tel]]",
            "Courriel : [[agence_courriel]]",
            "Carte professionnelle T n° [[carte_t]] délivrée par la CCI [[cci]]",
        ]},
        {"t": "fp",
         "text": "**Objet du consentement.** La société désignée ci-dessus, qui exploite l'agence Suisse "
                 "Immo, souhaite pouvoir vous contacter au sujet de ses **services de transaction "
                 "immobilière** : estimation de votre bien, proposition de mandat de vente ou de "
                 "location, recherche d'un bien, information sur le marché local."},
        {"t": "fp",
         "text": "**Votre décision.** Vous pouvez accepter ou refuser. Ce choix est libre et n'a aucune "
                 "conséquence sur les prestations que vous pourriez nous confier."},
        {"t": "choices", "items": [
            ["cb_telephone", "**J'accepte** d'être contacté(e) par **téléphone** par cette agence, pour "
                             "l'objet indiqué ci-dessus."],
            ["cb_courriel_sms", "**J'accepte** d'être contacté(e) par **courriel et SMS**."],
            ["cb_refus", "**Je refuse** toute prospection commerciale."],
        ]},
        {"t": "fp",
         "text": "**Durée.** Votre accord vaut pour une durée de **douze (12) mois** à compter de ce jour. "
                 "**Il n'est pas renouvelé tacitement** : passé ce délai, nous ne pourrons plus vous "
                 "appeler sans un nouvel accord de votre part."},
        {"t": "fp",
         "text": "**Retrait.** Vous pouvez retirer votre accord à tout moment, y compris oralement "
                 "pendant un appel, par courriel à [[retrait_courriel]] ou par courrier à l'adresse "
                 "ci-dessus. Le retrait est aussi simple que l'accord et prend effet immédiatement."},
        {"t": "fp",
         "text": "**Preuve.** Nous conservons la preuve de votre accord pendant trois ans et vous la "
                 "communiquons gratuitement, sur support durable, à votre simple demande."},
        {"t": "fp",
         "text": "**Créneaux d'appel.** Nous appelons du lundi au vendredi, hors jours fériés, de 10 h à "
                 "13 h et de 14 h à 20 h, et au maximum quatre fois sur trente jours. {{cb:cb_creneau}} "
                 "Je souhaite être appelé(e) de préférence : [[creneau]] (date et horaire précis)."},
        {"t": "fp",
         "text": "**Données personnelles.** Les données recueillies sont traitées par la société "
                 "désignée ci-dessus, responsable du traitement, à des fins de prospection commerciale, "
                 "sur la base de votre consentement. Elles ne sont ni transmises aux autres agences du "
                 "réseau Suisse Immo, ni cédées à des tiers. Elles sont conservées trois ans à compter "
                 "de notre dernier contact. Vous disposez des droits d'accès, de rectification, "
                 "d'effacement, de limitation, de portabilité, d'opposition et du droit de retirer votre "
                 "consentement, en écrivant à [[droits_courriel]]. Vous pouvez introduire une "
                 "réclamation auprès de la CNIL. {{cb:cb_source}} Source des données, lorsqu'elles n'ont "
                 "pas été collectées auprès de vous : [[source]]."},
        {"t": "fp", "id": "signataire", "gap": True,
         "text": "Nom, prénom : [[nom_prenom]] · Téléphone : [[telephone]]"},
        {"t": "fp",
         "text": "Fait à [[fait_a]], le [[date]] à [[heure|…]] h [[minute|…]]"},
        {"t": "signature", "label": "Signature (acte positif clair) :"},
    ]},
    {"t": "reserved",
     "text": "**Réservé à l'agence :** canal de recueil [[canal]] · collaborateur ou agent commercial "
             "[[collaborateur]] · n° d'enregistrement au registre [[n_registre]] · échéance du "
             "consentement [[echeance]]."},
]

# Champs du formulaire : libellé (info-bulle, contrôle Word) et largeur en mm.
FIELDS = {
    "agence_ville": ("Ville de l'agence", 52),
    "societe": ("Société exploitante : raison sociale, forme, RCS", 82),
    "agence_adresse": ("Adresse de l'agence", 78),
    "agence_tel": ("Téléphone de l'agence", 32),
    "agence_courriel": ("Courriel de l'agence", 64),
    "carte_t": ("Numéro de carte professionnelle T", 46),
    "cci": ("CCI de délivrance de la carte", 44),
    "cb_telephone": ("J'accepte d'être contacté(e) par téléphone", 0),
    "cb_courriel_sms": ("J'accepte d'être contacté(e) par courriel et SMS", 0),
    "cb_refus": ("Je refuse toute prospection commerciale", 0),
    "retrait_courriel": ("Courriel de retrait du consentement", 58),
    "cb_creneau": ("Je souhaite être appelé(e) de préférence à un créneau précis", 0),
    "creneau": ("Créneau de préférence : date et horaire précis", 58),
    "droits_courriel": ("Courriel pour l'exercice des droits", 58),
    "cb_source": ("Données non collectées auprès de la personne", 0),
    "source": ("Source des données", 64),
    "nom_prenom": ("Nom et prénom", 64),
    "telephone": ("Téléphone de la personne", 34),
    "fait_a": ("Lieu de signature", 36),
    "date": ("Date (JJ/MM/AAAA)", 30),
    "heure": ("Heure", 9),
    "minute": ("Minutes", 9),
    "signature": ("Signature de la personne", 0),
    "canal": ("Réservé à l'agence : canal de recueil", 26),
    "collaborateur": ("Réservé à l'agence : collaborateur ou agent commercial", 34),
    "n_registre": ("Réservé à l'agence : numéro d'enregistrement au registre", 24),
    "echeance": ("Réservé à l'agence : échéance du consentement", 24),
}

PAGES = [page1, page2, page3, page4, page5, page6, page7]

CONTENT = walk({
    "ref": REF,
    "version": VERSION,
    "title": DOC_TITLE,
    "footer": [FOOTER_LEFT, FOOTER_CENTER],
    "total": TOTAL_PAGES,
    "pages": PAGES,
})
CONTENT["fields"] = {k: {"label": fr(v[0]), "mm": v[1]} for k, v in FIELDS.items()}

if __name__ == "__main__":
    import sys
    out = sys.argv[1] if len(sys.argv) > 1 else "content.json"
    with open(out, "w", encoding="utf-8") as f:
        json.dump(CONTENT, f, ensure_ascii=False, indent=1)
    print("écrit", out)
