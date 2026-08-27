<?php
/**
 * Écran Conversations : la liste à gauche, le fil à droite.
 *
 * @var string[] $mois
 * @var string $courant
 * @var array $conversations
 * @var array|null $ouverte
 * @var int $conservation
 */
use App\Core\Csrf;

/** Un mois « 2026-08 » écrit en toutes lettres. */
$moisLisible = static function (string $m): string {
    static $noms = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                    'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    [$a, $n] = array_pad(explode('-', $m), 2, '1');
    return ($noms[(int) $n] ?? $m) . ' ' . $a;
};

/** Le contact relevé, s'il y en a un. */
$contactDe = static function (array $c): string {
    $bouts = array_filter([
        $c['contact']['nom'] ?? '',
        $c['contact']['telephone'] ?? '',
        $c['contact']['email'] ?? '',
    ]);
    return implode(' · ', $bouts);
};
?>
<p class="bo-aide">
  Les échanges des visiteurs avec l’assistant, du plus récent au plus ancien.
  Les coordonnées laissées y sont mises en évidence : c’est là que se trouvent
  les demandes de rappel. Tout est effacé automatiquement au bout de
  <?= (int) $conservation ?> mois.
</p>

<?php if ($mois === []): ?>
  <p class="bo-vide">Aucune conversation pour l’instant.</p>
<?php else: ?>

<nav class="bo-onglets" aria-label="Mois">
  <?php foreach ($mois as $m): ?>
    <a class="bo-onglet<?= $m === $courant ? ' bo-onglet--actif' : '' ?>"
       href="<?= url('/admin/conversations?mois=' . rawurlencode($m)) ?>"
       <?= $m === $courant ? 'aria-current="page"' : '' ?>><?= e($moisLisible($m)) ?></a>
  <?php endforeach; ?>
</nav>

<div class="bo-conv">
  <div class="bo-conv__liste">
    <?php if ($conversations === []): ?>
      <p class="bo-vide">Aucune conversation ce mois-ci.</p>
    <?php endif; ?>

    <?php foreach ($conversations as $c): ?>
      <?php
      $contact = $contactDe($c);
      $premier = '';
      foreach ($c['messages'] ?? [] as $m) {
          if (($m['role'] ?? '') === 'visiteur') { $premier = (string) $m['texte']; break; }
      }
      $actif = $ouverte !== null && ($ouverte['id'] ?? '') === $c['id'];
      ?>
      <a class="bo-conv__entree<?= $actif ? ' bo-conv__entree--active' : '' ?><?= empty($c['lu']) ? ' bo-conv__entree--neuve' : '' ?>"
         href="<?= url('/admin/conversations?mois=' . rawurlencode($courant) . '&id=' . rawurlencode((string) $c['id'])) ?>">
        <span class="bo-conv__date">
          <?= e(date('d/m à H\\hi', (int) ($c['derniere'] ?? time()))) ?>
          <?php if (empty($c['lu'])): ?><span class="bo-conv__neuf">nouveau</span><?php endif; ?>
        </span>
        <?php if ($contact !== ''): ?>
          <span class="bo-conv__contact"><?= e($contact) ?></span>
        <?php endif; ?>
        <span class="bo-conv__extrait"><?= e(mb_substr($premier, 0, 90)) ?></span>
        <span class="bo-conv__compte"><?= (int) (count($c['messages'] ?? []) / 2) ?> question(s)</span>
      </a>
    <?php endforeach; ?>

    <?php if ($conversations !== []): ?>
      <form class="bo-conv__purge" method="post" action="<?= url('/admin/conversations/vider') ?>"
            data-confirmer="Supprimer tous les échanges de <?= e($moisLisible($courant)) ?> ?">
        <?= Csrf::champ() ?>
        <input type="hidden" name="mois" value="<?= e($courant) ?>">
        <button class="bo-btn bo-btn--petit bo-btn--danger" type="submit">Vider ce mois</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="bo-conv__fil">
    <?php if ($ouverte === null): ?>
      <p class="bo-vide">Choisissez une conversation à gauche.</p>
    <?php else: ?>
      <?php $contact = $contactDe($ouverte); ?>

      <?php if ($contact !== ''): ?>
        <div class="bo-conv__fiche">
          <h2>Coordonnées laissées</h2>
          <ul>
            <?php foreach (['nom' => 'Nom', 'telephone' => 'Téléphone', 'email' => 'E-mail', 'message' => 'Message'] as $cle => $libelle): ?>
              <?php if (($ouverte['contact'][$cle] ?? '') !== ''): ?>
                <li>
                  <span><?= e($libelle) ?></span>
                  <?php if ($cle === 'telephone'): ?>
                    <a href="tel:<?= e(preg_replace('/\s+/', '', (string) $ouverte['contact'][$cle])) ?>"><?= e($ouverte['contact'][$cle]) ?></a>
                  <?php elseif ($cle === 'email'): ?>
                    <a href="mailto:<?= e($ouverte['contact'][$cle]) ?>"><?= e($ouverte['contact'][$cle]) ?></a>
                  <?php else: ?>
                    <?= e($ouverte['contact'][$cle]) ?>
                  <?php endif; ?>
                </li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <p class="bo-aide">
        Début le <?= e(date('d/m/Y à H\\hi', (int) ($ouverte['debut'] ?? time()))) ?>.
        <?php if (($ouverte['pages'] ?? []) !== []): ?>
          Pages visitées : <?= e(implode(', ', $ouverte['pages'])) ?>.
        <?php endif; ?>
      </p>

      <div class="bo-conv__messages">
        <?php foreach ($ouverte['messages'] ?? [] as $m): ?>
          <p class="bo-conv__bulle bo-conv__bulle--<?= e($m['role'] ?? 'robot') ?>"><?= nl2br(e((string) $m['texte'])) ?></p>
        <?php endforeach; ?>
        <?php if (($ouverte['messages'] ?? []) === []): ?>
          <p class="bo-vide">Demande de rappel envoyée sans question posée.</p>
        <?php endif; ?>
      </div>

      <form method="post" action="<?= url('/admin/conversations/supprimer') ?>"
            data-confirmer="Supprimer définitivement cette conversation ?">
        <?= Csrf::champ() ?>
        <input type="hidden" name="mois" value="<?= e($courant) ?>">
        <input type="hidden" name="id" value="<?= e((string) $ouverte['id']) ?>">
        <button class="bo-btn bo-btn--petit bo-btn--danger" type="submit">Supprimer cette conversation</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>
