# Génère offre.html (infographie A4 « L'offre irrésistible », charte Synapse).
# Usage : python3 generer.py <dossier de sortie> <chemin du projet visite-immo>
# Puis le rendu PDF se fait avec Chromium (voir render.mjs).
import sys

out, P = sys.argv[1], sys.argv[2]
logo = open(P + '/public/img/synapse-logo.svg').read()
logo = logo[logo.index('<svg'):]
F = P + '/public/fonts/'
PILL = ''

taches = [("Fiche du bien & caractéristiques", 45, 2), ("Rédaction de l'annonce", 30, 2), ("Mandat & pièces du vendeur", 40, 5),
          ("Compte rendu au vendeur", 20, 1), ("Diffusion sur les portails", 15, 0)]


def row(t, a, b):
    return f'''<div class="t-row"><div class="t-nom">{t}</div><div class="t-bars">
      <div class="bar-l"><span class="bar avant" style="width:{a / 45 * 100:.1f}%"></span><b>{a} min</b></div>
      <div class="bar-l"><span class="bar apres" style="width:{max(b / 45 * 100, 0.8):.1f}%"></span><b>{b} min</b></div></div></div>'''


col1 = "".join(row(*t) for t in taches[:3])
col2 = "".join(row(*t) for t in taches[3:])

etapes = [
    ("01", "Prospection", "Trouver les vendeurs avant les autres", None, [
        ("Ciblage des futurs vendeurs : logements F/G (ADEME) et biens détenus depuis longtemps (DVF)", True),
        ("Courrier personnalisé généré et prêt à envoyer, plutôt que le démarchage téléphonique", True),
        ("Rapprochement automatique acquéreurs ↔ nouveaux mandats", True),
        ("Estimation en temps réel avec les ventes similaires du quartier (DVF)", True)]),
    ("02", "Visite & mandat", "Le dossier se remplit pendant que vous parlez", "≈ 1 h 30 / mandat", [
        ("Visite enregistrée : l'IA remplit la fiche du bien", True),
        ("Dossier complété à la voix en sortant de la visite", True),
        ("Mandat conforme, registre, rétractation et bordereau générés", True),
        ("Dossier technique depuis l'adresse : cadastre, état des risques (Géorisques), DPE ADEME", True),
        ("L'IA lit les papiers du vendeur (acte, taxe foncière, PV d'AG) et relance les pièces manquantes", True),
        ("Signature électronique sur place · plan 2D mesuré au téléphone", True)]),
    ("03", "Commercialisation", "L'annonce part sans vous", "≈ 45 min / mandat", [
        ("Annonce rédigée avec mentions légales, aperçu réel Leboncoin", True),
        ("Diffusion automatique sur les grands portails (multidiffuseur)", False),
        ("Photos triées, retouchées, home staging virtuel", True),
        ("Publications réseaux sociaux et courte vidéo générées pour chaque mandat", True)]),
    ("04", "Acquéreurs & visites", "Vous ne visitez qu'avec des acquéreurs qualifiés", "≈ 4 à 7 h / semaine", [
        ("Assistant acquéreurs 24 h/24 : répond, qualifie (budget, financement, délai) et cale les visites dans votre agenda", True),
        ("Bon de visite signé sur le téléphone, retour dicté en 30 secondes", True),
        ("Compte rendu de commercialisation envoyé au vendeur chaque vendredi, automatiquement", True),
        ("Compte rendu de visite vendeur et PDF en 1 clic", True)]),
    ("05", "Du compromis à l'acte", "Le dossier avance seul", "≈ 2 à 3 h / vente", [
        ("Dossier pour le notaire assemblé automatiquement", True),
        ("Échéances suivies : rétractation SRU, prêt, conditions suspensives", True),
        ("Relances automatiques de l'acquéreur, du courtier et du notaire", True),
        ("Contrôle anti-blanchiment (LCB-FT) : pièce d'identité, listes de gel, fiche de vigilance", True)]),
    ("06", "Au quotidien", "Votre assistant, partout", None, [
        ("Briefing du matin à écouter : rendez-vous, relances, nouveaux contacts, échéances", True),
        ("Bilan d'appel dicté : la tâche et la relance se créent seules", True),
        ("Facture d'honoraires et note de commission générées à l'acte", True),
        ("Demande d'avis Google envoyée au client satisfait", True)]),
]


def etape(num, nom, sous, gain, items):
    lis = "".join(f'<li class="{"ok" if ok else ""}">{t}{PILL if ok else ""}</li>' for t, ok in items)
    g = f'<span class="e-gain">{gain}</span>' if gain else ''
    return f'''<div class="etape"><div class="e-top"><span class="e-num">{num}</span><span class="e-nom">{nom}</span>{g}</div><div class="e-sous">{sous}</div><ul>{lis}</ul></div>'''


parcours = "".join(etape(*e) for e in etapes)

css = open(__file__.replace('generer.py', 'style.css')).read().replace('{F}', F)

html = f'''<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Synapse · L'offre irrésistible</title><style>{css}</style></head><body>
<div class="top">{logo}<div class="meta mono"><b>Offre agents commerciaux</b><br>Réseau Synapse · 2026<br>Document de travail</div></div>

<div>
  <span class="tag">L'offre irrésistible</span>
  <h1>Vous allez sur le terrain. <mark>On fait tout le reste.</mark></h1>
  <p class="chapo">Visiter, rentrer des mandats, vendre : c'est votre métier. Fiches, annonces, mandats, relances, notaire : le logiciel Synapse s'en charge. <b>Un mandat rentré, c'est 10 minutes de saisie au lieu de 2 h 30</b>, et jusqu'à 95 % des honoraires pour vous.</p>
</div>

<div class="cards">
  <div class="card noir"><div class="lbl">Votre part des honoraires</div><div class="big">80 → 95 %</div><p>80 % dès le premier euro, jusqu'à 95 % quand vous performez. Sans plafond, sans frais cachés.</p></div>
  <div class="card"><div class="lbl orange-t">Abonnement tout compris</div><div class="big">79 €<small> HT/mois</small></div><p>3 mois offerts, 0 € de droit d'entrée, sans engagement.</p></div>
  <div class="card citron"><div class="lbl">Temps rendu</div><div class="big">≈ 1 jour<small> / semaine</small></div><p>Saisie, annonces, relances et suivi automatisés : du temps rendu au terrain.</p></div>
</div>

<div class="secteur">
  <div class="s-g">
    <div class="lbl">Votre secteur</div>
    <div class="s-titre">Développez-le comme une franchise.<br><mark>Sans les contraintes de la franchise.</mark></div>
    <p>Le réseau vous attribue un secteur. Vous le développez comme votre agence, avec la marque, la prospection ciblée sur vos rues et le back-office Synapse. Vous gardez votre liberté, et vos honoraires.</p>
  </div>
  <table class="s-t">
    <tr><th></th><th>Franchise classique</th><th class="syn">Synapse</th></tr>
    <tr><td>Droit d'entrée</td><td>Souvent des milliers d'euros</td><td class="syn">0 €</td></tr>
    <tr><td>Redevance</td><td>Un % de votre chiffre d'affaires</td><td class="syn">Aucune : 79 € HT / mois fixe</td></tr>
    <tr><td>Local, travaux, salariés</td><td>À votre charge</td><td class="syn">Aucun : travaillez d'où vous voulez</td></tr>
    <tr><td>Engagement</td><td>Contrat de plusieurs années</td><td class="syn">Sans engagement</td></tr>
    <tr><td>Votre secteur</td><td>Contre investissement</td><td class="syn">Attribué, à développer</td></tr>
  </table>
</div>

<div class="grille">
  <section>
    <h2>Votre rémunération</h2>
    <div class="remu">
      <div><div class="paliers">
        <div class="palier"><div class="col" style="height:84%">80 %</div><div class="sous">Dès 1 €</div></div>
        <div class="palier"><div class="col" style="height:95%">90 %</div><div class="sous">40 000 €</div></div>
        <div class="palier"><div class="col" style="height:100%">95 %</div><div class="sous">80 000 €</div></div>
      </div></div>
      <p class="note">Part des honoraires HT encaissés, selon votre chiffre d'affaires sur 12 mois glissants. Le palier atteint s'applique à toutes vos ventes suivantes. <b>Exemple : 60 000 € d'honoraires = 50 000 € pour vous.</b></p>
    </div>
  </section>
  <section>
    <h2>L'abonnement · 79 € HT / mois</h2>
    <ul class="abo">
      <li>Le logiciel Synapse complet, toutes les fonctions ci-dessous</li>
      <li>Multidiffusion illimitée, signature électronique, registre des mandats</li>
      <li>Formation obligatoire loi ALUR incluse (14 h / an)</li>
      <li>Assistance juridique et hotline 6 j / 7</li>
      <li>Pause gratuite en cas de congé, maladie ou parentalité</li>
    </ul>
  </section>
</div>

<section>
  <h2>Le temps rendu sur un mandat <span>estimations, à mesurer en test</span></h2>
  <div class="legende" style="margin-bottom:1mm"><span><i style="background:var(--orange)"></i>Aujourd'hui, à la main</span><span><i style="background:var(--vert)"></i>Avec Synapse</span></div>
  <div class="grille" style="gap:0 6mm"><div>{col1}</div><div>{col2}
    <div class="total"><span class="lbl">Total par mandat</span><span><span class="big" style="color:var(--orange)">150 min</span> <span class="mono" style="font-size:6.6pt">→</span> <span class="big vert-t">10 min</span></span></div></div></div>
</section>

<section>
  <h2>Le logiciel, de la prospection à l'acte <span>gains estimés</span></h2>
  <div class="parcours">{parcours}</div>
  <div class="legende2"><span><i style="background:var(--citron)"></i>Déjà en test dans le prototype</span><span><i style="background:var(--papier)"></i>À venir</span><span>Données publiques gratuites : cadastre IGN, Géorisques, ADEME, DVF</span></div>
</section>

<section>
  <h2>L'accompagnement, en plus du logiciel</h2>
  <div class="accomp">
    <div class="acc"><div class="lbl">Démarrage</div><div class="tt">Un coach dédié 90 jours</div><div class="td">Objectif : votre premier mandat en 30 jours, avec un plan de prospection sur votre secteur.</div></div>
    <div class="acc"><div class="lbl">Jusqu'à l'acte</div><div class="tt">Un back-office qui relance</div><div class="td">Notaire, syndic, diagnostiqueur, pièces du dossier : on suit, vous êtes prévenu.</div></div>
    <div class="acc"><div class="lbl">Sécurité</div><div class="tt">Un juriste au bout du fil</div><div class="td">Mandats conformes par construction, LCB-FT et démarchage encadrés.</div></div>
    <div class="acc"><div class="lbl">Réseau</div><div class="tt">La communauté Synapse</div><div class="td">Contacts entrants partagés par le réseau social Synapse, entraide entre agents.</div></div>
  </div>
</section>

<div>
  <div class="ticker">80 à 95 % <b>★</b> votre secteur <b>★</b> 79 € HT / mois <b>★</b> 3 mois offerts <b>★</b> 0 € d'entrée <b>★</b> 0 redevance <b>★</b> sans engagement</div>
  <div class="fin"><span>Proposition de travail, à valider : paliers, tarifs, contenus et gains de temps sont indicatifs, à mesurer en conditions réelles.</span><span>Statut agent commercial indépendant (RSAC)</span></div>
</div>
</body></html>'''
open(out + '/offre.html', 'w').write(html)
print("offre.html écrit")
