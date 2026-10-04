<?php
/** Étiquette « À vérifier » (aperçu du back-office seulement). Variables : $item */
if (\App\Vitrine\Site::$preview && !empty($item['a_verifier'])): ?>
<span class="vverify" title="<?= e((string) ($item['a_verifier_note'] ?? 'Contenu d’exemple : invisible du public tant qu’il n’est pas vérifié.')) ?>">À vérifier</span>
<?php endif;
