// Capteur du micro pour l'assistant vocal : transmet chaque bloc audio au fil principal.
// Fichier à part (et non créé à la volée) pour respecter la politique de sécurité du contenu (CSP).
class Capteur extends AudioWorkletProcessor {
  process(inputs) {
    const ch = inputs[0] && inputs[0][0];
    if (ch) this.port.postMessage(ch.slice(0));
    return true;
  }
}
registerProcessor("capteur", Capteur);
