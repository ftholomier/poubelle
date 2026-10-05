/*
 * Déconnexion automatique du back-office après 30 minutes sans activité (délai donné par le serveur :
 * <body data-idle="secondes">). Activité : clavier, souris, toucher, dans n'importe quel onglet du
 * back-office (partagée par le stockage local). Deux minutes avant la fin, avertissement « Rester
 * connecté ». Un texte saisi sans enregistrer est signalé au serveur (au plus toutes les 4 minutes), pour
 * que la session ne se ferme pas pendant qu'on écrit. Les appels automatiques (verrou des fiches,
 * rafraîchissements) ne comptent pas. Au bout du délai : déconnexion, puis page de connexion, qui
 * ramène à la même page ; une fiche en cours de modification garde son brouillon sur l'ordinateur.
 */
(function () {
  'use strict';
  const BO = window.BO;
  const limit = (+document.body.dataset.idle || 0) * 1000;
  if (!BO || !limit) return;
  const WARN = Math.min(120000, limit / 4);
  const KEEP = Math.min(240000, limit / 4);
  const KEY = 'bo-activite', OUT = 'bo-deconnexion';
  let last = Date.now(), sent = Date.now(), wrote = 0, box = null, ending = false;

  const read = () => { try { return +localStorage.getItem(KEY) || 0; } catch (e) { return 0; } };
  const write = (k, v) => { try { localStorage.setItem(k, String(v)); } catch (e) { /* stockage indisponible : onglet seul */ } };
  const share = () => { if (last - wrote > 5000) { wrote = last; write(KEY, last); } };

  /* ------------------------------------------------------------ activité */
  const mark = e => {
    if (box) return; // avertissement affiché : seuls ses boutons comptent
    last = Date.now();
    share();
  };
  ['pointerdown', 'keydown', 'input', 'wheel', 'touchstart', 'mousemove'].forEach(ev => document.addEventListener(ev, mark, { passive: true, capture: true }));
  wrote = last;
  write(KEY, last); // page ouverte : c'est une activité

  const keepalive = async () => {
    sent = Date.now();
    const r = await BO.post('/admin/api/actif', {}).catch(() => null);
    if (r && r._status === 401) leave(!!r.idle);
  };

  /* ------------------------------------------------------------ avertissement */
  const fmt = ms => { const s = Math.max(0, Math.ceil(ms / 1000)); return Math.floor(s / 60) + ' min ' + String(s % 60).padStart(2, '0') + ' s'; };
  const stay = () => {
    hide();
    last = Date.now();
    wrote = last;
    write(KEY, last);
    keepalive();
  };
  function show(left) {
    if (!box) {
      box = document.createElement('div');
      box.className = 'modal';
      box.innerHTML = '<div class="modal__box" role="alertdialog" aria-modal="true" aria-labelledby="idle-t" aria-describedby="idle-d">'
        + '<h2 class="modal__t" id="idle-t">Toujours là ?</h2>'
        + '<p id="idle-d" style="margin:0;font-size:16px;line-height:1.45">Aucune activité depuis un moment : vous serez déconnecté dans <b data-idle-left></b>. Une fiche en cours de modification garde son brouillon sur cet ordinateur.</p>'
        + '<div class="row row--end"><button type="button" class="btn" data-idle-out>Se déconnecter</button><button type="button" class="btn btn--navy" data-idle-stay>Rester connecté</button></div></div>';
      document.body.appendChild(box);
      box.addEventListener('click', e => {
        if (e.target.closest('[data-idle-out]')) logout(true);
        else if (e.target.closest('[data-idle-stay]') || e.target === box) stay();
      });
      box.addEventListener('keydown', e => { if (e.key === 'Escape') stay(); });
      box.querySelector('[data-idle-stay]').focus();
    }
    box.querySelector('[data-idle-left]').textContent = fmt(left);
  }
  function hide() {
    if (box) { box.remove(); box = null; }
  }

  /* ------------------------------------------------------------ fin de session */
  function leave(idle) {
    if (BO.leaving) return;
    ending = true;
    hide();
    if (idle) write(OUT, Date.now());
    BO.leaving = true; // pas de « Quitter la page ? » : le brouillon est déjà gardé
    location.href = '/admin/connexion?' + (idle ? 'inactif=1&' : '') + 'r=' + encodeURIComponent(location.pathname + location.search);
  }
  async function logout(asked = false) {
    if (ending) return;
    ending = true;
    hide();
    if (asked) {
      // « Se déconnecter » dans l'avertissement : déconnexion ordinaire.
      const f = document.createElement('form');
      f.method = 'post';
      f.action = '/admin/deconnexion';
      f.innerHTML = '<input type="hidden" name="_csrf" value="' + BO.esc(BO.CSRF) + '">';
      document.body.appendChild(f);
      BO.leaving = true;
      f.submit();
      return;
    }
    const r = await BO.post('/admin/api/inactif', {}, true, true).catch(() => null);
    if (r && r.active) {
      // Une action récente vue par le serveur (autre onglet, autre fenêtre) : la session continue.
      ending = false;
      last = Date.now() - (r.age || 0) * 1000;
      return;
    }
    leave(true);
  }

  function tick() {
    if (ending) return;
    const act = Math.max(last, read());
    const idle = Date.now() - act;
    if (idle >= limit) { logout(); return; }
    if (idle >= limit - WARN) show(limit - idle); else hide();
    if (act > sent && Date.now() - sent >= KEEP) keepalive();
  }

  // Déconnexion pour inactivité dans un autre onglet, ou session fermée par le serveur.
  window.addEventListener('storage', e => {
    if (e.key === OUT && !ending) leave(true);
    else if (e.key === KEY) tick();
  });
  document.addEventListener('bo:expired', e => { if (!ending) leave(!!(e.detail && e.detail.idle)); });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
  setInterval(tick, 1000);
})();
