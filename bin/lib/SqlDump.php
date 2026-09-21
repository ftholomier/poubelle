<?php
declare(strict_types=1);

namespace Bin;

/**
 * Lecteur en flux d'un export mysqldump / phpMyAdmin.
 * Ne charge jamais le fichier entier en mémoire : un dump de 175 Mo passe
 * dans quelques mégaoctets.
 */
final class SqlDump
{
    public function __construct(private readonly string $path)
    {
        if (!is_file($this->path)) {
            throw new \RuntimeException('Dump introuvable : ' . $this->path);
        }
    }

    /**
     * Parcourt les lignes des tables demandées.
     *
     * @param  string[] $tables
     * @return \Generator<int, array{0:string,1:array<string,mixed>}>
     */
    public function rows(array $tables): \Generator
    {
        $wanted = array_flip($tables);
        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Lecture impossible : ' . $this->path);
        }

        $buffer = '';
        $table = null;
        $columns = [];

        try {
            while (($line = fgets($handle)) !== false) {
                if ($table === null) {
                    if (!str_starts_with($line, 'INSERT INTO `')) {
                        continue;
                    }
                    if (!preg_match('/^INSERT INTO `([^`]+)` \(([^)]*)\) VALUES/', $line, $m)) {
                        continue;
                    }
                    if (!isset($wanted[$m[1]])) {
                        continue;
                    }
                    $table = $m[1];
                    $columns = array_map(
                        static fn(string $c) => trim(trim($c), '`'),
                        explode(',', $m[2]),
                    );
                    $buffer = substr($line, (int) strpos($line, 'VALUES') + 6);
                } else {
                    $buffer .= $line;
                }

                if (rtrim($buffer) === '' || !str_ends_with(rtrim($buffer), ';')) {
                    continue;
                }

                foreach ($this->splitTuples(rtrim(rtrim($buffer), ';')) as $tuple) {
                    $row = [];
                    foreach ($columns as $i => $name) {
                        $row[$name] = $tuple[$i] ?? null;
                    }
                    yield [$table, $row];
                }

                $buffer = '';
                $table = null;
                $columns = [];
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Découpe `(1,'a'),(2,'b')` en tuples, en respectant les chaînes échappées.
     *
     * @return array<int, array<int, mixed>>
     */
    private function splitTuples(string $payload): array
    {
        $tuples = [];
        $current = [];
        $field = '';
        $inString = false;
        $inTuple = false;
        $length = strlen($payload);

        for ($i = 0; $i < $length; $i++) {
            $char = $payload[$i];

            if ($inString) {
                if ($char === '\\' && $i + 1 < $length) {
                    $field .= $char . $payload[$i + 1];
                    $i++;
                    continue;
                }
                if ($char === "'") {
                    // '' à l'intérieur d'une chaîne = apostrophe littérale
                    if ($i + 1 < $length && $payload[$i + 1] === "'") {
                        $field .= "''";
                        $i++;
                        continue;
                    }
                    $inString = false;
                }
                $field .= $char;
                continue;
            }

            if ($char === "'") {
                $inString = true;
                $field .= $char;
                continue;
            }
            if (!$inTuple) {
                if ($char === '(') {
                    $inTuple = true;
                    $current = [];
                    $field = '';
                }
                continue;
            }
            if ($char === ',') {
                $current[] = $this->decode($field);
                $field = '';
                continue;
            }
            if ($char === ')') {
                $current[] = $this->decode($field);
                $tuples[] = $current;
                $inTuple = false;
                $field = '';
                continue;
            }
            $field .= $char;
        }

        return $tuples;
    }

    /** Convertit un littéral SQL en valeur PHP. */
    private function decode(string $token): mixed
    {
        $token = trim($token);

        if ($token === 'NULL' || $token === '') {
            return $token === '' ? '' : null;
        }
        if ($token[0] === "'" && str_ends_with($token, "'")) {
            $body = substr($token, 1, -1);
            $body = str_replace("''", "'", $body);
            return strtr($body, [
                '\\n' => "\n", '\\r' => "\r", '\\t' => "\t", '\\0' => "\0",
                '\\Z' => "\x1a", "\\'" => "'", '\\"' => '"', '\\\\' => '\\',
            ]);
        }
        if (preg_match('/^-?\d+$/', $token)) {
            return (int) $token;
        }
        if (is_numeric($token)) {
            return (float) $token;
        }
        return $token;
    }
}
