<?php
declare(strict_types=1);

namespace App\Core;

/** Document JSON unique (réglages, catégories, redirections…) avec accès par chemin "a.b.c". */
final class Doc
{
    private ?array $data = null;
    private string $path;

    public function __construct(public readonly string $name, private array $defaults = [])
    {
        if (!preg_match('/^[a-z0-9_\-\/]+$/', $name)) {
            throw new \InvalidArgumentException('Nom de document invalide');
        }
        $this->path = STORAGE_PATH . '/data/' . $name . '.json';
    }

    public function all(): array
    {
        if ($this->data === null) {
            $this->data = $this->withDefaults(Fs::readJson($this->path, null));
        }
        return $this->data;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $v = $this->all();
        foreach (explode('.', $key) as $k) {
            if (!is_array($v) || !array_key_exists($k, $v)) {
                return $default;
            }
            $v = $v[$k];
        }
        return $v;
    }

    public function set(string $key, mixed $value): void
    {
        $this->update(static function (array $d) use ($key, $value): array {
            $ref = &$d;
            foreach (explode('.', $key) as $k) {
                if (!isset($ref[$k]) || !is_array($ref[$k])) {
                    $ref[$k] = [];
                }
                $ref = &$ref[$k];
            }
            $ref = $value;
            return $d;
        });
    }

    /** Fusionne un tableau de valeurs (récursif sur les clés associatives). */
    public function merge(array $values): void
    {
        $this->update(static fn (array $d): array => Collection::merge($d, $values));
    }

    public function save(array $data): void
    {
        Fs::withLock($this->path . '.lock', function () use ($data): void {
            Fs::writeJson($this->path, $data, true);
        });
        $this->data = null;
        Cache::bump();
    }

    public function update(callable $fn): array
    {
        $result = Fs::withLock($this->path . '.lock', function () use ($fn): array {
            $next = $fn($this->withDefaults(Fs::readJson($this->path, null)));
            Fs::writeJson($this->path, $next, true);
            return $next;
        });
        $this->data = null;
        Cache::bump();
        return $result;
    }

    /** Listes : le contenu stocké remplace les valeurs par défaut. Objets : fusion récursive. */
    private function withDefaults(mixed $stored): array
    {
        if (!is_array($stored)) {
            return $this->defaults;
        }
        if ($this->defaults === [] || array_is_list($this->defaults)) {
            return $stored === [] && $this->defaults !== [] ? $this->defaults : $stored;
        }
        return Collection::merge($this->defaults, $stored);
    }
}
