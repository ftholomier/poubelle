<?php
/** Agenda. Variables : $p, $next, $past */
use App\Vitrine\Host;
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => $p['title'], 'lead' => $p['lead'], 'eyebrow' => 'Rendez-vous', 'crumbs' => [[$p['title'], Host::url('/agenda/')]]]) ?>
<section class="section">
  <div class="wrap vcols">
    <div class="vcols__main">
      <h2 class="h-2">À venir</h2>
      <?php if ($next): ?>
        <div class="vevents mt-20"><?php foreach ($next as $e): ?><?= \App\Core\View::partial('vitrine/partials/event-row', ['e' => $e]) ?><?php endforeach; ?></div>
      <?php else: ?>
        <p class="lead mt-20"><?= e($p['empty_text'] ?? '') ?></p>
      <?php endif; ?>
      <?php if ($past): ?>
        <h2 class="h-3 mt-40" style="margin-top:64px">Déjà passés</h2>
        <div class="vevents vevents--past mt-20"><?php foreach ($past as $e): ?><?= \App\Core\View::partial('vitrine/partials/event-row', ['e' => $e]) ?><?php endforeach; ?></div>
      <?php endif; ?>
    </div>
    <aside class="vcols__side">
      <div class="vbox">
        <h2 class="h-3">Ne rien manquer</h2>
        <p class="mt-20" style="font-size:17px">Ajoutez l’agenda de l’association à votre téléphone ou à votre messagerie : les rendez-vous s’y mettront à jour tout seuls.</p>
        <a class="btn btn--navy btn--block" href="<?= e(Host::url('/agenda/agenda.ics')) ?>">S’abonner à l’agenda</a>
        <p class="xs muted mt-20" style="font-size:14px">Fichier au format iCalendar (Google Agenda, Outlook, Apple Calendrier).</p>
        <hr class="vsep">
        <h2 class="h-3">La newsletter</h2>
        <p class="mt-20" style="font-size:17px">Les rendez-vous et les nouvelles du musée, chaque semaine.</p>
        <?= \App\Core\View::partial('vitrine/partials/newsletter-form', ['id' => 'nl-agenda', 'compact' => true]) ?>
      </div>
    </aside>
  </div>
</section>
