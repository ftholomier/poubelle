<?php
/** Tableau importé (wpDataTables) affiché tel quel. Variables : $table [title, headers, rows], $caption */
if (empty($table['rows'])) {
    return;
}
$num = function (string $v): bool {
    return (bool) preg_match('/^[\d\s.,+\-–%]+$/u', trim($v)) && trim($v) !== '';
};
?>
<div class="dtable-wrap">
  <table class="dtable">
    <?php if (!empty($caption) || !empty($table['title'])): ?><caption class="sr-only"><?= e($caption ?? $table['title']) ?></caption><?php endif; ?>
    <?php if (!empty($table['headers'])): ?>
    <thead><tr><?php foreach ($table['headers'] as $h): ?><th scope="col"><?= e($h) ?></th><?php endforeach; ?></tr></thead>
    <?php endif; ?>
    <tbody>
      <?php foreach ($table['rows'] as $row):
          $isTotal = preg_match('/^total/iu', trim((string) ($row[0] ?? '')));
      ?>
      <tr<?= $isTotal ? ' class="total"' : '' ?>>
        <?php foreach ($row as $i => $cell): ?>
          <td<?= $i > 0 && $num((string) $cell) ? ' class="n"' : ($i === 0 ? ' class="strong"' : '') ?>><?= e($cell) ?></td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
