<?php
// Suppressions : tout ce que l'agent crée ou reçoit peut être retiré (avec confirmation dans l'interface).
// Déjà ailleurs : dossier complet et audio (DELETE visit / audio), acquéreur, rendez-vous, photo, offre, compte, logo.
// Ici : tâche, document du vendeur, secteur de prospection, contact reçu, visite d'acquéreur.
// Chaque suppression dans un dossier est notée au journal du dossier.

route('DELETE tache', function ($id) {
    $me = require_user();
    collection_trouver($me, 'taches', $id) ?? fail(404, 'Tâche introuvable.');
    collection_supprimer($me, 'taches', $id);
    send_json(['ok' => true]);
});

/** Document du vendeur déposé par erreur : fichier effacé, la pièce redevient « à recevoir » s'il n'en reste aucun. */
route('POST piece_supprimer', function ($id) {
    $me = require_user();
    $nom = (string) (json_input()['fichier'] ?? '');
    if (!preg_match('/^[a-z0-9._-]{5,90}$/', $nom)) fail(400, 'Document invalide.');
    $trouve = false;
    update_visit($me, $id, function (array $v) use ($nom, &$trouve) {
        foreach ($v['pieces'] ?? [] as $i => $p) {
            $reste = array_values(array_filter($p['fichiers'] ?? [], fn ($f) => $f['nom'] !== $nom));
            if (count($reste) === count($p['fichiers'] ?? [])) continue;
            $trouve = true;
            $v['pieces'][$i]['fichiers'] = $reste;
            if (!$reste) $v['pieces'][$i]['statut'] = 'manquante';
            journal_ajout($v, 'piece', "Document supprimé : {$p['label']}.");
        }
        $v['pieces'] = array_values(array_filter($v['pieces'] ?? [], fn ($p) => !str_starts_with($p['cle'], 'autre-') || !empty($p['fichiers'])));
        return $v;
    });
    if (!$trouve) fail(404, 'Document introuvable.');
    @unlink(visit_dir($me, $id) . "/pieces/$nom");
    send_json(['ok' => true]);
});

/** Secteur de prospection retiré, avec les adresses ciblées qui n'ont pas encore été contactées. */
route('DELETE secteur', function () {
    $me = require_user();
    $code = (string) ($_GET['citycode'] ?? '');
    if (!preg_match('/^\w{5}$/', $code)) fail(400, 'Commune invalide.');
    collection_maj($me, 'prospection', function (array $p) use ($code) {
        unset($p['secteurs'][$code]);
        $p['cibles'] = array_values(array_filter($p['cibles'] ?? [], fn ($c) => ($c['citycode'] ?? '') !== $code || !in_array($c['statut'] ?? 'nouveau', ['nouveau', 'ecarte'], true)));
        return $p;
    });
    send_json(['ok' => true]);
});

/** Contact reçu par la page du bien (doublon, message indésirable). La fiche acquéreur éventuelle est conservée. */
route('POST contact_supprimer', function ($id) {
    $me = require_user();
    $date = (string) (json_input()['date'] ?? '');
    $v = update_visit($me, $id, function (array $v) use ($date) {
        $avant = count($v['contacts'] ?? []);
        $v['contacts'] = array_values(array_filter($v['contacts'] ?? [], fn ($c) => ($c['date'] ?? '') !== $date));
        if (count($v['contacts']) < $avant) journal_ajout($v, 'contact', 'Contact reçu supprimé.');
        return $v;
    });
    send_json(vue_dossier($v));
});

/** Visite d'acquéreur annulée ou saisie par erreur : retirée du dossier et de l'agenda. */
route('POST visite_acq_supprimer', function ($id) {
    $me = require_user();
    $va = (string) (json_input()['visite'] ?? '');
    $rdv = null;
    $v = update_visit($me, $id, function (array $v) use ($va, &$rdv) {
        foreach ($v['visites_acq'] ?? [] as $i => $x) {
            if ($x['id'] !== $va) continue;
            $rdv = $x['rdv'] ?? null;
            unset($v['visites_acq'][$i]);
            if (($v['signatures']["bon:$va"]['statut'] ?? '') === 'en_attente') $v['signatures']["bon:$va"]['statut'] = 'annule';
            journal_ajout($v, 'visite', "Visite de {$x['nom']} supprimée.");
        }
        $v['visites_acq'] = array_values($v['visites_acq'] ?? []);
        return $v;
    });
    if ($rdv && collection_trouver($me, 'agenda', (string) $rdv)) collection_supprimer($me, 'agenda', (string) $rdv);
    send_json(vue_dossier($v));
});
