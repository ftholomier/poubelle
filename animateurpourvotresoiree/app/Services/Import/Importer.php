<?php
declare(strict_types=1);

namespace App\Services\Import;

use App\Core\Crypto;
use App\Core\Env;
use App\Core\Fs;
use App\Core\Image;
use App\Core\Logger;
use App\Core\Sanitizer;
use App\Core\Str;
use App\Services\Blog;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Pages;
use App\Services\Pros;
use App\Services\Store;

/**
 * Migration de l'ancienne base MySQL (dump .sql) vers les fichiers JSON.
 * Chaque étape est rejouable et découpée en lots pour éviter les dépassements de délai
 * sur un hébergement mutualisé. Utilisable depuis le back-office ou en ligne de commande.
 */
final class Importer
{
    public const DIR = STORAGE_PATH . '/import';

    /** Étapes dans l'ordre : clé => [libellé, taille de lot] */
    public const STEPS = [
        'parse' => ['Lecture du dump SQL', 0],
        'reference' => ['Référentiels, archives, statistiques, pages et blog', 0],
        'pros' => ['Fiches des pros (mots de passe chiffrés)', 250],
        'requests' => ['Demandes de devis', 0],
        'messages' => ['Messages envoyés aux pros', 12000],
        'prospects' => ['Liste des emails prospects', 0],
        'finalize' => ['Index, compteurs et caches', 0],
    ];

    private static ?TextRepair $repair = null;

    public static function status(): array
    {
        return Fs::readJson(self::DIR . '/status.json', ['steps' => [], 'dump' => null]);
    }

    private static function saveStatus(array $s): void
    {
        Fs::writeJson(self::DIR . '/status.json', $s, true);
    }

    /** Dump le plus récent déposé dans storage/import/. */
    public static function findDump(): ?string
    {
        $files = glob(self::DIR . '/*.sql') ?: [];
        usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
        return $files[0] ?? null;
    }

    /**
     * Exécute une étape (ou un lot d'étape).
     * @return array{done:bool, offset:int, total:int, message:string}
     */
    public static function run(string $step, int $offset = 0): array
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');
        if (!isset(self::STEPS[$step])) {
            throw new \InvalidArgumentException('Étape inconnue');
        }
        $res = match ($step) {
            'parse' => self::parse(),
            'reference' => self::reference(),
            'pros' => self::pros($offset, self::STEPS['pros'][1]),
            'requests' => self::requests(),
            'messages' => self::messages($offset, self::STEPS['messages'][1]),
            'prospects' => self::prospects(),
            'finalize' => self::finalize(),
        };
        $status = self::status();
        $status['steps'][$step] = ['done' => $res['done'], 'offset' => $res['offset'], 'total' => $res['total'], 'message' => $res['message'], 'at' => date('c')];
        self::saveStatus($status);
        Logger::log('import', $step . ' : ' . $res['message'], ['offset' => $res['offset'], 'total' => $res['total']]);
        return $res;
    }

    // ------------------------------------------------------------------ étapes

    private static function parse(): array
    {
        $dump = self::findDump();
        if (!$dump) {
            throw new \RuntimeException('Aucun fichier .sql dans storage/import/. Déposez le dump de l\'ancienne base.');
        }
        $dir = self::DIR . '/tables';
        Fs::rmrf($dir);
        Fs::ensureDir($dir);
        $handles = [];
        $repair = new TextRepair();
        $repair->seed(TextRepair::baseWords());
        $learn = ['adherent' => ['prestation', 'resume', 'accroche', 'mot1', 'mot2', 'mot3', 'mot4', 'societe', 'ville'],
            'offre' => ['demande', 'lieu', 'ville'], 'adherentcontact' => ['contact_demande', 'contact_lieu'],
            'adherentfiche' => ['module5', 'ref_titre', 'ref_description'], 'villes' => ['nom'], 'info' => ['texte']];
        $counts = SqlDump::each($dump, static function (string $table, array $row) use (&$handles, $dir, $repair, $learn): void {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                return;
            }
            $handles[$table] ??= fopen($dir . '/' . $table . '.jsonl', 'w');
            fwrite($handles[$table], json_encode($row, Fs::JSON_FLAGS) . "\n");
            foreach ($learn[$table] ?? [] as $col) {
                if (isset($row[$col]) && is_string($row[$col]) && !str_contains($row[$col], '?')) {
                    $repair->learn(html_entity_decode($row[$col], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
            }
        });
        foreach ($handles as $h) {
            fclose($h);
        }
        // Articles du blog (textes sains) : enrichissent aussi le dictionnaire.
        foreach (Blog::seed() as $a) {
            $repair->learn(strip_tags((string) $a['body']));
        }
        Fs::writeJson(self::DIR . '/dictionary.json', $repair->export());
        $status = self::status();
        $status['dump'] = ['file' => basename($dump), 'size' => filesize($dump), 'counts' => $counts, 'at' => date('c')];
        $status['steps'] = [];
        self::saveStatus($status);
        $total = array_sum($counts);
        return ['done' => true, 'offset' => $total, 'total' => $total, 'message' => count($counts) . ' tables, ' . number_format($total, 0, ',', ' ') . ' lignes lues'];
    }

    private static function reference(): array
    {
        // Catégories et occasions (si jamais personnalisées, on ne les écrase pas)
        if (!Store::doc('categories', Categories::defaults())->exists()) {
            Store::doc('categories', Categories::defaults())->save(Categories::defaults());
        }
        if (!Store::doc('occasions', Categories::occasionDefaults())->exists()) {
            Store::doc('occasions', Categories::occasionDefaults())->save(Categories::occasionDefaults());
        }
        Categories::reset();

        // Archives : toutes les tables historiques en lecture seule
        $archived = 0;
        $archiveTables = ['adherentemail', 'animateur_facture', 'animateur_factureemail', 'animation', 'compta', 'compteur_visite', 'contactmois', 'dep', 'email_com', 'email_fr', 'email_fr_to', 'facture', 'fichecontact', 'frais', 'google', 'info', 'infoapvs', 'ixmail_contact', 'ixmail_popsetting', 'ixmail_signature', 'ixmail_siteusers', 'liste_emails', 'mailing', 'memo', 'offremois', 'parrain', 'profil', 'regions', 'statvisiteur', 'tarif', 'test', 'test_poweremailer', 'user', 'villes', 'adherentfiche'];
        Fs::ensureDir(STORAGE_PATH . '/data/archives');
        foreach ($archiveTables as $t) {
            $rows = [];
            foreach (self::rows($t) as $row) {
                foreach ($row as $k => $v) {
                    if (preg_match('/^(pass|passwd|password|pwd)$/i', (string) $k)) {
                        $row[$k] = '***';
                    }
                    if (is_string($v) && !mb_check_encoding($v, 'UTF-8')) {
                        $row[$k] = mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
                    }
                }
                $rows[] = $row;
            }
            if ($rows) {
                Fs::writeJson(STORAGE_PATH . '/data/archives/' . $t . '.json', $rows);
                $archived++;
            }
        }

        // Mémo de l'administrateur
        foreach (self::rows('memo') as $row) {
            Store::doc('memo')->save(['html' => Sanitizer::html((string) $row['memo']), 'updated_at' => date('c'), 'source' => 'ancien site']);
        }

        // Historique mensuel (demandes de devis et contacts depuis 2002)
        $hist = ['requests' => [], 'messages' => []];
        foreach (self::rows('offremois') as $r) {
            if ((int) $r['aa'] > 1990) {
                $hist['requests'][sprintf('%04d-%02d', $r['aa'], $r['mm'])] = (int) $r['nboffre'];
            }
        }
        foreach (self::rows('contactmois') as $r) {
            if ((int) $r['aa'] > 1990) {
                $hist['messages'][sprintf('%04d-%02d', $r['aa'], $r['mm'])] = (int) $r['nbcontact'];
            }
        }
        ksort($hist['requests']);
        ksort($hist['messages']);
        Fs::writeJson(STORAGE_PATH . '/data/stats/legacy_monthly.json', $hist);

        // Pages institutionnelles et articles du blog
        $pages = Pages::seedIfEmpty();
        $articles = Blog::seedIfEmpty();

        return ['done' => true, 'offset' => 1, 'total' => 1, 'message' => "$archived tables archivées, historique mensuel, $pages pages, $articles articles"];
    }

    private static function pros(int $offset, int $limit): array
    {
        $col = Store::pros();
        $rows = iterator_to_array(self::rows('adherent'), false);
        usort($rows, static fn ($a, $b) => (int) $a['id'] <=> (int) $b['id']);
        $total = count($rows);
        if ($offset === 0) {
            // Les photos déjà rapatriées sont conservées si l'on relance l'import.
            $keep = [];
            foreach (glob($col->dir() . '/rec/*/*.json') ?: [] as $f) {
                $old = Fs::readJson($f, []);
                if (!empty($old['photos'])) {
                    $keep[(int) $old['id']] = ['photos' => $old['photos'], 'done' => !empty($old['legacy']['photos_imported'])];
                }
            }
            Fs::writeJson(self::DIR . '/photos_keep.json', $keep);
            $col->truncate();
            Fs::writeJson(self::DIR . '/slugs.json', []);
        }
        $keepPhotos = Fs::readJson(self::DIR . '/photos_keep.json', []);
        $fiches = [];
        foreach (self::rows('adherentfiche') as $f) {
            $fiches[(int) $f['idadherent']] = $f;
        }
        $slugs = Fs::readJson(self::DIR . '/slugs.json', []);
        $batch = array_slice($rows, $offset, $limit);
        // Écriture en lot : les index complets sont reconstruits à l'étape « finalize ».
        $col->beginBulk();
        foreach ($batch as $r) {
            $p = self::mapPro($r, $fiches[(int) $r['id']] ?? null);
            $base = Str::slug(Pros::displayName($p), 60) ?: 'pro';
            $slug = $base;
            $i = 2;
            while (isset($slugs[$slug])) {
                $slug = $base . '-' . $i++;
            }
            $slugs[$slug] = (int) $p['id'];
            $p['slug'] = $slug;
            if (isset($keepPhotos[(string) $p['id']])) {
                $p['photos'] = $keepPhotos[(string) $p['id']]['photos'];
                $p['legacy']['photos_imported'] = (bool) $keepPhotos[(string) $p['id']]['done'];
            }
            $col->bulkPut($p);
        }
        $col->endBulk();
        Fs::writeJson(self::DIR . '/slugs.json', $slugs);
        $next = $offset + count($batch);
        if ($next >= $total) {
            $col->rebuild();
        }
        return ['done' => $next >= $total, 'offset' => $next, 'total' => $total, 'message' => "$next / $total fiches importées"];
    }

    public static function mapPro(array $r, ?array $fiche): array
    {
        $rp = self::repair();
        $t = static fn ($v) => $rp->fix(Str::clean(html_entity_decode((string) ($v ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $line = static fn ($v) => Str::clean($t($v), false);

        $rawCp = (string) $r['code_postal'];
        $rawCity = (string) $r['ville'];
        // champs inversés dans l'ancienne base (ville = « 02110 », code postal = « Molain »)
        if (!preg_match('/\d/', $rawCp) && preg_match('/^\s*\d{5}\s*$/', $rawCity)) {
            [$rawCp, $rawCity] = [$rawCity, $rawCp];
        }
        $postcode = preg_replace('/\D/', '', $rawCp) ?? '';
        if ($postcode === '' && preg_match('/\b(\d{5})\b/', $rawCity, $mm)) {
            $postcode = $mm[1];
        }
        $postcode = strlen($postcode) === 4 ? '0' . $postcode : substr($postcode, 0, 5);
        $city = trim(preg_replace('/\b\d{5}\b/', '', $line($rawCity)) ?? '');
        $site = trim((string) $r['site']);
        $website = Str::url($site);
        $socials = [];
        foreach (['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin'] as $net) {
            if ($website !== '' && str_contains(strtolower($website), $net . '.com')) {
                $socials[$net] = $website;
                $website = '';
            }
        }
        $tags = [];
        foreach (['mot1', 'mot2', 'mot3', 'mot4'] as $k) {
            $tag = $line($r[$k] ?? '');
            if ($tag === '') {
                continue;
            }
            $letters = preg_replace('/[^\p{L}]/u', '', $tag) ?? '';
            if ($letters !== '' && $letters === mb_strtoupper($letters) && mb_strlen($letters) > 3) {
                $tag = Str::ucfirst(mb_strtolower($tag));
            }
            $tag = preg_replace('/\bdj\b/iu', 'DJ', $tag) ?? $tag;
            $tags[mb_strtolower($tag)] = mb_substr($tag, 0, 40);
        }
        $zones = [];
        for ($i = 1; $i <= 10; $i++) {
            $d = Geo::depCode((string) ($r['dep' . $i] ?? ''));
            if ($d !== '' && Geo::dep($d)) {
                $zones[] = $d;
            }
        }
        $descRaw = $t($r['prestation']);
        $description = Sanitizer::html(Str::sentenceCase($descRaw), ['headings' => true]);
        $tagline = Str::sentenceCase($line($r['resume']) ?: $line($r['accroche']));
        $company = $line($r['societe']);
        $first = Str::nameCase($line($r['prenom']));
        $last = Str::nameCase($line($r['nom']));
        if ($company !== '' && mb_strtolower($company) === mb_strtolower($last)) {
            $last = '';
        }
        // Raison sociale saisie sous forme d'adresse web : on préfère le nom, sinon on embellit le domaine.
        if (preg_match('#^(https?://)?(www\.)?([a-z0-9\-]+)\.[a-z]{2,6}(/.*)?$#i', $company, $dm)) {
            $company = trim($first . ' ' . $last) !== '' ? '' : Str::nameCase(str_replace('-', ' ', $dm[3]));
        }
        $display = $company !== '' ? ((preg_match('/^[\p{Lu}\s\d\'&\-.]+$/u', $company) && mb_strlen($company) > 4) || $company === mb_strtolower($company) ? Str::nameCase($company) : $company) : trim($first . ' ' . $last);
        $cats = Categories::classify([
            'tags' => implode(' ', $tags),
            'name' => $display,
            'tagline' => $tagline,
            'description' => Str::text($description),
        ]);
        if (!$cats) {
            $cats = ['animateur-soiree'];
        }
        $date = static fn ($d) => ($d && $d !== '0000-00-00' && strtotime((string) $d)) ? date('c', (int) strtotime((string) $d)) : null;
        $altTexts = array_values(array_filter([$line($r['text_photo1'] ?? ''), $line($r['text_photo2'] ?? ''), $line($r['text_photo3'] ?? '')]));
        $pass = (string) ($r['pass'] ?? '');
        $seo = [];
        if ($fiche) {
            $seo = array_filter([
                'title' => Str::limit($line($fiche['ref_titre'] ?? ''), 70, ''),
                'description' => Str::limit($line($fiche['ref_description'] ?? ''), 170, ''),
                'keywords' => Str::limit($line($fiche['ref_keyword'] ?? ''), 255, ''),
            ]);
        }
        $legacy = [
            'debut' => $r['debut'] ?? null, 'fin' => $r['fin'] ?? null, 'checkactif' => (int) ($r['checkactif'] ?? 0),
            'receptiondevis' => (int) ($r['receptiondevis'] ?? 0), 'dep_cible' => (int) ($r['dep_cible'] ?? 0),
            'offre_directe' => (int) ($r['offre_directe'] ?? 0), 'gratuit' => (int) ($r['gratuit'] ?? 0),
            'type_paiement' => (int) ($r['type_paiement'] ?? 0), 'somme' => (int) ($r['somme'] ?? 0),
            'idcompta' => (int) ($r['idcompta'] ?? 0), 'renouvellement' => (int) ($r['renouvellement'] ?? 0),
            'contrat' => (string) ($r['contrat'] ?? ''), 'en_regle' => (string) ($r['en_regle'] ?? ''),
            'urlsite' => (string) ($r['urlsite'] ?? ''), 'urldomaine' => (string) ($r['urldomaine'] ?? ''), 'urlemail' => (string) ($r['urlemail'] ?? ''),
            'parrain' => (string) ($r['parrain'] ?? ''), 'totalfilleul' => (int) ($r['totalfilleul'] ?? 0),
            'num_facture' => (string) ($r['num_facture'] ?? ''), 'site_original' => $site, 'photo_alts' => $altTexts,
        ];
        if ($fiche) {
            $modules = [];
            foreach ($fiche as $k => $v) {
                if (str_starts_with((string) $k, 'module') && is_string($v) && trim($v) !== '') {
                    $modules[$k] = $v;
                }
            }
            if ($modules) {
                $legacy['fiche_modules'] = $modules;
            }
            if (!empty($fiche['popup'])) {
                $legacy['popup'] = (string) $fiche['popup'];
            }
        }
        $p = [
            'id' => (int) $r['id'],
            'status' => (int) $r['actif'] === 1 ? 'active' : 'inactive',
            'display_name' => $display,
            'first_name' => $first,
            'last_name' => $last,
            'company' => $company,
            'email' => Str::email((string) $r['email']),
            'phone' => Str::phone((string) $r['telephone']),
            'address' => $line($r['adresse']),
            'postcode' => $postcode,
            'city' => $city !== '' ? Str::nameCase($city) : '',
            'website' => $website,
            'socials' => $socials,
            'videos' => [],
            'tagline' => Str::limit($tagline, 220, ''),
            'description' => $description,
            'tags' => array_values($tags),
            'categories' => $cats,
            'zones' => array_values(array_unique($zones)),
            'all_france' => (int) ($r['france_cible'] ?? 0) === 1,
            'accept_requests' => true,
            'price_from' => null,
            'photos' => [],
            'siret' => preg_replace('/\D/', '', (string) ($r['num_declar'] ?? '')) ?: '',
            'ape' => Str::clean((string) ($r['ape'] ?? ''), false),
            'guso' => Str::clean((string) ($r['guso'] ?? ''), false),
            'login' => trim((string) $r['login']),
            'password_hash' => $pass !== '' ? Crypto::hashPassword($pass) : '',
            'must_change_password' => true,
            'email_verified' => true,
            'seo' => $seo,
            'settings' => ['notify_requests' => true, 'notify_messages' => true, 'vacation' => false, 'newsletter' => true],
            'stats' => ['views' => 0, 'phone_reveals' => 0, 'website_clicks' => 0],
            'rating' => ['avg' => 0, 'count' => 0],
            'membership' => ['since' => $r['debut'] ?? null, 'until' => $r['fin'] ?? null],
            'source' => 'legacy',
            'legacy' => $legacy,
            'created_at' => $date($r['debut'] ?? null) ?? '2005-01-01T00:00:00+01:00',
            'updated_at' => date('c'),
            'last_login_at' => null,
        ];
        // Fiches actives mais vides dans l'ancienne base : mises de côté pour vérification.
        if ($p['status'] === 'active' && ($display === '' || Str::text($description) === '') && $p['email'] === '') {
            $p['status'] = 'suspended';
            $p['admin_notes'] = "Fiche active dans l'ancienne base mais vide (ni nom, ni email, ni description) : suspendue lors de l'import.";
        }
        return Pros::derive($p, null);
    }

    private static function requests(): array
    {
        $col = Store::requests();
        $col->truncate();
        $rows = iterator_to_array(self::rows('offre'), false);
        usort($rows, static fn ($a, $b) => (int) $a['id'] <=> (int) $b['id']);
        $dates = self::interpolateDates($rows, static function (array $r): ?string {
            if (!empty($r['date_jour']) && $r['date_jour'] !== '0000-00-00') {
                return (string) $r['date_jour'];
            }
            if ((int) $r['aa'] > 1990 && (int) $r['mm'] >= 1 && (int) $r['dd'] >= 1) {
                return sprintf('%04d-%02d-%02d', $r['aa'], $r['mm'], $r['dd']);
            }
            return null;
        });
        $rp = self::repair();
        $line = static fn ($v) => Str::clean($rp->fix(html_entity_decode((string) ($v ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')), false);
        $col->beginBulk();
        foreach ($rows as $i => $r) {
            $message = $rp->fix(Str::text(str_ireplace(['<br>', '<br/>', '<br />'], "\n", (string) $r['demande'])));
            $type = $line($r['type']);
            $created = $dates[$i];
            $cp = preg_replace('/\D/', '', (string) $r['code_postal']) ?? '';
            $col->bulkPut([
                'id' => (int) $r['id'],
                'status' => (int) $r['actif'] === 1 ? 'diffused' : 'rejected',
                'source' => 'legacy',
                'client' => [
                    'type' => stripos($type, 'soci') !== false ? 'societe' : (stripos($type, 'asso') !== false ? 'association' : 'particulier'),
                    'first_name' => Str::nameCase($line($r['prenom'])),
                    'last_name' => Str::nameCase($line($r['nom'])),
                    'company' => '',
                    'email' => Str::email((string) $r['email']),
                    'phone' => Str::phone((string) $r['telephone']),
                    'address' => $line($r['adresse']),
                    'postcode' => strlen($cp) === 4 ? '0' . $cp : $cp,
                    'city' => Str::nameCase($line($r['ville'])),
                ],
                'event' => [
                    'type' => self::occasionOf($message),
                    'date' => self::parseEventDate((string) $r['date'], $created),
                    'date_text' => $line($r['date']),
                    'place' => $line($r['lieu']),
                    'city' => $line($r['lieu']),
                    'dep' => Geo::depCode((string) $r['depoffre']),
                    'guests' => $line($r['nombre']),
                    'budget' => in_array(trim((string) $r['budget']), ['', '0'], true) ? '' : $line($r['budget']),
                    'categories' => Categories::detect($message),
                    'message' => $message,
                ],
                'recipients' => [],
                'spam' => ['score' => 0, 'reasons' => []],
                'created_at' => $created,
                'updated_at' => $created,
                'legacy' => ['actif' => (int) $r['actif']],
            ]);
        }
        $col->endBulk();
        return ['done' => true, 'offset' => count($rows), 'total' => count($rows), 'message' => count($rows) . ' demandes de devis importées'];
    }

    private static function messages(int $offset, int $limit): array
    {
        $col = Store::messages();
        $rows = iterator_to_array(self::rows('adherentcontact'), false);
        usort($rows, static fn ($a, $b) => (int) $a['idcontact'] <=> (int) $b['idcontact']);
        $total = count($rows);
        if ($offset === 0) {
            $col->truncate();
        }
        $dates = self::interpolateDates($rows, static function (array $r): ?string {
            if (!empty($r['contact_date']) && $r['contact_date'] !== '0000-00-00') {
                return (string) $r['contact_date'];
            }
            if ((int) $r['aa'] > 1990 && (int) $r['mm'] >= 1 && (int) $r['dd'] >= 1) {
                return sprintf('%04d-%02d-%02d', $r['aa'], $r['mm'], $r['dd']);
            }
            return null;
        });
        $rp = self::repair();
        $line = static fn ($v) => Str::clean($rp->fix(html_entity_decode((string) ($v ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')), false);
        $col->beginBulk();
        // en mode lot, on recharge les index déjà écrits pour les compléter
        $slice = array_slice($rows, $offset, $limit, true);
        foreach ($slice as $i => $r) {
            $message = $rp->fix(Str::text(str_ireplace(['<br>', '<br/>', '<br />'], "\n", (string) $r['contact_demande'])));
            $col->bulkPut([
                'id' => (int) $r['idcontact'],
                'pro_id' => (int) $r['idadherent'],
                'status' => (int) $r['actif'] === 1 ? 'delivered' : 'rejected',
                'source' => 'legacy',
                'name' => Str::nameCase($line($r['contact_nom'])),
                'email' => Str::email((string) $r['contact_email']),
                'phone' => Str::phone((string) $r['contact_tel']),
                'place' => $line($r['contact_lieu']),
                'message' => $message,
                'spam' => ['score' => 0, 'reasons' => []],
                'created_at' => $dates[$i],
                'updated_at' => $dates[$i],
                'read_at' => $dates[$i],
                'legacy' => ['actif' => (int) $r['actif']],
            ]);
        }
        $col->endBulk();
        $next = $offset + count($slice);
        if ($next >= $total) {
            $col->rebuild();
        }
        return ['done' => $next >= $total, 'offset' => $next, 'total' => $total, 'message' => "$next / $total messages importés"];
    }

    private static function prospects(): array
    {
        $seen = [];
        $items = [];
        foreach (self::rows('liste') as $r) {
            $e = Str::email((string) $r['email']);
            if (!Str::emailValid($e) || isset($seen[$e])) {
                continue;
            }
            $seen[$e] = true;
            $items[] = ['id' => (int) $r['id'], 'email' => $e, 'source' => 'ancien site', 'created_at' => null, 'unsubscribed_at' => null];
        }
        Store::doc('prospects')->save(['items' => $items, 'updated_at' => date('c')]);
        return ['done' => true, 'offset' => count($items), 'total' => count($items), 'message' => count($items) . ' emails uniques (doublons et adresses invalides écartés)'];
    }

    private static function finalize(): array
    {
        $n = Store::pros()->rebuild();
        Store::requests()->rebuild();
        // Compteurs par pro (messages reçus) pour le score « très demandé »
        $counts = [];
        $since = date('c', strtotime('-365 days'));
        foreach (Store::messages()->iterate() as $m) {
            if ($m['status'] === 'delivered' && $m['created'] >= $since) {
                $counts[$m['pro']] = ($counts[$m['pro']] ?? 0) + 1;
            }
        }
        $patches = [];
        foreach ($counts as $proId => $c) {
            $patches[(int) $proId] = static function (array $p) use ($c): array {
                $p['stats']['messages_12m'] = $c;
                return $p;
            };
        }
        Store::pros()->updateMany($patches);
        \App\Services\Stats::recomputeHot();
        \App\Core\Cache::flush();
        \App\Core\Cache::flush('pages');
        Pros::changed();
        Fs::writeJson(STORAGE_PATH . '/data/import_done.json', ['at' => date('c'), 'counts' => self::status()['dump']['counts'] ?? []]);
        \App\Services\Notify::admin('system', 'Import terminé', "La migration de l'ancienne base est terminée ($n fiches).", \App\Core\Url::admin('pros'), 'success');
        return ['done' => true, 'offset' => $n, 'total' => $n, 'message' => "Index reconstruits ($n fiches), caches vidés"];
    }

    // ------------------------------------------------------------------ photos

    /**
     * Rapatrie les photos des pros depuis l'ancien site (/upload/{id}.JPG, {id}_2.JPG…).
     * @return array{done:bool, offset:int, total:int, message:string, imported:int}
     */
    public static function photos(int $offset, int $limit = 15, bool $onlyActive = true): array
    {
        @set_time_limit(300);
        $base = rtrim((string) Env::get('OLD_SITE_URL', 'https://www.animateurpourvotresoiree.com'), '/');
        $ids = [];
        foreach (Store::pros()->iterate(false) as $id => $row) {
            if (!$onlyActive || $row['status'] === 'active') {
                $ids[] = (int) $id;
            }
        }
        $total = count($ids);
        $imported = 0;
        foreach (array_slice($ids, $offset, $limit) as $id) {
            $pro = Store::pros()->get($id);
            if (!$pro || !empty($pro['legacy']['photos_imported'])) {
                continue;
            }
            $photos = $pro['photos'] ?? [];
            $alts = $pro['legacy']['photo_alts'] ?? [];
            foreach (['', '_2', '_3', '_4', '_5'] as $n => $suffix) {
                $tmp = null;
                foreach (['JPG', 'jpg'] as $ext) {
                    $tmp = Image::download($base . '/upload/' . $id . $suffix . '.' . $ext);
                    if ($tmp) {
                        break;
                    }
                }
                if (!$tmp) {
                    if ($n === 0) {
                        continue;
                    }
                    break;
                }
                try {
                    $img = Image::store($tmp, PUBLIC_PATH . '/media/pros/' . $id);
                    $photos[] = [
                        'id' => $img['id'],
                        'file' => $id . '/' . $img['id'],
                        'ext' => $img['ext'],
                        'w' => $img['w'],
                        'h' => $img['h'],
                        'alt' => $alts[$n] ?? Pros::displayName($pro),
                        'cover' => $photos === [],
                        'legacy' => 'upload/' . $id . $suffix,
                    ];
                    $imported++;
                } catch (\Throwable $e) {
                    Logger::log('import', 'Photo ignorée', ['pro' => $id, 'error' => $e->getMessage()], 'warning');
                } finally {
                    @unlink($tmp);
                }
            }
            Store::pros()->update($id, static function (array $p) use ($photos): array {
                $p['photos'] = $photos;
                $p['legacy']['photos_imported'] = true;
                return $p;
            }, false);
        }
        $next = min($total, $offset + $limit);
        if ($next >= $total) {
            Pros::changed();
        }
        return ['done' => $next >= $total, 'offset' => $next, 'total' => $total, 'imported' => $imported, 'message' => "$next / $total fiches traitées ($imported photos)"];
    }

    // ----------------------------------------------------------------- outils

    /** @return \Generator<array> */
    public static function rows(string $table): \Generator
    {
        $file = self::DIR . '/tables/' . $table . '.jsonl';
        if (!is_file($file)) {
            return;
        }
        $fh = fopen($file, 'r');
        while (($line = fgets($fh)) !== false) {
            $row = json_decode($line, true);
            if (is_array($row)) {
                yield $row;
            }
        }
        fclose($fh);
    }

    private static function repair(): TextRepair
    {
        if (self::$repair === null) {
            self::$repair = new TextRepair();
            self::$repair->import(Fs::readJson(self::DIR . '/dictionary.json', []));
        }
        return self::$repair;
    }

    /** Date de chaque ligne ; à défaut, celle de la ligne valide précédente (ordre des identifiants). */
    private static function interpolateDates(array $rows, callable $get): array
    {
        $out = [];
        $last = '2002-06-01';
        $firstValid = null;
        foreach ($rows as $r) {
            $d = $get($r);
            if ($d && strtotime($d)) {
                $firstValid = $d;
                break;
            }
        }
        $last = $firstValid ?? $last;
        foreach ($rows as $i => $r) {
            $d = $get($r);
            if ($d && ($ts = strtotime($d)) && $ts > 0) {
                $last = $d;
                $out[$i] = date('c', $ts);
            } else {
                $out[$i] = date('c', (int) strtotime($last));
            }
        }
        return $out;
    }

    private static function parseEventDate(string $raw, string $created): string
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $raw;
        }
        if (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2,4})$#', $raw, $m)) {
            $y = (int) $m[3];
            if ($y < 100) {
                $y += 2000;
            }
            if (checkdate((int) $m[2], (int) $m[1], $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
            }
        }
        return '';
    }

    private static function occasionOf(string $text): string
    {
        $t = ' ' . Str::norm($text) . ' ';
        foreach (['mariage' => ['mariage', 'wedding', 'vin d honneur'], 'anniversaire' => ['anniversaire', 'anniv'], 'entreprise' => ['entreprise', 'seminaire', 'comite', ' ce ', 'cse', 'arbre de noel', 'salon'], 'soiree' => ['soiree', 'fete', 'bal', 'reveillon']] as $type => $words) {
            foreach ($words as $w) {
                if (str_contains($t, ' ' . trim($w))) {
                    return $type;
                }
            }
        }
        return 'autre';
    }
}
