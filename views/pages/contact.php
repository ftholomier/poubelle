<?php /** @var array|null $page @var array $erreurs @var array $valeurs */ ?>
<article class="page">
  <h1><?= e($page['titre'] ?? 'Contact') ?></h1>
  <form method="post" action="<?= url('/contact') ?>" novalidate>
    <p hidden><label>Ne pas remplir <input type="text" name="site" tabindex="-1" autocomplete="off"></label></p>
    <?php foreach ([['nom','Nom','text'],['email','E-mail','email'],['tel','Téléphone','tel']] as [$n,$l,$t]): ?>
      <p>
        <label for="f-<?= $n ?>"><?= $l ?></label>
        <input id="f-<?= $n ?>" type="<?= $t ?>" name="<?= $n ?>" value="<?= e($valeurs[$n] ?? '') ?>">
        <?php if (!empty($erreurs[$n])): ?><span class="erreur"><?= e($erreurs[$n]) ?></span><?php endif; ?>
      </p>
    <?php endforeach; ?>
    <p>
      <label for="f-message">Message</label>
      <textarea id="f-message" name="message" rows="6"><?= e($valeurs['message'] ?? '') ?></textarea>
      <?php if (!empty($erreurs['message'])): ?><span class="erreur"><?= e($erreurs['message']) ?></span><?php endif; ?>
    </p>
    <p><button type="submit">Envoyer</button></p>
  </form>
</article>
