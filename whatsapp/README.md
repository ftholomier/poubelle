# Starter WhatsApp Cloud API — PHP natif 🇲🇦

Point de départ minimal et **sans framework** (PHP natif + cURL, zéro Composer) pour
s'interfacer avec la **WhatsApp Cloud API** de Meta : envoi de messages, réception via
webhook, messages interactifs. Pensé pour développer des solutions conversationnelles,
notamment pour le marché **marocain** (WhatsApp y est ultra-dominant).

> ℹ️ La Cloud API est la **seule** voie officielle depuis le **23 octobre 2025**
> (l'ancienne On-Premise API est arrêtée). Accès gratuit, tu ne payes que certains
> messages sortants (voir [Tarification](#tarification-2026)).

---

## Sommaire

- [Ce que fait ce starter](#ce-que-fait-ce-starter)
- [Prérequis](#prérequis)
- [Structure](#structure)
- [Étape 1 — Obtenir tes identifiants Meta](#étape-1--obtenir-tes-identifiants-meta)
- [Étape 2 — Configurer le projet](#étape-2--configurer-le-projet)
- [Étape 3 — Envoyer un premier message](#étape-3--envoyer-un-premier-message)
- [Étape 4 — Recevoir des messages (webhook)](#étape-4--recevoir-des-messages-webhook)
- [La fenêtre 24 h et les templates](#la-fenêtre-24-h-et-les-templates)
- [Sécurité](#sécurité)
- [Tarification 2026](#tarification-2026)
- [Spécificités Maroc](#spécificités-maroc)
- [Passer en production](#passer-en-production)

---

## Ce que fait ce starter

- ✅ Envoi : **texte**, **template**, **boutons**, **liste**, **image**
- ✅ Réception via **webhook** (messages + statuts *sent/delivered/read/failed*)
- ✅ Vérification du **challenge** (GET) et de la **signature** `X-Hub-Signature-256` (POST)
- ✅ Exemple d'**auto-réponse** (menu à boutons + écho)
- ✅ **CLI** de test, **logs** fichier, config par **JSON** ou variables d'environnement

---

## Prérequis

- **PHP 8.1+** avec les extensions `curl`, `json`, `mbstring` (vérifie : `php -m`)
- Un compte **Meta Business** + une **app Meta** avec le produit **WhatsApp**
- **[ngrok](https://ngrok.com/)** (ou équivalent) pour exposer le webhook en local

---

## Structure

```
whatsapp/
├── bootstrap.php            # autoloader natif (pas de Composer)
├── config.example.json      # modèle de config → copier vers config.json
├── bin/
│   └── send.php             # CLI d'envoi (text | template | buttons | list)
├── public/
│   └── webhook.php          # endpoint webhook (GET vérif + POST réception)
├── src/
│   ├── Config.php           # chargement config JSON + surcharge par env
│   ├── Logger.php           # log fichier simple
│   ├── WhatsAppClient.php    # client Cloud API (cURL)
│   └── WebhookHandler.php   # challenge, signature, parsing
└── logs/                    # logs runtime (git-ignorés)
```

---

## Étape 1 — Obtenir tes identifiants Meta

1. Va sur **[developers.facebook.com](https://developers.facebook.com/)** → *Mes apps* → **Créer une app** → type **Entreprise**.
2. Ajoute le produit **WhatsApp** à l'app.
3. Dans **WhatsApp → Configuration de l'API**, tu obtiens tout de suite :
   - un **numéro de test** offert (envoi gratuit vers un maximum de 5 numéros de test que tu déclares) ;
   - le **Phone Number ID** (l'identifiant du numéro expéditeur, **≠ le numéro**) ;
   - un **token d'accès temporaire (24 h)** pour démarrer.
4. **App Secret** : *Paramètres de l'app → Général → Clé secrète*.
5. **Verify token** : une chaîne secrète **que tu choisis toi-même** (ex. `openssl rand -hex 16`).
6. *(Plus tard, pour la prod)* un **token permanent** via un **Utilisateur système** (Business Settings)
   avec les permissions `whatsapp_business_messaging` + `whatsapp_business_management`.

---

## Étape 2 — Configurer le projet

```bash
cd whatsapp
cp config.example.json config.json
```

Édite `config.json` :

```json
{
    "graph_api_version": "v22.0",
    "phone_number_id": "123456789012345",
    "access_token": "EAAG...ton_token",
    "verify_token": "mon-token-secret-choisi",
    "app_secret": "abcdef0123456789...",
    "business_name": "Mon Business",
    "default_recipient": "2126XXXXXXXX"
}
```

> 🔐 `config.json` est **git-ignoré**. En prod, préfère les variables d'environnement :
> `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_VERIFY_TOKEN`,
> `WHATSAPP_APP_SECRET` (elles écrasent le fichier).

---

## Étape 3 — Envoyer un premier message

Avec le numéro de test, envoie d'abord vers **un de tes numéros déclarés** :

```bash
php bin/send.php text 2126XXXXXXXX "Bonjour depuis PHP natif 🚀"
php bin/send.php buttons 2126XXXXXXXX
php bin/send.php list 2126XXXXXXXX
# Template (obligatoire pour initier hors fenêtre 24 h) :
php bin/send.php template 2126XXXXXXXX hello_world en_US
```

> ⚠️ Un message **texte** ne part que si le destinataire t'a écrit dans les **dernières 24 h**.
> Pour **initier** une conversation, il faut un **template** approuvé (voir plus bas).

---

## Étape 4 — Recevoir des messages (webhook)

1. Lance le serveur PHP intégré (depuis la racine du repo) :

   ```bash
   php -S localhost:8080 -t whatsapp/public
   ```

2. Expose-le en HTTPS :

   ```bash
   ngrok http 8080
   ```

3. Dans l'app Meta → **WhatsApp → Configuration → Webhooks** :
   - **URL de rappel** : `https://<ton-sous-domaine>.ngrok-free.app/webhook.php`
   - **Token de vérification** : la valeur de `verify_token` de ton `config.json`
   - Clique **Vérifier et enregistrer** (Meta appelle ton endpoint en GET → il renvoie le challenge).
   - **Abonne-toi au champ `messages`.**

4. Écris à ton numéro WhatsApp Business depuis ton téléphone → tu verras l'auto-réponse,
   et les événements s'empilent dans `logs/webhook.log`.

Personnalise la logique dans la fonction `autoReply()` de `public/webhook.php`.

---

## La fenêtre 24 h et les templates

- **Fenêtre de service (24 h)** : dès qu'un utilisateur t'écrit, tu peux lui répondre
  **librement en texte** pendant 24 h. Ces messages « service » sont **gratuits**.
- **Hors fenêtre** : pour **relancer / initier**, tu **dois** envoyer un **template**
  pré-approuvé par Meta (catégories *Utility*, *Marketing* ou *Authentication*).
- Les templates se créent dans le **WhatsApp Manager** (texte + variables `{{1}}`, boutons…).
  Prévois des versions **FR** et **AR** (voir [Spécificités Maroc](#spécificités-maroc)).

---

## Sécurité

- 🔒 **Ne committe jamais** `config.json` ni tes tokens (déjà couvert par `.gitignore`).
- 🔒 **Active la vérification de signature** en prod : renseigne `app_secret`. Tant qu'il
  est vide, `verifySignature()` laisse passer (pratique en dev, **risqué en prod**).
- 🔒 Sers le webhook **en HTTPS uniquement** et garde le `verify_token` secret.
- 🔒 Utilise un **token permanent d'utilisateur système** (pas le token 24 h) en prod.

---

## Tarification 2026

Depuis le **1ᵉʳ juillet 2025**, facturation **au message** (plus à la conversation) :

| Catégorie | Facturation |
|---|---|
| **Service** (réponse dans la fenêtre 24 h) | **Gratuit** |
| **Utility** (template dans la fenêtre 24 h) | **Gratuit** |
| **Authentication** (OTP) | Payant |
| **Marketing** | Payant |

- Facturé **à la livraison uniquement** (un message non délivré n'est pas facturé).
- Tarif basé sur l'**indicatif pays du destinataire** (pour du `+212`, c'est le tarif Maroc).
- Barème officiel : [developers.facebook.com — pricing](https://developers.facebook.com/docs/whatsapp/pricing).

---

## Spécificités Maroc

- 🗣️ **Langues** : prévois **Français** + **Arabe (RTL)**, la **darija** étant courante à l'oral.
  Crée des templates multilingues et laisse l'utilisateur choisir (menu à boutons).
- 💳 **WhatsApp Pay indisponible au Maroc** → le paiement se fait par **lien externe**
  (CMI, PayZone…) ou catalogue in-chat + checkout externe.
- ☎️ **Numéro** : tu peux enregistrer un **+212** dédié ; il devient l'identité business
  (un numéro déjà utilisé sur l'app WhatsApp normale doit en être détaché au préalable).
- 📈 WhatsApp est le canal roi au Maroc : idéal pour support, prise de commande, RDV, notifications.

---

## Passer en production

- [ ] Numéro **réel** vérifié + **vérification Meta Business** (déplafonne les volumes).
- [ ] **Token permanent** (utilisateur système), stocké en variable d'environnement.
- [ ] `app_secret` renseigné → **signature vérifiée**.
- [ ] Webhook derrière un vrai domaine HTTPS (Nginx/Apache + PHP-FPM), plus ngrok.
- [ ] File d'attente / worker pour le traitement asynchrone si le volume grimpe.
- [ ] Templates **FR/AR** approuvés pour tous tes cas d'usage d'initiation.

---

### Référence rapide de l'API

- Cloud API : <https://developers.facebook.com/docs/whatsapp/cloud-api>
- Messages : <https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages>
- Webhooks : <https://developers.facebook.com/docs/whatsapp/cloud-api/guides/set-up-webhooks>
