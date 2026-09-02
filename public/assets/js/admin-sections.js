/**
 * Confort d'édition du back-office.
 *
 * Deux choses : les listes d'entrées répétables de l'éditeur de section —
 * cartes, colonnes, chiffres — et la confirmation avant une suppression. Le
 * back-office fonctionne sans ce script : les formulaires s'envoient, et le
 * serveur refuse de lui-même ce qui ne doit pas être supprimé.
 */

/**
 * Demande confirmation avant d'envoyer un formulaire qui supprime.
 *
 * Le message vit dans un attribut plutôt que dans un « onsubmit » : écrit dans
 * du JavaScript, il serait rompu par la première apostrophe d'un titre — et
 * les titres français en sont pleins.
 */
document.addEventListener('submit', (event) => {
  const message = event.target?.dataset?.confirm;
  if (message && !window.confirm(message)) {
    event.preventDefault();
  }
});

/**
 * Renumérote les entrées d'une liste.
 *
 * Les noms de champ portent l'indice de l'entrée (« champ[cards][0][titre] »).
 * Après un ajout ou une suppression, il faut donc les réécrire, sinon deux
 * entrées partageraient le même indice et PHP n'en garderait qu'une.
 */
function renumber(repeater) {
  const items = repeater.querySelectorAll('[data-repeater-item]');

  items.forEach((item, index) => {
    item.querySelectorAll('[name]').forEach((input) => {
      input.name = input.name.replace(/\[(\d+|__i__)\]/, `[${index}]`);
    });
    const rank = item.querySelector('.repeater__rank');
    if (rank) rank.textContent = String(index + 1);
  });

  // Une liste vide reste modifiable : le bouton d'ajout ne disparaît jamais.
  repeater.querySelectorAll('[data-repeater-remove]').forEach((button) => {
    button.disabled = items.length <= 1;
  });
}

document.querySelectorAll('[data-repeater]').forEach((repeater) => {
  const list = repeater.querySelector('[data-repeater-items]');
  const template = repeater.querySelector('[data-repeater-template]');
  const add = repeater.querySelector('[data-repeater-add]');

  add?.addEventListener('click', () => {
    const clone = template.content.cloneNode(true);
    list.appendChild(clone);
    renumber(repeater);
    // Le curseur se place dans le premier champ de l'entrée qui vient d'apparaître.
    list.lastElementChild?.querySelector('input, textarea')?.focus();
  });

  repeater.addEventListener('click', (event) => {
    const button = event.target.closest('[data-repeater-remove]');
    if (!button) return;
    button.closest('[data-repeater-item]')?.remove();
    renumber(repeater);
  });

  renumber(repeater);
});
