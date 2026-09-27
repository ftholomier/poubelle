"""Construit le site : chaque page de pages/*.html (contenu seul, avec un en-tête de métadonnées) est placée dans
le gabarit commun (en-tête, navigation, pied de page) et écrite dans public/. Aucune dépendance.

Raccourcis utilisables dans les pages :
  {{shot:nom|adresse affichée|texte alternatif}}   capture de l'application dans un cadre de navigateur
  {{phone:nom|texte alternatif}}                   capture mobile dans un cadre de téléphone
  {{anim:nom|adresse affichée|description}}        l'application en action (boucle vidéo muette), cadre de navigateur
  {{animphone:nom|description}}                    idem sur téléphone
  {{img:chemin|texte alternatif|classe}}           image de public/assets/img (WebP, dimensions renseignées)
  {{photo:nom|texte alternatif|lieu}}              photo, avec son crédit (et le nom du lieu, facultatif)
  {{icon:nom}}                                     pictogramme
  {{cta}}                                          bandeau final commun

Usage : python3 outils/construire.py   (puis python3 outils/verifier.py)
"""
import json
import os
import re
from datetime import date

ICI = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PAGES = os.path.join(ICI, 'pages')
PUB = os.path.join(ICI, 'public')
SITE = 'https://terricom.fr'
TAILLES = json.load(open(os.path.join(ICI, 'outils', 'tailles.json')))
CREDITS = json.load(open(os.path.join(ICI, 'outils', 'credits.json')))
ANIMS = json.load(open(os.path.join(ICI, 'outils', 'animations.json')))

NAV = [
    ('elus.html', 'Pour les élus'),
    ('solution.html', 'La solution'),
    ('communes.html', 'Les communes'),
    ('entreprises.html', 'Les entreprises'),
    ('difference.html', 'Notre différence'),
    ('accompagnement.html', 'Accompagnement'),
]

ICONS = {
    'pin': '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
    'users': '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 14.2c3 .2 5.5 2.6 5.5 5.8"/>',
    'megaphone': '<path d="M3 10v4l11 5V5L3 10z"/><path d="M14 8.5a4 4 0 0 1 0 7"/><path d="M6 14.5l1.5 5h3l-1.3-4.2"/>',
    'chart': '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'shield': '<path d="M12 3l8 3v6c0 5-3.5 8.3-8 9-4.5-.7-8-4-8-9V6l8-3z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
    'spark': '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z"/><path d="M19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8L19 16z"/>',
    'store': '<path d="M4 9l1.5-5h13L20 9"/><path d="M4 9h16v2a3 3 0 0 1-5.3 1.9A3 3 0 0 1 12 14a3 3 0 0 1-2.7-1.1A3 3 0 0 1 4 11V9z"/><path d="M5 13.5V20h14v-6.5M10 20v-4h4v4"/>',
    'mail': '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5l8.5 6 8.5-6"/>',
    'calendar': '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    'route': '<circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 18H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.5"/>',
    'briefcase': '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/>',
    'landmark': '<path d="M3 10l9-6 9 6"/><path d="M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 21h18"/>',
    'heart': '<path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7a4.3 4.3 0 0 1 7.5 2.8C19.5 15.4 12 20 12 20z"/>',
    'eye': '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    'hand': '<path d="M8 12V5.5a1.5 1.5 0 0 1 3 0V11M11 10V4.5a1.5 1.5 0 0 1 3 0V11M14 10.5V6a1.5 1.5 0 0 1 3 0v8c0 4-2.7 7-6.5 7-2.6 0-4.1-1.2-5.5-3.2L3.3 15a1.5 1.5 0 0 1 2.4-1.8L8 15.5"/>',
    'leaf': '<path d="M5 19c0-8 5-14 15-14 0 10-6 15-14 15"/><path d="M5 19l7-7"/>',
    'check': '<path d="M4 12.5l5 5L20 6.5"/>',
    'clock': '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'euro': '<path d="M17 6.5A7 7 0 0 0 7 12a7 7 0 0 0 10 5.5M4 10.5h9M4 13.5h9"/>',
    'lock': '<rect x="4.5" y="10" width="15" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    'globe': '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
    'phone': '<rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
    'doc': '<path d="M6 3h8l5 5v13H6z"/><path d="M14 3v5h5M9 13h7M9 17h5"/>',
    'compass': '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5 5-2z"/>',
    'map': '<path d="M9 4L3 6v14l6-2 6 2 6-2V4l-6 2-6-2z"/><path d="M9 4v14M15 6v14"/>',
    'search': '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.3-4.3"/>',
    'qr': '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 17h3v3"/>',
    'refresh': '<path d="M20 11a8 8 0 0 0-14.3-4.9L4 8M4 4v4h4M4 13a8 8 0 0 0 14.3 4.9L20 16M20 20v-4h-4"/>',
    'flag': '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
    'school': '<path d="M2 9l10-5 10 5-10 5L2 9z"/><path d="M6 11v5c3 2.5 9 2.5 12 0v-5"/>',
    'star': '<path d="M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8L3.5 9.7l5.9-.8L12 3.5z"/>',
}

# Symbole terricom : carré aux trois coins arrondis (50 %) et un coin à 14 %, pivoté à -45° : un repère de carte.
MARK_PATH = 'M20.00 5 H20.00 A13.0 13.0 0 0 1 33 18.00 V18.00 A13.0 13.0 0 0 1 20.00 31 H10.64 A3.64 3.64 0 0 1 7 27.36 V18.00 A13.0 13.0 0 0 1 20.00 5 Z'
LOGO = (
    '<svg viewBox="0 0 40 40" aria-hidden="true"><path transform="rotate(-45 20 18)" d="' + MARK_PATH + '" fill="#1F6B52"/>'
    '<text x="20" y="23.6" text-anchor="middle" font-family="Bricolage, sans-serif" font-weight="800" font-size="17" fill="#F4B266">t</text></svg>'
)


def icon(name):
    return (
        f'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" '
        f'stroke-linejoin="round" aria-hidden="true">{ICONS[name]}</svg>'
    )


def dims(key):
    w, h = TAILLES.get(key, [1600, 1000])
    return w, h


def picture(src, key, alt, cls='', lazy=True, sizes='(max-width: 900px) 100vw, 800px'):
    w, h = dims(key)
    small = src.replace('.webp', '-800.webp')
    srcset = f' srcset="{small} 800w, {src} {w}w" sizes="{sizes}"' if os.path.exists(os.path.join(PUB, small)) else ''
    load = ' loading="lazy" decoding="async"' if lazy else ' fetchpriority="high"'
    c = f' class="{cls}"' if cls else ''
    return f'<img src="{src}"{srcset} width="{w}" height="{h}" alt="{alt}"{c}{load}>'


def m_shot(m):
    name, url, alt = (m.group(1).split('|') + ['', ''])[:3]
    return f'<figure class="shot reveal"><div class="bar"><i></i><i></i><i></i><span>{url}</span></div>{picture(f"assets/img/app/{name}.webp", name, alt)}</figure>'


def m_phone(m):
    name, alt = (m.group(1).split('|') + [''])[:2]
    return f'<figure class="phone reveal">{picture(f"assets/img/app/{name}.webp", name, alt, sizes="300px")}</figure>'


PAUSE = (
    '<button class="anim-toggle" type="button" aria-label="Mettre l’animation en pause">'
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path class="i-pause" d="M8 5h3v14H8zM13 5h3v14h-3z"/>'
    '<path class="i-play" d="M8 5l11 7-11 7z"/></svg></button>'
)


def video(name, alt):
    a = ANIMS[name]
    return (
        f'<video class="loop" muted loop playsinline preload="none" width="{a["w"]}" height="{a["h"]}" '
        f'poster="assets/img/app/anim-{name}.webp" data-src="assets/video/app/{name}.mp4" '
        f'data-webm="assets/video/app/{name}.webm" aria-label="{alt}"></video>{PAUSE}'
    )


def m_anim(m):
    name, url, alt = (m.group(1).split('|') + ['', ''])[:3]
    return f'<figure class="shot anim reveal"><div class="bar"><i></i><i></i><i></i><span>{url}</span></div>{video(name, alt)}</figure>'


def m_animphone(m):
    name, alt = (m.group(1).split('|') + [''])[:2]
    return f'<figure class="phone anim reveal">{video(name, alt)}</figure>'


def m_img(m):
    path, alt, cls = (m.group(1).split('|') + ['', ''])[:3]
    key = os.path.splitext(os.path.basename(path))[0]
    return picture(f'assets/img/{path}', key, alt, cls, lazy=('eager' not in cls))


def credit_text(name):
    c = CREDITS.get(name)
    if not c:
        return ''
    return f'Photo : {c["artist"]}, {c["license"]}, via Wikimedia Commons'


def m_photo(m):
    name, alt, lieu = (m.group(1).split('|') + ['', ''])[:3]
    c = CREDITS.get(name, {})
    cred = f'<figcaption class="credit"><a href="{c.get("page", "credits.html")}">{credit_text(name)}</a></figcaption>' if c else ''
    lieu = f'<span class="lieu">{lieu}</span>' if lieu else ''
    return f'<figure class="photo" style="position:relative;margin:0">{picture(f"assets/img/photos/{name}.webp", name, alt, "", True, "(max-width: 900px) 100vw, 1200px")}{lieu}{cred}</figure>'


CTA = """<section class="section dark cta-final">
  <div class="wrap">
    <span class="eyebrow">Et si c’était votre territoire ?</span>
    <p class="quote reveal">Nous préparons la démonstration <em>sur vos communes et vos entreprises</em>, avant même notre premier rendez-vous.</p>
    <div class="actions">
      <a class="btn btn-amber" href="demonstration.html#demande">Demander ma démonstration <span class="arr">→</span></a>
      <a class="btn btn-line" href="contact.html">Nous écrire</a>
    </div>
  </div>
</section>"""


def insecables(html):
    # Espaces insécables : milliers (4 900), avant €, %, :, ;, ?, ! et dans « guillemets », hors balises.
    def fix(t):
        t = re.sub(r'(\d) (\d{3})(?!\d)', '\\1\u202f\\2', t)
        t = re.sub(r'(\d) (\d{3})(?!\d)', '\\1\u202f\\2', t)
        t = re.sub(r' (€|%)', '\u00a0\\1', t)
        t = re.sub(r' ([;:?!»])', '\u202f\\1', t)
        t = re.sub(r'« ', '«\u202f', t)
        t = re.sub(r'(\d) (h|habitants|communes|entreprises|commune|mois|ans|jours|minutes|semaines)\b', '\\1\u00a0\\2', t)
        return t
    return re.sub(r'>([^<]+)<', lambda m: '>' + fix(m.group(1)) + '<', html)


def expand(html):
    html = re.sub(r'\{\{shot:([^}]*)\}\}', m_shot, html)
    html = re.sub(r'\{\{phone:([^}]*)\}\}', m_phone, html)
    html = re.sub(r'\{\{anim:([^}]*)\}\}', m_anim, html)
    html = re.sub(r'\{\{animphone:([^}]*)\}\}', m_animphone, html)
    html = re.sub(r'\{\{img:([^}]*)\}\}', m_img, html)
    html = re.sub(r'\{\{photo:([^}]*)\}\}', m_photo, html)
    html = re.sub(r'\{\{icon:([a-z]+)\}\}', lambda m: icon(m.group(1)), html)
    html = re.sub(r'\{\{credit:([a-z-]+)\}\}', lambda m: f'<a href="{CREDITS.get(m.group(1), {}).get("page", "credits.html")}">{credit_text(m.group(1))}</a>', html)
    html = html.replace('{{cta}}', CTA)
    if '{{credits}}' in html:
        items = ''.join(
            f'<li>{c["title"].rsplit(".", 1)[0]} : {c["artist"]}, {c["license"]} (<a href="{c["page"]}">source</a>)</li>'
            for k, c in CREDITS.items()
            if k in TAILLES
        )
        html = html.replace('{{credits}}', f'<ul>{items}</ul>')
    return html


def layout(page, meta, body):
    titre = meta['titre']
    full = titre if page == 'index.html' else f'{titre} · terricom'
    desc = meta['description']
    url = f'{SITE}/' if page == 'index.html' else f'{SITE}/{page.replace(".html", "")}'
    cur = ' aria-current="page"'
    nav = '\n'.join(f'<a href="{href}"{cur if href == page else ""}>{label}</a>' for href, label in NAV)
    scripts = '<script src="assets/js/site.js" defer></script>'
    if meta.get('scripts'):
        for s in meta['scripts'].split(','):
            scripts += f'\n<script type="module" src="assets/js/{s.strip()}"></script>'
    ld = {
        '@context': 'https://schema.org',
        '@type': 'Organization',
        'name': 'terricom',
        'url': SITE,
        'logo': f'{SITE}/favicon.svg',
        'email': 'bonjour@terricom.fr',
        'slogan': 'Le territoire, en vitrine.',
        'areaServed': 'FR',
    }
    return f"""<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{full}</title>
<meta name="description" content="{desc}">
<link rel="canonical" href="{url}">
<meta name="theme-color" content="#14201B">
<meta property="og:type" content="website">
<meta property="og:locale" content="fr_FR">
<meta property="og:site_name" content="terricom">
<meta property="og:title" content="{titre}">
<meta property="og:description" content="{desc}">
<meta property="og:url" content="{url}">
<meta property="og:image" content="{SITE}/assets/img/partage.jpg">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="assets/img/icone-180.png">
<link rel="manifest" href="site.webmanifest">
<link rel="preload" href="assets/fonts/bricolage-grotesque-latin-opsz-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="assets/fonts/instrument-sans-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="assets/css/site.css">
<script type="application/ld+json">{json.dumps(ld, ensure_ascii=False)}</script>
</head>
<body class="page-{page.replace('.html', '')}">
<a class="skip" href="#contenu">Aller au contenu</a>
<header class="site-header">
  <div class="wrap">
    <a class="logo" href="index.html" aria-label="terricom, accueil">{LOGO}<span>terricom<i>.</i></span></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="navigation">Menu</button>
    <nav class="nav" id="navigation" aria-label="Navigation principale">
{nav}
      <a class="btn btn-amber" href="demonstration.html">Voir la démonstration</a>
    </nav>
  </div>
</header>
<main id="contenu">
{body}
</main>
<footer class="site-footer">
  <div class="wrap">
    <div class="top">
      <div>
        <a class="logo" href="index.html" aria-label="terricom, accueil">{LOGO}<span>terricom<i>.</i></span></a>
        <p class="baseline">Le territoire, <em>en vitrine.</em></p>
        <p>La plateforme des communautés de communes et des communes de France qui mettent en lumière leurs commerces, leurs artisans et leurs producteurs. Conçue, développée et hébergée en France.</p>
      </div>
      <div>
        <h2>Découvrir</h2>
        <ul>
          <li><a href="elus.html">Pour les élus</a></li>
          <li><a href="solution.html">La solution</a></li>
          <li><a href="communes.html">Les communes</a></li>
          <li><a href="entreprises.html">Les entreprises</a></li>
          <li><a href="difference.html">Notre différence</a></li>
        </ul>
      </div>
      <div>
        <h2>Avancer</h2>
        <ul>
          <li><a href="demonstration.html">La démonstration</a></li>
          <li><a href="accompagnement.html">L’accompagnement</a></li>
          <li><a href="confiance.html">Confiance et données</a></li>
          <li><a href="questions.html">Questions fréquentes</a></li>
        </ul>
      </div>
      <div>
        <h2>Nous écrire</h2>
        <ul>
          <li><a href="contact.html">Contact</a></li>
          <li><a href="mailto:bonjour@terricom.fr">bonjour@terricom.fr</a></li>
          <li><a href="mentions-legales.html">Mentions légales</a></li>
          <li><a href="confidentialite.html">Confidentialité</a></li>
          <li><a href="credits.html">Crédits</a></li>
        </ul>
      </div>
    </div>
    <div class="bottom">
      <span>© {date.today().year} terricom · Hébergé en France · Sans publicité, sans revente de données</span>
      <span>Accessibilité : <a href="confiance.html#accessibilite">partiellement conforme au RGAA</a></span>
    </div>
  </div>
</footer>
{scripts}
</body>
</html>
"""


def main():
    written = []
    for f in sorted(os.listdir(PAGES)):
        if not f.endswith('.html'):
            continue
        src = open(os.path.join(PAGES, f), encoding='utf-8').read()
        m = re.match(r'\s*<!--(.*?)-->\s*', src, re.S)
        meta = {}
        for line in m.group(1).strip().splitlines():
            k, v = line.split(':', 1)
            meta[k.strip()] = v.strip()
        body = insecables(expand(src[m.end():]))
        open(os.path.join(PUB, f), 'w', encoding='utf-8').write(layout(f, meta, body))
        written.append(f)
    # plan du site
    urls = ''.join(
        f'<url><loc>{SITE}/{"" if f == "index.html" else f.replace(".html", "")}</loc><lastmod>{date.today()}</lastmod></url>'
        for f in written
        if f not in ('404.html',)
    )
    open(os.path.join(PUB, 'sitemap.xml'), 'w').write(
        f'<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">{urls}</urlset>\n'
    )
    print(len(written), 'pages construites')


if __name__ == '__main__':
    main()
