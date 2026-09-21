<?php
declare(strict_types=1);

namespace App\Services;

/** Validation de formulaire : collecte les erreurs, ne jette jamais. */
final class Validator
{
    private array $errors = [];
    private array $clean = [];

    public function __construct(private readonly array $input)
    {
    }

    public function required(string $field, string $label = ''): self
    {
        $value = trim((string) ($this->input[$field] ?? ''));
        if ($value === '') {
            $this->errors[$field] = I18n::t('form.err_required');
        }
        $this->clean[$field] = $value;
        return $this;
    }

    public function optional(string $field, int $max = 2000): self
    {
        $this->clean[$field] = mb_substr(trim((string) ($this->input[$field] ?? '')), 0, $max);
        return $this;
    }

    public function email(string $field, bool $required = true): self
    {
        $value = strtolower(trim((string) ($this->input[$field] ?? '')));
        if ($value === '') {
            if ($required) {
                $this->errors[$field] = I18n::t('form.err_required');
            }
        } elseif (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = I18n::t('form.err_email');
        }
        $this->clean[$field] = $value;
        return $this;
    }

    public function url(string $field): self
    {
        $value = trim((string) ($this->input[$field] ?? ''));
        if ($value !== '' && !preg_match('#^https?://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }
        $this->clean[$field] = filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
        return $this;
    }

    public function integer(string $field, int $min = 0, int $max = 99): self
    {
        $raw = (string) ($this->input[$field] ?? '');
        $value = is_numeric($raw) ? (int) $raw : $min;
        $this->clean[$field] = max($min, min($max, $value));
        return $this;
    }

    public function date(string $field): self
    {
        $raw = trim((string) ($this->input[$field] ?? ''));
        $ts = $raw !== '' ? strtotime($raw) : false;
        $this->clean[$field] = $ts === false ? '' : date('c', $ts);
        return $this;
    }

    /** Liste séparée par des virgules -> tableau nettoyé et dédoublonné. */
    public function listOf(string $field, int $maxItems = 20, int $maxLength = 48): self
    {
        $raw = $this->input[$field] ?? '';
        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);
        $out = [];
        foreach ($parts as $part) {
            $item = trim((string) $part);
            if ($item !== '' && mb_strlen($item) <= $maxLength) {
                $out[mb_strtolower($item)] = $item;
            }
        }
        $this->clean[$field] = array_slice(array_values($out), 0, $maxItems);
        return $this;
    }

    /** N'accepte que des valeurs d'une liste blanche. */
    public function oneOf(string $field, array $allowed, string $default = ''): self
    {
        $value = trim((string) ($this->input[$field] ?? ''));
        $this->clean[$field] = in_array($value, $allowed, true) ? $value : $default;
        return $this;
    }

    public function accepted(string $field): self
    {
        $ok = in_array((string) ($this->input[$field] ?? ''), ['1', 'on', 'true', 'yes'], true);
        if (!$ok) {
            $this->errors[$field] = I18n::t('form.err_required');
        }
        $this->clean[$field] = $ok;
        return $this;
    }

    public function checkbox(string $field): self
    {
        $this->clean[$field] = in_array((string) ($this->input[$field] ?? ''), ['1', 'on', 'true', 'yes'], true);
        return $this;
    }

    public function addError(string $field, string $message): self
    {
        $this->errors[$field] = $message;
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function values(): array
    {
        return $this->clean;
    }

    public function value(string $field, mixed $default = ''): mixed
    {
        return $this->clean[$field] ?? $default;
    }
}
