<?php
/**
 * Échanges avec l'assistant Régie : ce que les visiteurs demandent, ce qu'il répond.
 *
 * @var array  $stats      fréquentation des 30 derniers jours
 * @var array  $months     mois disponibles, du plus récent au plus ancien
 * @var string $month      mois affiché, ou « tout »
 * @var string $query      recherche
 * @var string $view       filtre : '', 'sans-lien', 'sans-ia'
 * @var array  $threads    conversations de la page
 * @var int    $total      conversations retenues par les filtres
 * @var int    $questions  questions qu'elles contiennent
 * @var int    $page       @var int $pages
 * @var int    $retention  durée de conservation, en mois
 * @var bool   $gemini     l'IA est-elle branchée ?
 * @var array  $mailing    envoi par e-mail : on, to (adresses), idle (minutes)
 */
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Services\Regie;

$nf = static fn(int $n): string => number_format($n, 0, ',', ' ');
$monthNames = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre',
               'octobre', 'novembre', 'décembre'];
$monthLabel = static function (string $ym) use ($monthNames): string {
    [$y, $m] = array_map('intval', explode('-', $ym) + [1 => '1']);
    return ($monthNames[$m - 1] ?? $ym) . ' ' . $y;
};
$filtered = $query !== '' || $view !== '';
$params = array_filter(['mois' => $month, 'q' => $query, 'vue' => $view], static fn(string $v): bool => $v !== '');
$link = static fn(array $extra = []): string => '/admin/assistant?' . http_build_query($extra + $params);
$self = $link($page > 1 ? ['p' => $page] : []);
?>
<div class="admin-head">
  <div>
    <h1>Assistant IA</h1>
    <p>Ce que les visiteurs demandent à Régie, et ce qu’elle leur répond, avec la date et l’heure.</p>
    <p>
      <?php if ($mailing['on'] && $mailing['to'] !== []): ?>
        Chaque conversation vous est aussi envoyée par e-mail, à <?= e(implode(', ', $mailing['to'])) ?>,
        une fois terminée : <?= (int) $mailing['idle'] ?> minutes sans nouvelle question.
      <?php elseif ($mailing['on']): ?>
        L’envoi des conversations par e-mail attend une adresse d’alerte.
      <?php else: ?>
        L’envoi des conversations par e-mail est coupé.
      <?php endif; ?>
      <a href="/admin/alertes">Alertes &amp; e-mails</a>
    </p>
  </div>
</div>

<?php if (($notice = Session::flash('notice')) !== null): ?>
  <div class="notice notice-ok" role="status"><?= e((string) $notice) ?></div>
<?php endif; ?>

<?= View::partial('admin/partials-assistant-tabs', ['current' => '/admin/assistant']) ?>

<?php if (!$gemini): ?>
  <div class="notice notice-wait">
    Aucune clé Gemini : Régie répond pour l’instant avec des extraits du site, sans IA.
    La clé se règle dans <a href="/admin/cles-api">Clés d’API</a>.
  </div>
<?php endif; ?>

<div class="kpi-grid">
  <?php foreach ([
      ['Questions aujourd’hui', $stats['today'], '#FF4B3E', ''],
      ['7 derniers jours', $stats['week'], '#6D4AFF', ''],
      ['30 derniers jours', $stats['month'], '#0FBFA4', $nf($stats['conversations']) . ' conversation(s)'],
      ['Réponses sans lien vers le site', $stats['unlinked'], '#FFC531',
       $stats['month'] > 0 ? round($stats['unlinked'] * 100 / $stats['month']) . ' % sur 30 jours' : ''],
  ] as [$label, $value, $color, $sub]): ?>
    <div class="kpi">
      <div class="n" style="color:<?= e($color) ?>"><?= e($nf((int) $value)) ?></div>
      <div class="l"><?= e($label) ?><?php if ($sub !== ''): ?><br><span style="font-weight:600"><?= e($sub) ?></span><?php endif; ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="admin-card">
  <form method="get" action="/admin/assistant" class="chat-filters">
    <label class="field chat-search">
      <span class="label">Rechercher dans les questions et les réponses</span>
      <input class="input" type="search" name="q" value="<?= e($query) ?>" placeholder="GUSO, régisseur, salaire…">
    </label>
    <label class="field">
      <span class="label">Mois</span>
      <select class="select" name="mois">
        <?php foreach ($months === [] ? [date('Y-m')] : $months as $m): ?>
          <option value="<?= e($m) ?>"<?= $m === $month ? ' selected' : '' ?>><?= e(ucfirst($monthLabel($m))) ?></option>
        <?php endforeach; ?>
        <?php if (count($months) > 1): ?>
          <option value="tout"<?= $month === 'tout' ? ' selected' : '' ?>>Tous les mois</option>
        <?php endif; ?>
      </select>
    </label>
    <label class="field">
      <span class="label">Réponses</span>
      <select class="select" name="vue">
        <option value="">Toutes</option>
        <option value="sans-lien"<?= $view === 'sans-lien' ? ' selected' : '' ?>>Sans lien vers le site</option>
        <option value="sans-ia"<?= $view === 'sans-ia' ? ' selected' : '' ?>>Réponses sans l’IA</option>
      </select>
    </label>
    <div class="chat-filters-go">
      <button type="submit" class="btn btn-coral btn-sm">Filtrer</button>
      <?php if ($filtered): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e('/admin/assistant?' . http_build_query(['mois' => $month])) ?>">Tout afficher</a>
      <?php endif; ?>
    </div>
  </form>

  <p class="muted chat-count">
    <?php if ($total === 0): ?>
      <?= $filtered ? 'Aucun échange ne correspond à ces critères.' : 'Aucun échange pour le moment : les questions posées à Régie apparaîtront ici.' ?>
    <?php else: ?>
      <strong><?= e($nf($total)) ?></strong> conversation(s), <?= e($nf($questions)) ?> question(s)
      <?= $month === 'tout' ? 'sur l’ensemble de l’historique' : 'en ' . e($monthLabel($month)) ?>
      <?= $filtered ? ' · en surligné, les échanges qui répondent à la recherche' : '' ?>
    <?php endif; ?>
  </p>

  <?php if ($threads !== []): ?>
    <div class="chat-list">
      <?php foreach ($threads as $thread): ?>
        <?php
          $startedAt = (int) strtotime((string) $thread['started']);
          $day = date('Y-m-d', $startedAt);
          $count = count($thread['exchanges']);
        ?>
        <article class="chat" aria-label="Conversation du <?= e(date('d/m/Y à H:i', $startedAt)) ?>">
          <header class="chat-head">
            <span class="when"><?= e(date('d/m/Y', $startedAt)) ?> à <?= e(date('H:i', $startedAt)) ?></span>
            <span class="meta"><?= $count ?> question<?= $count > 1 ? 's' : '' ?></span>
            <?php if ($thread['lang'] !== ''): ?>
              <span class="state state-neutral" title="Langue de la page"><?= e(strtoupper((string) $thread['lang'])) ?></span>
            <?php endif; ?>
            <?php if ($thread['page'] !== ''): ?>
              <a class="meta chat-page" href="<?= e((string) $thread['page']) ?>" target="_blank" rel="noopener"
                 title="Page d’où la question a été posée"><?= e(str_excerpt((string) $thread['page'], 60)) ?></a>
            <?php endif; ?>
            <span class="spacer"></span>
            <form method="post" action="<?= e($self) ?>">
              <?= Csrf::field('admin-assistant') ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="conv" value="<?= e((string) $thread['conv']) ?>">
              <button type="submit" class="btn btn-ghost btn-sm"
                      data-confirm="Effacer cette conversation de l’historique ? C’est définitif.">Effacer</button>
            </form>
          </header>

          <?php foreach ($thread['exchanges'] as $i => $x): ?>
            <?php $at = (int) strtotime((string) $x['at']); ?>
            <div class="chat-turn<?= in_array($i, $thread['hits'], true) ? ' is-match' : '' ?>">
              <time class="time" datetime="<?= e((string) $x['at']) ?>">
                <?= e(date('Y-m-d', $at) === $day ? date('H:i', $at) : date('d/m H:i', $at)) ?>
              </time>
              <div>
                <p class="chat-q"><span class="who">Question</span><?= e((string) $x['q']) ?></p>
                <div class="chat-a"><span class="who">Réponse</span><?= Regie::display((string) $x['a'], (array) $x['links']) ?></div>
                <div class="chat-tags">
                  <?php if ($x['note'] === 'gemini_empty'): ?>
                    <span class="state state-err">Gemini n’a pas répondu : réponse de secours</span>
                  <?php elseif ($x['source'] === 'index'): ?>
                    <span class="state state-neutral">Extrait du site, sans l’IA</span>
                  <?php endif; ?>
                  <?php if ($x['source'] === 'none'): ?>
                    <span class="state state-wait">Sans réponse</span>
                  <?php elseif ($x['links'] === []): ?>
                    <span class="state state-wait" title="Question hors sujet, ou page qui manque au site">Sans lien vers le site</span>
                  <?php endif; ?>
                  <?php if ($x['ms'] > 0): ?>
                    <span class="meta"><?= e(number_format($x['ms'] / 1000, 1, ',', ' ')) ?> s<?= $x['model'] !== '' ? ' · ' . e((string) $x['model']) : '' ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="chat-pager" aria-label="Pages de l’historique">
        <?php if ($page > 1): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e($link(['p' => $page - 1])) ?>">← Plus récentes</a>
        <?php endif; ?>
        <span class="meta">Page <?= $page ?> sur <?= $pages ?></span>
        <?php if ($page < $pages): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e($link(['p' => $page + 1])) ?>">Plus anciennes →</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

  <p class="muted chat-privacy">
    Rien n’identifie les visiteurs : ni adresse IP, ni compte, ni cookie. Une conversation est un numéro tiré
    au hasard, qui disparaît avec la session du visiteur. Les adresses e-mail et les numéros de téléphone tapés
    dans une question sont masqués avant l’enregistrement.
    <?php if ($retention > 0): ?>
      Les échanges sont effacés automatiquement au bout de <?= $retention ?> mois.
    <?php endif; ?>
  </p>
</div>
