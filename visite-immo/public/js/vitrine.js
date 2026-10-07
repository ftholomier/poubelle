// Page publique d'un bien : demande de visite (créneaux libres) et assistant en ligne.
(() => {
  const post = async (a, body) => {
    const r = await fetch(`${location.pathname}?b=${encodeURIComponent(new URLSearchParams(location.search).get("b"))}&a=${a}`, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-Requested-With": "vitrine" },
      body: JSON.stringify(body),
    });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.error || "Erreur, réessayez.");
    return d;
  };

  // Formulaire de visite
  const form = document.getElementById("vt-form");
  document.querySelectorAll(".vt-visite .vt-creneau").forEach((b) => (b.onclick = () => {
    document.querySelectorAll(".vt-visite .vt-creneau").forEach((x) => x.classList.toggle("on", x === b && !b.classList.contains("on")));
    form.creneau.value = b.classList.contains("on") ? b.dataset.creneau : "";
  }));
  form.onsubmit = async (e) => {
    e.preventDefault();
    const msg = document.getElementById("vt-msg");
    const btn = form.querySelector("button");
    btn.disabled = true;
    try {
      const r = await post("contact", Object.fromEntries(new FormData(form)));
      form.innerHTML = `<p class="es-msg ok">${r.rdv ? `Visite réservée ${r.rdv}. Vous allez recevoir une confirmation.` : "Merci ! Votre conseiller vous recontacte très vite."}</p>`;
    } catch (err) {
      msg.className = "es-msg erreur";
      msg.textContent = err.message;
      btn.disabled = false;
    }
  };

  // Assistant
  const chat = document.getElementById("vt-chat"), fil = document.getElementById("vt-fil");
  let conversation = sessionStorage.getItem("vt-conv") || "";
  const bulle = (texte, qui) => {
    const d = document.createElement("div");
    d.className = `vt-bulle ${qui}`;
    d.textContent = texte;
    fil.append(d);
    fil.scrollTop = fil.scrollHeight;
    return d;
  };
  const envoyer = async (texte) => {
    bulle(texte, "moi");
    const attente = bulle("…", "ia");
    try {
      const r = await post("chat", { message: texte, conversation });
      conversation = r.conversation;
      try { sessionStorage.setItem("vt-conv", conversation); } catch { /* navigation privée */ }
      attente.textContent = r.reponse;
      for (const c of r.creneaux || []) {
        const b = document.createElement("button");
        b.className = "vt-creneau";
        b.textContent = c.libelle;
        b.onclick = () => envoyer(`Je choisis ${c.libelle}.`);
        attente.append(document.createElement("br"), b);
      }
    } catch (err) {
      attente.textContent = err.message;
    }
  };
  document.getElementById("vt-chat-ouvrir").onclick = () => { chat.hidden = false; document.getElementById("vt-message").focus(); };
  document.getElementById("vt-chat-fermer").onclick = () => (chat.hidden = true);
  document.getElementById("vt-saisie").onsubmit = (e) => {
    e.preventDefault();
    const i = document.getElementById("vt-message");
    const t = i.value.trim();
    if (!t) return;
    i.value = "";
    envoyer(t);
  };
})();
