<?php
/** @var string $subject @var string $body @var string $footer */
$site = App\Services\Settings::siteName();
$url = App\Core\Url::abs('/');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= e($subject) ?></title>
</head>
<body style="margin:0;padding:0;background:#fff6e8;color:#1c1233;font-family:'Bricolage Grotesque',Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff6e8">
  <tr><td align="center" style="padding:28px 14px">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px">
      <tr><td style="padding:0 0 18px">
        <a href="<?= e($url) ?>" style="text-decoration:none;color:#1c1233;font-weight:800;font-size:20px;letter-spacing:-0.02em">
          <span style="display:inline-block;width:34px;height:34px;line-height:34px;text-align:center;border-radius:50%;background:#ff4f3a;border:2px solid #1c1233;color:#fff6e8;font-weight:800;font-size:17px;vertical-align:middle">A</span>
          <span style="vertical-align:middle">&nbsp;animateur<em style="font-family:Georgia,'Instrument Serif',serif;font-weight:400;color:#ff4f3a">pour</em>votresoirée</span>
        </a>
      </td></tr>
      <tr><td style="background:#ffffff;border:2px solid #1c1233;border-radius:22px;padding:30px 28px;box-shadow:6px 6px 0 #1c1233;font-size:16px;line-height:1.55;color:#1c1233">
        <?= $body ?>
        <p style="margin:26px 0 0;color:#4a3f5e"><?= e(App\Services\Settings::get('mailing.signature', "L'équipe " . $site)) ?></p>
      </td></tr>
      <tr><td style="padding:18px 6px 0;font-size:12px;line-height:1.5;color:#6b5f80;text-align:center">
        <?= $footer ?>
        <p style="margin:8px 0 0"><?= e($site) ?> · <?= e(App\Services\Settings::get('site.baseline')) ?><br><a href="<?= e($url) ?>" style="color:#6b5f80"><?= e(App\Core\Str::domain($url) ?: $url) ?></a></p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
