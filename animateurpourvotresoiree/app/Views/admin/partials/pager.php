<?php /** @var int $page @var int $pages @var callable $link */ ?>
<?= App\Core\View::partial('front/partials/pagination', ['page' => $page, 'pages' => $pages, 'link' => $link]) ?>
