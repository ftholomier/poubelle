// Accès à l'API PHP

export class ApiError extends Error {
  constructor(message, status) {
    super(message);
    this.status = status;
  }
}

/** Coût de l'IA du mois joint par le serveur à chaque réponse (« 0.01234;agence ») : met à jour le compteur. */
export function signalerCout(res) {
  const c = res.headers.get("X-Cout-IA");
  if (c !== null) window.dispatchEvent(new CustomEvent("cout-ia", { detail: c }));
}

export async function api(route, { method = "GET", query = {}, body, form, signal } = {}) {
  const params = new URLSearchParams({ r: route, ...query });
  const headers = { "X-Requested-With": "visite-immo" };
  let payload;
  if (form) payload = form;
  else if (body !== undefined) {
    headers["Content-Type"] = "application/json";
    payload = JSON.stringify(body);
  }

  let res;
  try {
    res = await fetch(`api/?${params}`, { method, headers, body: payload, credentials: "same-origin", signal });
  } catch (e) {
    if (e.name === "AbortError") throw e; // requête remplacée par une plus récente
    throw new ApiError("Pas de connexion réseau.", 0);
  }
  signalerCout(res);
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    if (res.status === 401 && route !== "login") window.dispatchEvent(new Event("session-expired"));
    throw new ApiError(data.error || `Erreur ${res.status}`, res.status);
  }
  return data;
}

export const audioUrl = (id, n) => `api/?${new URLSearchParams({ r: "audio", id, n })}`;
