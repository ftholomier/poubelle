<?php
/** @var array $c  @var array $s */
$bkEnabled = !empty($s['booking']['enabled']);
$days = $bkEnabled ? LD\Booking::days() : [];
$modes = array_values(array_filter($s['booking']['modes'] ?? ['En visio', 'En vrai']));
$b = $c['booking'];
$f = $c['form'];
$cb = $c['callback'];
$phoneSet = trim((string) ($s['contact']['phone'] ?? '')) !== '';
$smsOn = LD\Sms::enabled();
$bkCfg = ['days' => $days, 'modes' => $modes, 'choose' => $b['choose'], 'book' => $b['book'], 'doneText' => $b['done_text']];
?>
<section class="rdv-hero" aria-labelledby="rdv-title">
  <div class="dots dots--rdv" aria-hidden="true"></div>
  <div class="rdv-hero__in">
    <span class="pill-r"><?= t($c['hero']['tag']) ?></span>
    <h1 class="rdv-title" id="rdv-title"><?= t($c['hero']['title']) ?> <span class="grad-text"><?= t($c['hero']['title_accent']) ?></span></h1>
    <p class="rdv-p"><?= t($c['hero']['text']) ?></p>
    <div class="reassure"><?php foreach ($c['hero']['reassure'] as $r): ?><span><?= tick(18, 9) ?><?= t($r) ?></span><?php endforeach; ?></div>
  </div>
</section>

<section class="rdv-main">
  <div class="diag" data-diag="<?= json_attr($c['diag']) ?>" hidden>
    <div class="diag__head"><span class="diag__k"><?= t($c['diag']['kicker']) ?></span><span class="diag__t" data-diag-title></span></div>
  </div>

  <div class="rdv-grid">
    <div class="bk" id="reserver">
      <div class="bk__glow" aria-hidden="true"></div>
      <div class="bk__head"><span class="bk__k"><?= t($b['kicker']) ?></span><h2 class="bk__t"><?= t($b['title']) ?></h2></div>

      <div class="bk__done" data-bk-done hidden>
        <span class="bk__done-t"><?= t($b['done_title']) ?></span>
        <span class="bk__done-p" data-bk-label></span>
        <button type="button" class="bk__ics" data-bk-ics><?= t($b['ics']) ?></button>
        <?php if ($smsOn): ?><span class="bk__smsok" data-bk-smsok hidden><?= t($b['sms_done']) ?></span><?php endif; ?>
        <button type="button" class="bk__change" data-bk-change><?= t($b['change']) ?></button>
      </div>

      <?php if ($days): ?>
      <form class="bk__form" data-booking="<?= json_attr($bkCfg) ?>" novalidate>
        <div class="seg" role="radiogroup" aria-label="Format du rendez-vous">
          <?php foreach ($modes as $i => $m): ?><button type="button" role="radio" aria-checked="<?= $i === 0 ? 'true' : 'false' ?>"<?= $i === 0 ? ' class="is-on"' : '' ?> data-mode="<?= $i ?>"><?= e($m) ?></button><?php endforeach; ?>
        </div>
        <div class="bk__days" role="radiogroup" aria-label="Jour">
          <?php foreach ($days as $i => $d): ?><button type="button" role="radio" aria-checked="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="<?= e($d['label']) ?>"<?= $i === 0 ? ' class="is-on"' : '' ?> data-day="<?= $i ?>"><span><?= e($d['wd']) ?></span><b><?= (int) $d['n'] ?></b></button><?php endforeach; ?>
        </div>
        <div class="bk__slots" role="radiogroup" aria-label="Créneau" data-bk-slots>
          <?php foreach ($days[0]['slots'] as $j => $sl): ?><button type="button" role="radio" aria-checked="false" data-slot="<?= $j ?>"<?= $sl['free'] ? '' : ' disabled' ?>><?= e($sl['t']) ?></button><?php endforeach; ?>
        </div>
        <div class="bk__who" data-bk-who hidden>
          <label class="sr-only" for="bk-name"><?= t($b['name_label']) ?></label>
          <input id="bk-name" name="name" maxlength="80" placeholder="<?= e($b['name_label']) ?>" autocomplete="given-name" required>
          <label class="sr-only" for="bk-email"><?= t($b['email_label']) ?></label>
          <input id="bk-email" name="email" type="email" maxlength="160" placeholder="<?= e($b['email_label']) ?>" autocomplete="email" required>
          <label class="sr-only" for="bk-phone"><?= t($b['phone_label']) ?></label>
          <input id="bk-phone" name="phone" type="tel" maxlength="30" placeholder="<?= e($b['phone_label']) ?>" autocomplete="tel">
          <input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
        </div>
        <?php if ($smsOn): ?><button type="button" class="bk__sms" data-bk-sms aria-pressed="true"><span class="bk__box" aria-hidden="true">✓</span><?= t($b['sms_label']) ?></button><?php endif; ?>
        <p class="bk__err" data-bk-err role="alert" hidden></p>
        <button type="submit" class="bk__go" data-bk-go disabled><?= t($b['choose']) ?></button>
        <p class="bk__note"><?= t($b['note']) ?></p>
      </form>
      <?php else: ?>
        <p class="bk__none"><?= t($b['none']) ?></p>
      <?php endif; ?>
    </div>

    <div class="ct" id="formulaire">
      <div class="ct__head"><span class="ct__k"><?= t($f['kicker']) ?></span><h2 class="ct__t"><?= t($f['title']) ?></h2></div>
      <div class="ct__done" data-ct-done hidden><span><?= t($f['done_title']) ?></span><span><?= t($f['done_text']) ?></span></div>
      <form class="ct__form" data-contact novalidate>
        <div class="ag" style="--min:180px;--gap:12px">
          <label class="fld">Ton prénom<input name="name" maxlength="80" autocomplete="given-name" required></label>
          <label class="fld"><?= t($f['phone']) ?><input name="phone" type="tel" maxlength="30" autocomplete="tel"></label>
        </div>
        <label class="fld"><?= t($f['email']) ?><input name="email" type="email" maxlength="160" autocomplete="email"></label>
        <fieldset class="ct__int">
          <legend><?= t($f['interests_label']) ?></legend>
          <div class="ct__chips"><?php foreach (LD\Solutions::interests((array) $f['interests']) as $it): ?><button type="button" aria-pressed="false" data-interest="<?= e($it) ?>"><?= t($it) ?></button><?php endforeach; ?></div>
        </fieldset>
        <label class="fld"><?= t($f['time']) ?><textarea name="time_text" rows="3" maxlength="2000" data-ct-time></textarea></label>
        <label class="fld"><?= t($f['why']) ?><textarea name="why" rows="3" maxlength="3000"></textarea></label>
        <input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
        <p class="ct__err" data-ct-err role="alert" hidden></p>
        <button type="submit" class="ct__go"><?= t($f['submit']) ?></button>
        <?php if (trim((string) ($f['consent'] ?? '')) !== ''): ?><p class="ct__consent"><?= t($f['consent']) ?> <a href="/mentions-legales#donnees">En savoir plus</a></p><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="cb" id="rappel">
    <div class="cb__txt"><span class="cb__k"><?= t($cb['kicker']) ?></span><h2 class="cb__t"><?= t($cb['title']) ?></h2><span class="cb__p"><?= t($cb['text']) ?></span></div>
    <div class="cb__done" data-cb-done hidden data-tpl="<?= e($cb['done']) ?>"></div>
    <form class="cb__form" data-callback="<?= json_attr($cb['moments_label']) ?>" novalidate>
      <label class="sr-only" for="cb-phone">Ton numéro</label>
      <input id="cb-phone" name="phone" type="tel" maxlength="30" placeholder="<?= e($cb['placeholder']) ?>" autocomplete="tel" required>
      <div class="cb__moments" role="radiogroup" aria-label="Moment du rappel">
        <?php foreach ($cb['moments'] as $i => $m): ?><button type="button" role="radio" aria-checked="<?= $i === 0 ? 'true' : 'false' ?>"<?= $i === 0 ? ' class="is-on"' : '' ?> data-moment="<?= $i ?>"><?= t($m) ?></button><?php endforeach; ?>
      </div>
      <input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <button type="submit" class="cb__go"><?= t($cb['submit']) ?></button>
      <p class="cb__err" data-cb-err role="alert" hidden></p>
    </form>
  </div>

  <div class="contacts ag" style="--min:300px">
    <a class="contact" href="<?= e(tel_href()) ?>">
      <span class="contact__ic contact__ic--red"><?= icon('phone', 22, '#fff') ?></span>
      <span class="contact__t"><span><?= t($c['contacts']['phone_label']) ?></span><b class="accent"><?= e($phoneSet ? $s['contact']['phone'] : $c['contacts']['phone_todo']) ?></b></span>
    </a>
    <a class="contact"<?= link_attrs(linkedin_url()) ?>>
      <span class="contact__ic contact__ic--ink">in</span>
      <span class="contact__t"><span><?= t($c['contacts']['linkedin_label']) ?></span><b><?= t($c['contacts']['linkedin']) ?></b></span>
    </a>
    <button type="button" class="contact contact--dark" data-open-bot>
      <span class="contact__ic contact__ic--glass"><?= icon('spark1', 24, '#ffc9c2', 1.8) ?></span>
      <span class="contact__t"><span><?= t($c['contacts']['bot_label']) ?></span><b><?= t($c['contacts']['bot']) ?></b></span>
    </button>
  </div>
</section>
