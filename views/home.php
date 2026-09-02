<?php

use App\View;

/** @var list<array<string,mixed>> $sections */
/** @var array<string,mixed> $site */

foreach ($sections as $section) {
    $kind = (string) ($section['kind'] ?? 'statement');
    $template = 'partials/section-' . $kind;
    if (!is_file(APP_VIEWS . '/' . $template . '.php')) {
        $template = 'partials/section-statement';
    }
    echo View::capture($template, ['section' => $section, 'site' => $site]);
}
