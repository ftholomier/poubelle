<?php
/**
 * Tableau de bord.
 *
 * Trois étages, dans l'ordre où l'on s'en sert :
 *   1. ce qui demande une action — et rien du tout s'il n'y a rien à faire ;
 *   2. la fréquentation, parce que c'est la question qu'un élu pose ;
 *   3. le contenu : ce qui est en ligne, ce qui arrive, ce qu'on vient d'écrire.
 *
 * Les raccourcis sont des boutons, pas des liens en fin de page : ce sont des
 * gestes, ils doivent se voir et se viser.
 *
 * @var array $aFaire
 * @var array $audience
 * @var array $chiffres
 * @var array $prochains
 * @var array $dernieres
 */

/** « 2026-06-30 » → « 30 juin ». */
$enClair = static function (string $jour): string {
    $mois = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
             'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    [$a, $m, $j] = array_pad(explode('-', $jour), 3, '1');
    return (int) $j . ' ' . ($mois[(int) $m] ?? '');
};

$serie = $audience['serie'];
$pic   = max(1, ...array_values($serie ?: [1]));
?>

<?php /* ------------------------------------------------------ à faire --- */ ?>
<?php if ($aFaire !== []): ?>
  <section class="bo-afaire" aria-labelledby="bo-afaire-titre">
    <h2 class="bo-afaire__titre" id="bo-afaire-titre">À traiter</h2>
    <ul class="bo-afaire__liste">
      <?php foreach ($aFaire as $item): ?>
        <li class="bo-afaire__item bo-afaire__item--<?= e($item['ton']) ?>">
          <span class="bo-afaire__texte"><?= e($item['texte']) ?></span>
          <a class="bo-btn bo-btn--petit" href="<?= url($item['url']) ?>"><?= e($item['action']) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php /* --------------------------------------------------- fréquentation - */ ?>
<section class="bo-panneau" aria-labelledby="bo-audience-titre">
  <div class="bo-panneau__tete">
    <h2 id="bo-audience-titre">Fréquentation du site</h2>
    <p class="bo-panneau__note">30 derniers jours</p>
  </div>

  <?php if (!$audience['amorcee']): ?>
    <p class="bo-vide">
      La mesure commence aujourd’hui. Cette courbe se remplira d’elle-même, au fil
      des visites : rien n’est envoyé à personne, et rien n’est déposé chez les
      visiteurs — le site compte ses pages vues lui-même, sans cookie ni service
      extérieur.
    </p>
  <?php else: ?>
    <?php
      $ecart = $audience['precedent'] > 0
        ? round((($audience['total'] - $audience['precedent']) / $audience['precedent']) * 100)
        : null;
    ?>
    <div class="bo-audience">
      <div class="bo-audience__chiffre">
        <strong><?= number_format($audience['total'], 0, ',', ' ') ?></strong>
        <span>pages vues</span>
        <?php if ($ecart !== null): ?>
          <p class="bo-tendance bo-tendance--<?= $ecart >= 0 ? 'hausse' : 'baisse' ?>">
            <?= $ecart >= 0 ? '+' : '−' ?><?= abs((int) $ecart) ?> %
            <span>par rapport aux 30 jours précédents</span>
          </p>
        <?php endif; ?>
      </div>

      <?php /* La courbe est dessinée à la main : trente nombres ne valent pas
               une bibliothèque de graphiques, et le back-office ne charge
               aucun script extérieur — pas plus que le site. */ ?>
      <svg class="bo-courbe" viewBox="0 0 300 70" preserveAspectRatio="none"
           role="img" aria-label="Pages vues par jour sur les trente derniers jours, maximum <?= (int) $pic ?>">
        <?php
          $n = max(1, count($serie) - 1);
          $points = [];
          foreach (array_values($serie) as $i => $v) {
            $points[] = sprintf('%.1f,%.1f', $i * (300 / $n), 68 - ($v / $pic) * 60);
          }
        ?>
        <polyline class="bo-courbe__aire"
                  points="0,70 <?= e(implode(' ', $points)) ?> 300,70"></polyline>
        <polyline class="bo-courbe__trait" points="<?= e(implode(' ', $points)) ?>"></polyline>
      </svg>
    </div>

    <?php if ($audience['pages'] !== []): ?>
      <h3 class="bo-sous-titre">Les pages les plus consultées</h3>
      <ol class="bo-palmares">
        <?php $tete = max($audience['pages']); ?>
        <?php foreach ($audience['pages'] as $chemin => $vues): ?>
          <li class="bo-palmares__ligne">
            <a class="bo-palmares__nom" href="<?= url($chemin) ?>" target="_blank" rel="noopener"><?= e($chemin) ?></a>
            <span class="bo-palmares__jauge" aria-hidden="true">
              <span style="width: <?= (int) round($vues / max(1, $tete) * 100) ?>%"></span>
            </span>
            <span class="bo-palmares__vues"><?= (int) $vues ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php /* --------------------------------------------------------- chiffres - */ ?>
<div class="bo-stats">
  <?php foreach ($chiffres as $s): ?>
    <a class="bo-stat" href="<?= url($s['url']) ?>">
      <strong><?= e($s['valeur']) ?></strong>
      <span><?= e($s['libelle']) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<?php /* ------------------------------------------------------ deux listes - */ ?>
<div class="bo-colonnes">
  <section class="bo-panneau" aria-labelledby="bo-agenda-titre">
    <div class="bo-panneau__tete">
      <h2 id="bo-agenda-titre">Prochains rendez-vous</h2>
      <a class="bo-btn bo-btn--petit bo-btn--discret" href="<?= url('/admin/listes/agenda') ?>">Gérer l’agenda</a>
    </div>
    <?php if ($prochains === []): ?>
      <p class="bo-vide">Rien d’annoncé pour le moment.</p>
    <?php else: ?>
      <ul class="bo-fil">
        <?php foreach ($prochains as $e): ?>
          <li>
            <span class="bo-fil__date"><?= e($enClair((string) ($e['date'] ?? ''))) ?></span>
            <span class="bo-fil__nom"><?= e((string) ($e['titre'] ?? '')) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="bo-panneau" aria-labelledby="bo-actus-titre">
    <div class="bo-panneau__tete">
      <h2 id="bo-actus-titre">Dernières actualités</h2>
      <a class="bo-btn bo-btn--petit bo-btn--discret" href="<?= url('/admin/actualites') ?>">Gérer les actualités</a>
    </div>
    <?php if ($dernieres === []): ?>
      <p class="bo-vide">Aucune actualité publiée.</p>
    <?php else: ?>
      <ul class="bo-fil">
        <?php foreach ($dernieres as $a): ?>
          <li>
            <span class="bo-fil__date"><?= e($enClair((string) ($a['date'] ?? ''))) ?></span>
            <a class="bo-fil__nom" href="<?= url('/admin/actualites/' . rawurlencode((string) ($a['slug'] ?? ''))) ?>"><?= e((string) ($a['titre'] ?? '')) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<?php /* ------------------------------------------------------- raccourcis - */ ?>
<section class="bo-panneau" aria-labelledby="bo-gestes-titre">
  <div class="bo-panneau__tete">
    <h2 id="bo-gestes-titre">Ce qu’on fait le plus souvent</h2>
  </div>
  <div class="bo-gestes">
    <a class="bo-geste" href="<?= url('/admin/actualites') ?>">
      <strong>Écrire une actualité</strong>
      <span>Et la publier sur Facebook dans la foulée</span>
    </a>
    <a class="bo-geste" href="<?= url('/admin/listes/agenda') ?>">
      <strong>Annoncer un rendez-vous</strong>
      <span>Conseil, fête, permanence, réunion publique</span>
    </a>
    <a class="bo-geste" href="<?= url('/admin/listes/documents') ?>">
      <strong>Déposer un document</strong>
      <span>Compte-rendu, délibération, budget, bulletin</span>
    </a>
    <a class="bo-geste" href="<?= url('/admin/photos') ?>">
      <strong>Envoyer des photos</strong>
      <span>Elles alimentent l’album et les bandeaux</span>
    </a>
    <a class="bo-geste" href="<?= url('/admin/site') ?>">
      <strong>Horaires et coordonnées</strong>
      <span>Téléphone, adresse, ouverture du secrétariat</span>
    </a>
    <a class="bo-geste" href="<?= url('/admin/reseaux') ?>">
      <strong>Publier sur les réseaux</strong>
      <span>Facebook et Instagram, depuis le site</span>
    </a>
    <a class="bo-geste" href="<?= url('/admin/accueil') ?>">
      <strong>Page d’accueil</strong>
      <span>Bandeau, diaporama, textes d’introduction</span>
    </a>
    <a class="bo-geste" href="<?= url('/admin/mises-a-jour') ?>">
      <strong>Mettre le site à jour</strong>
      <span>Récupérer la dernière version du code</span>
    </a>
  </div>
</section>
