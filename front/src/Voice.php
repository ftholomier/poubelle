<?php

declare(strict_types=1);

/**
 * Messages vocaux laissés depuis la barre CTA (bouton rouge « Laissez un vocal »),
 * sur le modèle du site le-digital.com.
 *
 * Le navigateur enregistre au micro (MediaRecorder), transcrit en direct quand il le sait
 * (reconnaissance vocale du navigateur), puis envoie le fichier audio. Le vocal est rangé
 * hors du dossier public (storage/voices), devient une demande du back-office et part par
 * e-mail avec sa transcription (fichier audio joint si l'option de la page E-mails est cochée).
 * Enregistré en qualité « voix » (32 kbit/s), il pèse environ 240 Ko par minute.
 * Avec une clé Gemini, l'IA résume le message (et le transcrit si le navigateur ne l'a pas fait).
 */
final class Voice
{
    public const DEFAULTS = [
        'enabled' => true,
        'label' => 'Laissez un vocal',
        'max_seconds' => 120,
        'questions' => [
            'Quel est votre projet ?',
            'Quels produits ? Fenêtres, porte, volets…',
            'Où se trouve le chantier ?',
            'Pour quand souhaitez-vous le réaliser ?',
            'Comment vous joindre ?',
        ],
        'finish' => 'Terminer',
        'title' => 'Votre message vocal',
        'no_transcript' => 'Transcription indisponible sur ce navigateur : votre message sera quand même envoyé.',
        'summary_label' => 'Résumé pour nos commerciaux',
        'send' => 'Envoyer mon message',
        'again' => 'Recommencer',
        'sent_title' => 'Message reçu{name} !',
        'sent_text' => 'Nos commerciaux écoutent votre message et vous rappellent dans les délais les plus rapides possible.',
        'error_title' => 'Micro indisponible',
        'error_text' => 'Impossible d\'accéder au micro. Autorisez-le dans votre navigateur, ou utilisez le formulaire de devis.',
        'summary_prompt' => "Résume ce message vocal d'un client pour l'équipe commerciale de Renov'Est, en 3 lignes courtes commençant par « Projet : », « Produits : », « Lieu et délai : ». N'invente rien : écris « non précisé » si l'information manque. Message : « {transcript} »",
    ];

    private const MAX_BYTES = 10 * 1024 * 1024;
    private const TYPES = [
        'audio/webm' => 'webm', 'video/webm' => 'webm', 'audio/ogg' => 'ogg', 'application/ogg' => 'ogg', 'audio/mp4' => 'm4a',
        'video/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/aac' => 'aac',
    ];

    /** Réglages (rubrique Conversion du back-office), complétés par les valeurs par défaut. */
    public static function cfg(): array
    {
        $c = array_replace(self::DEFAULTS, (array) (Content::ui()['voice'] ?? []));
        $c['questions'] = array_values(array_filter((array) $c['questions'])) ?: self::DEFAULTS['questions'];
        $c['max_seconds'] = max(15, min(300, (int) $c['max_seconds']));
        return $c;
    }

    public static function enabled(): bool
    {
        return !empty(self::cfg()['enabled']);
    }

    public static function dir(): string
    {
        $d = cfg('storage') . '/voices';
        if (!is_dir($d)) {
            @mkdir($d, 0750, true);
        }
        return $d;
    }

    /** Chemin d'un vocal enregistré (nom vérifié), ou null. */
    public static function file(string $name): ?string
    {
        if (!preg_match('/^\d{8}-\d{6}-[0-9a-f]{8}\.(webm|ogg|m4a|mp3|wav|aac)$/', $name)) {
            return null;
        }
        $f = self::dir() . '/' . $name;
        return is_file($f) ? $f : null;
    }

    public static function mime(string $name): string
    {
        return match (pathinfo($name, PATHINFO_EXTENSION)) {
            'webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'aac' => 'audio/aac',
            default => 'application/octet-stream',
        };
    }

    /** Range le fichier envoyé. [nom du fichier, type audio]. */
    public static function store(array $f): array
    {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            throw new RuntimeException('Le message vocal n\'a pas pu être envoyé. Réessayez ?');
        }
        if ((int) $f['size'] > self::MAX_BYTES) {
            throw new RuntimeException('Votre message est trop long.');
        }
        $mime = function_exists('finfo_open') ? (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']) : '';
        if (!isset(self::TYPES[$mime])) {
            throw new RuntimeException('Format audio non reconnu.');
        }
        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . self::TYPES[$mime];
        if (!move_uploaded_file($f['tmp_name'], self::dir() . '/' . $name)) {
            throw new RuntimeException('Enregistrement impossible, réessayez.');
        }
        @chmod(self::dir() . '/' . $name, 0640);
        return [$name, self::mime($name)];
    }

    public static function delete(string $name): void
    {
        if (($f = self::file($name)) !== null) {
            @unlink($f);
        }
    }

    /** Résumé du message par Gemini ('' sans clé, message trop court ou en cas d'échec). */
    public static function summary(string $transcript): string
    {
        $transcript = trim($transcript);
        if (!Bot::aiReady() || str_word_count($transcript) < 6) {
            return '';
        }
        $prompt = str_replace('{transcript}', $transcript, (string) self::cfg()['summary_prompt']);
        try {
            return Gemini::generate(Settings::secret('gemini_key'), Bot::model(), 'Tu aides l\'équipe commerciale de ' . Content::site()['name'] . '. Réponds en français, sans mise en forme Markdown.', [['role' => 'user', 'content' => $prompt]], 1024);
        } catch (Throwable $e) {
            error_log('[renovest] résumé du vocal : ' . $e->getMessage());
            return '';
        }
    }

    /** Transcription par Gemini quand le navigateur n'a pas pu la faire ('' en cas d'échec). */
    public static function transcribe(string $name): string
    {
        $f = self::file($name);
        if ($f === null || !Bot::aiReady()) {
            return '';
        }
        try {
            return Gemini::transcribe(Settings::secret('gemini_key'), Bot::model(), $f, self::mime($name));
        } catch (Throwable $e) {
            error_log('[renovest] transcription du vocal : ' . $e->getMessage());
            return '';
        }
    }
}
