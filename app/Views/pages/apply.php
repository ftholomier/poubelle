<?php
$a = (array) content('apply');
$steps = (array) ($a['steps'] ?? []);
$stats = (array) content('stats');
?>
<section class="section" style="padding-top:clamp(140px,18vh,190px)">
  <span class="glow glow--red" style="width:680px;height:680px;top:-280px;right:-200px;opacity:.35" aria-hidden="true"></span>
  <span class="glow glow--amber" style="width:420px;height:420px;bottom:0;left:-200px;opacity:.25" aria-hidden="true"></span>

  <div class="container">
    <div class="funnel">
      <!-- Colonne de réassurance -->
      <aside class="funnel-aside">
        <div data-reveal="left">
          <span class="chip chip--mint"><span class="dot"></span> Secteurs encore ouverts</span>
          <h1 class="h1" style="margin:20px 0 16px;font-size:clamp(2rem,4vw,3rem)"><?= e($a['title'] ?? '') ?></h1>
          <p class="lead" style="font-size:1rem"><?= e($a['lead'] ?? '') ?></p>
        </div>

        <div class="funnel-steps" role="list">
          <?php foreach ($steps as $i => $s): ?>
            <div class="funnel-step<?= $i === 0 ? ' is-active' : '' ?>" role="listitem">
              <span class="funnel-step__n"><?= $i + 1 ?></span>
              <div>
                <h4><?= e($s['title'] ?? '') ?></h4>
                <p><?= e($s['hint'] ?? '') ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

      </aside>

      <div class="funnel-proof card">
        <div class="cluster" style="gap:10px;margin-bottom:14px">
          <span class="avatars" aria-hidden="true"><span>KL</span><span>CY</span><span>DJ</span><span class="is-more">60+</span></span>
          <span class="stars" aria-hidden="true"><?= str_repeat(icon('star'), 5) ?></span>
        </div>
        <p class="small muted">
          Rejoignez les <?= e((string) ($stats[1]['value'] ?? 60)) ?>+ agents commerciaux du réseau.
          Réponse garantie sous <?= e(settings('funnel.response_delay', '48 h')) ?>, sans engagement de votre part.
        </p>
        <p class="small muted" style="margin-top:12px">
          Une question avant de candidater ?
          <a class="link-arrow" href="tel:<?= e(settings('company.phone_link')) ?>"><?= e(settings('company.phone')) ?></a>
        </p>
      </div>

      <!-- Tunnel -->
      <div class="funnel-card" data-reveal="right">
        <div class="funnel-progress" role="progressbar" aria-label="Progression" aria-valuemin="0" aria-valuemax="100"><i></i></div>

        <form id="apply-form" method="post" action="<?= e(url('api/apply')) ?>" enctype="multipart/form-data" novalidate>
          <?= Csrf::field() ?>
          <input type="hidden" name="draft_id" value="">
          <input class="honey" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

          <!-- Étape 1 : situation -->
          <section class="funnel-pane">
            <div class="funnel-pane__head">
              <h2 class="h3"><?= e($steps[0]['title'] ?? 'Votre situation') ?></h2>
              <p>Aucune expérience immobilière n’est exigée. Dites-nous simplement d’où vous partez.</p>
            </div>

            <fieldset style="border:0;padding:0" class="field">
              <legend class="sr-only">Votre situation actuelle</legend>
              <label>Aujourd’hui, vous êtes… <span class="req">*</span></label>
              <div class="choices">
                <?php foreach ((array) ($a['situations'] ?? []) as $i => $s): ?>
                  <label class="choice">
                    <input type="radio" name="situation" value="<?= e($s) ?>" data-required<?= $i === 0 ? '' : '' ?>>
                    <span><?= e($s) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>

            <div class="field">
              <label for="f-experience">Une expérience de la vente ou de la relation client ?</label>
              <select class="select" id="f-experience" name="experience">
                <option value="">— Choisissez —</option>
                <option>Aucune, mais très motivé(e)</option>
                <option>Moins de 2 ans</option>
                <option>2 à 5 ans</option>
                <option>Plus de 5 ans</option>
                <option>Déjà agent immobilier</option>
              </select>
            </div>

            <div class="funnel-nav">
              <span class="small muted"><?= icon('lock') ?> Données confidentielles</span>
              <button class="btn btn--magnet" type="button" data-next>Continuer <?= icon('arrow') ?></button>
            </div>
          </section>

          <!-- Étape 2 : projet -->
          <section class="funnel-pane" hidden>
            <div class="funnel-pane__head">
              <h2 class="h3"><?= e($steps[1]['title'] ?? 'Votre projet') ?></h2>
              <p>Nous vérifions la disponibilité de votre secteur avant l’entretien.</p>
            </div>

            <div class="field">
              <label for="f-area">Secteur géographique souhaité <span class="req">*</span></label>
              <input class="input" id="f-area" name="area" type="text" data-required
                     placeholder="Belfort, Besançon, Montbéliard, Morteau…" autocomplete="address-level2">
            </div>

            <fieldset style="border:0;padding:0" class="field">
              <legend class="sr-only">Disponibilité</legend>
              <label>Quand souhaitez-vous démarrer ? <span class="req">*</span></label>
              <div class="choices">
                <?php foreach ((array) ($a['availabilities'] ?? []) as $av): ?>
                  <label class="choice">
                    <input type="radio" name="availability" value="<?= e($av) ?>" data-required>
                    <span><?= e($av) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>

            <div class="field">
              <label for="f-goal">Votre objectif en une phrase</label>
              <textarea class="textarea" id="f-goal" name="goal" rows="3"
                        placeholder="Ex. : doubler mes revenus en travaillant sur mon secteur, tout en gardant mon indépendance."></textarea>
            </div>

            <div class="funnel-nav">
              <button class="funnel-back" type="button" data-prev>← Retour</button>
              <button class="btn btn--magnet" type="button" data-next>Continuer <?= icon('arrow') ?></button>
            </div>
          </section>

          <!-- Étape 3 : coordonnées -->
          <section class="funnel-pane" hidden>
            <div class="funnel-pane__head">
              <h2 class="h3"><?= e($steps[2]['title'] ?? 'Vos coordonnées') ?></h2>
              <p>Pour vous rappeler et fixer votre rendez-vous stratégique.</p>
            </div>

            <div class="field">
              <label for="f-name">Nom et prénom <span class="req">*</span></label>
              <input class="input" id="f-name" name="name" type="text" data-required autocomplete="name" placeholder="Camille Martin">
            </div>

            <div class="two-col">
              <div class="field">
                <label for="f-email">E-mail <span class="req">*</span></label>
                <input class="input" id="f-email" name="email" type="email" data-required autocomplete="email" placeholder="camille@exemple.fr">
              </div>
              <div class="field">
                <label for="f-phone">Téléphone <span class="req">*</span></label>
                <input class="input" id="f-phone" name="phone" type="tel" data-required autocomplete="tel" placeholder="06 12 34 56 78">
              </div>
            </div>

            <div class="field">
              <label for="f-source">Vous nous avez connus par</label>
              <select class="select" id="f-source" name="source">
                <option value="">— Veuillez choisir une option —</option>
                <?php foreach ((array) ($a['sources'] ?? []) as $s): ?>
                  <option><?= e($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <?php if (settings('funnel.cv_upload', true)): ?>
            <div class="field">
              <label>Votre CV <span class="muted small">(facultatif)</span></label>
              <div class="filedrop" tabindex="0" role="button" aria-label="Ajouter un CV">
                <?= icon('file') ?>
                <b data-file-name>Glissez votre CV ici ou cliquez pour le choisir</b>
                <small>PDF, DOC ou DOCX — 5 Mo maximum</small>
                <input type="file" name="cv" accept=".pdf,.doc,.docx">
              </div>
            </div>
            <?php endif; ?>

            <div class="funnel-nav">
              <button class="funnel-back" type="button" data-prev>← Retour</button>
              <button class="btn btn--magnet" type="button" data-next>Vérifier ma candidature <?= icon('arrow') ?></button>
            </div>
          </section>

          <!-- Étape 4 : confirmation -->
          <section class="funnel-pane" hidden>
            <div class="funnel-pane__head">
              <h2 class="h3"><?= e($steps[3]['title'] ?? 'Confirmation') ?></h2>
              <p>Un dernier regard, puis nous vous recontactons sous <?= e(settings('funnel.response_delay', '48 h')) ?>.</p>
            </div>

            <dl class="recap">
              <div><dt>Nom</dt><dd id="recap-name">—</dd></div>
              <div><dt>E-mail</dt><dd id="recap-email">—</dd></div>
              <div><dt>Téléphone</dt><dd id="recap-phone">—</dd></div>
              <div><dt>Secteur</dt><dd id="recap-area">—</dd></div>
              <div><dt>Situation</dt><dd id="recap-situation">—</dd></div>
              <div><dt>Disponibilité</dt><dd id="recap-availability">—</dd></div>
            </dl>

            <div class="field">
              <label for="f-message">Un mot pour l’équipe ? <span class="muted small">(facultatif)</span></label>
              <textarea class="textarea" id="f-message" name="message" rows="3" placeholder="Ce que vous aimeriez aborder pendant l’entretien…"></textarea>
            </div>

            <div class="field">
              <label class="checkbox">
                <input type="checkbox" name="consent" value="1" data-required>
                <i aria-hidden="true"></i>
                <span>J’accepte que Suisse Immo traite mes données pour étudier ma candidature et me recontacter, conformément à la
                  <a href="<?= e(url('politique-de-confidentialite')) ?>" target="_blank" rel="noopener">politique de confidentialité</a>. <span class="req">*</span></span>
              </label>
            </div>

            <div class="funnel-nav">
              <button class="funnel-back" type="button" data-prev>← Retour</button>
              <button class="btn btn--lg btn--magnet" type="submit" data-cta="apply-submit">Envoyer ma candidature <?= icon('arrow') ?></button>
            </div>
            <p class="small muted" style="margin-top:6px"><?= icon('shield-check') ?> Vos informations ne sont jamais transmises à des tiers.</p>
          </section>
        </form>
      </div>
    </div>
  </div>
</section>
