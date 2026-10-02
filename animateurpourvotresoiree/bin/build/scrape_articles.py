#!/usr/bin/env python3
"""
Récupère les articles « Actualités » de l'ancien site (pages .php statiques) et produit
app/data/seed/articles.json + les images dans public/media/blog/.

Usage : python3 bin/build/scrape_articles.py <dossier_html_aspiré> <liste_urls.txt> <base_url> <racine_projet>
Les pages HTML doivent avoir été téléchargées au préalable (curl), nommées <fichier.php>.html
"""
import html
import json
import os
import re
import sys
import unicodedata
import urllib.request
from datetime import date


def slugify(s):
    s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode().lower()
    s = s.replace("'", '-')
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')


def decode(raw):
    try:
        return raw.decode('utf-8')
    except UnicodeDecodeError:
        return raw.decode('cp1252', 'replace')


def main():
    src, urls_file, base, root = sys.argv[1:5]
    out_img = os.path.join(root, 'public/media/blog')
    os.makedirs(out_img, exist_ok=True)
    urls = [u.strip() for u in open(urls_file) if u.strip()]
    articles = []
    for idx, u in enumerate(urls):
        path = os.path.join(src, u + '.html')
        if not os.path.exists(path):
            continue
        raw = open(path, 'rb').read()
        start = raw.find(b'single-project-content')
        if start < 0:
            continue
        start = raw.find(b'>', start) + 1
        end = raw.find(b'<SCRIPT LANGUAGE="JavaScript">', start)
        if end < 0:
            end = raw.find(b'<!-- colonne droite', start)
        body = decode(raw[start:end])
        head = raw[:start].decode('latin-1')
        m = re.search(r'titre-inscription">(.*?)</h3>', head, re.S)
        title = html.unescape(m.group(1)).strip() if m else ''
        if not title:
            t = re.search(r'<title>(.*?)</title>', head, re.S | re.I)
            title = html.unescape(t.group(1)).strip() if t else ''
        md = re.search(r'<meta\s+name="description"\s+content="([^"]*)"', head, re.I)
        meta_description = html.unescape(md.group(1)).strip() if md else ''
        h1 = re.search(r'<h1[^>]*>(.*?)</h1>', body, re.S | re.I)
        if h1:
            t2 = re.sub(r'<[^>]+>', '', html.unescape(h1.group(1))).strip()
            if t2:
                title = t2
            body = body[:h1.start()] + body[h1.end():]
        # image principale
        image = ''
        im = re.search(r"<img[^>]+src=['\"]([^'\"]+)['\"][^>]*>", body, re.I)
        if im:
            src_img = im.group(1)
            body = body[:im.start()] + body[im.end():]
            img_url = src_img if src_img.startswith('http') else base.rstrip('/') + '/' + src_img.lstrip('./')
            ext = os.path.splitext(src_img.split('?')[0])[1].lower() or '.jpg'
            name = slugify(os.path.splitext(u)[0])[:60] + ext
            try:
                req = urllib.request.Request(img_url, headers={'User-Agent': 'Mozilla/5.0 APVS migration'})
                data = urllib.request.urlopen(req, timeout=30).read()
                if len(data) > 1000:
                    open(os.path.join(out_img, name), 'wb').write(data)
                    image = '/media/blog/' + name
            except Exception as e:  # noqa
                print('image KO', img_url, e)
        # autres images dans le corps : on les rapatrie aussi
        def repl(mo):
            s = mo.group(1)
            full = s if s.startswith('http') else base.rstrip('/') + '/' + s.lstrip('./')
            if 'animateurpourvotresoiree.com' not in full:
                return mo.group(0)
            nm = slugify(os.path.splitext(os.path.basename(s))[0])[:60] + (os.path.splitext(s)[1].lower() or '.jpg')
            try:
                req = urllib.request.Request(full, headers={'User-Agent': 'Mozilla/5.0 APVS migration'})
                data = urllib.request.urlopen(req, timeout=30).read()
                open(os.path.join(out_img, nm), 'wb').write(data)
                return mo.group(0).replace(s, '/media/blog/' + nm)
            except Exception:
                return ''
        body = re.sub(r"<img[^>]+src=['\"]([^'\"]+)['\"][^>]*>", repl, body, flags=re.I)
        body = re.sub(r'(\s*<br\s*/?>\s*){3,}', '<br><br>', body.strip())
        text = re.sub(r'<[^>]+>', ' ', body)
        text = re.sub(r'\s+', ' ', html.unescape(text)).strip()
        links = re.findall(r"href=['\"](https?://[^'\"]+)['\"]", body)
        external = [l for l in links if 'animateurpourvotresoiree.com' not in l]
        slug = slugify(os.path.splitext(u)[0].replace('_', '-'))
        articles.append({
            'legacy_url': '/' + u,
            'slug': slug,
            'title': title,
            'excerpt': text[:220].rsplit(' ', 1)[0] + '…' if len(text) > 220 else text,
            'body': body,
            'image': image,
            'meta_description': meta_description,
            'external_links': external,
            'sponsored': bool(external),
            'order': idx,
        })
        print(f'{slug}: {title[:70]} ({len(text)} car., {len(external)} liens externes, image={bool(image)})')
    dest = os.path.join(root, 'app/data/seed/articles.json')
    os.makedirs(os.path.dirname(dest), exist_ok=True)
    json.dump({'scraped_at': date.today().isoformat(), 'source': base, 'articles': articles}, open(dest, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print(len(articles), 'articles ->', dest)


if __name__ == '__main__':
    main()
