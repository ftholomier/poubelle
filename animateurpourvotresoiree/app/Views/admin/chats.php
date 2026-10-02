<?php
use App\Core\Url;
/** @var array $items @var ?array $current */
?>
<p><a class="link small" href="<?= e(Url::admin('ia')) ?>">← Intelligence artificielle</a></p>
<div class="adm-head"><div><h1>Conversations <span class="serif">de l'assistant</span></h1><p>Pour comprendre ce que cherchent les visiteurs et améliorer les consignes. Conservées 6 mois.</p></div></div>
<div class="adm-cols">
  <div class="table-wrap"><table class="tbl"><thead><tr><th>Date</th><th>Premier message</th><th class="num">Messages</th><th class="num">Pros proposés</th></tr></thead><tbody>
    <?php foreach ($items as $c): ?><tr><td class="small"><?= e(date_fr($c['created'], 'datetime')) ?></td><td><a class="t-main" href="?id=<?= (int) $c['id'] ?>"><?= e($c['first'] ?: '—') ?></a></td><td class="num"><?= (int) $c['n'] ?></td><td class="num"><?= (int) $c['cards'] ?></td></tr><?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="4"><div class="empty-sm">Aucune conversation.</div></td></tr><?php endif; ?>
  </tbody></table>
  <?= App\Core\View::partial('admin/partials/pager', ['page' => $page, 'pages' => $pages, 'link' => $link]) ?></div>
  <aside><div class="box">
    <?php if (!$current): ?><p class="muted small">Sélectionnez une conversation.</p><?php else: ?>
      <h2>Conversation n° <?= (int) $current['id'] ?></h2><p class="small muted">Page : <?= e((string) ($current['page'] ?? '')) ?></p>
      <div class="stack"><?php foreach ((array) $current['messages'] as $m): ?><div class="msg <?= $m['role'] === 'user' ? 'me' : 'bot' ?>" style="max-width:100%"><?= nl2br(e((string) $m['text'])) ?><?= !empty($m['quote']) ? '<br><small>→ devis proposé</small>' : '' ?></div><?php endforeach; ?></div>
    <?php endif; ?>
  </div></aside>
</div>
