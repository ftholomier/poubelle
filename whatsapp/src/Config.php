<?php

declare(strict_types=1);

namespace Poubelle\WhatsApp;

/**
 * Chargement de la configuration depuis config.json,
 * avec possibilité de surcharger les secrets via variables d'environnement
 * (pratique en prod / CI pour ne rien committer).
 */
final class Config
{
    /** @var array<string,mixed> */
    private array $data;

    /** @param array<string,mixed> $data */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function load(?string $path = null): self
    {
        $path ??= dirname(__DIR__) . '/config.json';

        if (!is_file($path)) {
            throw new \RuntimeException(
                "Config introuvable : $path\n" .
                "→ Copie config.example.json vers config.json puis renseigne tes identifiants."
            );
        }

        /** @var array<string,mixed> $data */
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        // Surcharge par variables d'environnement (prioritaires sur le fichier).
        $envMap = [
            'access_token'    => 'WHATSAPP_ACCESS_TOKEN',
            'phone_number_id' => 'WHATSAPP_PHONE_NUMBER_ID',
            'verify_token'    => 'WHATSAPP_VERIFY_TOKEN',
            'app_secret'      => 'WHATSAPP_APP_SECRET',
        ];
        foreach ($envMap as $key => $env) {
            $value = getenv($env);
            if ($value !== false && $value !== '') {
                $data[$key] = $value;
            }
        }

        return new self($data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Récupère une valeur obligatoire. Lève une exception si elle est absente,
     * vide, ou encore à sa valeur d'exemple (REMPLACE_...).
     */
    public function requireKey(string $key): mixed
    {
        $value = $this->data[$key] ?? null;
        if ($value === null || $value === '' || str_starts_with((string) $value, 'REMPLACE_')) {
            throw new \RuntimeException(
                "Config manquante : '$key'.\n" .
                "→ Renseigne-la dans config.json ou via la variable d'environnement correspondante."
            );
        }
        return $value;
    }
}
