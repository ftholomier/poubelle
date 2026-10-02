<?php
declare(strict_types=1);

namespace App\Services\Import;

/**
 * Lecteur natif des dumps SQL (phpMyAdmin / mysqldump) : extrait les INSERT ligne par ligne,
 * sans serveur MySQL. Gère les chaînes échappées, NULL, nombres et valeurs hexadécimales.
 */
final class SqlDump
{
    /**
     * @param callable(string $table, array $row):void $onRow  $row = [colonne => valeur]
     * @param string[]|null $only tables à lire (null = toutes)
     * @return array<string,int> nombre de lignes par table
     */
    public static function each(string $file, callable $onRow, ?array $only = null): array
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new \RuntimeException('Lecture du dump impossible');
        }
        if (str_starts_with($sql, "\xEF\xBB\xBF")) {
            $sql = substr($sql, 3);
        }
        if (!mb_check_encoding(substr($sql, 0, 2_000_000), 'UTF-8')) {
            $sql = mb_convert_encoding($sql, 'UTF-8', 'Windows-1252');
        }
        $len = strlen($sql);
        $pos = 0;
        $counts = [];
        $columnsByTable = self::createColumns($sql);
        while (($p = stripos($sql, 'INSERT INTO ', $pos)) !== false) {
            $pos = $p + 12;
            // nom de table
            if ($sql[$pos] === '`') {
                $end = strpos($sql, '`', $pos + 1);
                $table = substr($sql, $pos + 1, $end - $pos - 1);
                $pos = $end + 1;
            } else {
                preg_match('/\G\s*([A-Za-z0-9_]+)/', $sql, $m, 0, $pos);
                $table = $m[1];
                $pos += strlen($m[0]);
            }
            // liste de colonnes facultative
            while ($pos < $len && ctype_space($sql[$pos])) {
                $pos++;
            }
            $cols = $columnsByTable[$table] ?? [];
            if ($sql[$pos] === '(') {
                $end = strpos($sql, ')', $pos);
                $cols = array_map(static fn ($c) => trim($c, " `\t\n\r"), explode(',', substr($sql, $pos + 1, $end - $pos - 1)));
                $pos = $end + 1;
            }
            $v = stripos($sql, 'VALUES', $pos);
            $pos = $v + 6;
            $skip = $only !== null && !in_array($table, $only, true);
            $counts[$table] ??= 0;
            // tuples
            while ($pos < $len) {
                while ($pos < $len && ctype_space($sql[$pos])) {
                    $pos++;
                }
                if ($sql[$pos] !== '(') {
                    break;
                }
                $pos++;
                $values = [];
                while (true) {
                    while (ctype_space($sql[$pos])) {
                        $pos++;
                    }
                    $ch = $sql[$pos];
                    if ($ch === "'") {
                        $pos++;
                        $buf = '';
                        while (true) {
                            $seg = strcspn($sql, "'\\", $pos);
                            $buf .= substr($sql, $pos, $seg);
                            $pos += $seg;
                            $c = $sql[$pos];
                            if ($c === '\\') {
                                $n = $sql[$pos + 1];
                                $buf .= match ($n) {
                                    'n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", 'Z' => "\x1A", 'b' => "\x08",
                                    default => $n,
                                };
                                $pos += 2;
                                continue;
                            }
                            // apostrophe : doublée = littérale, sinon fin de chaîne
                            if (($sql[$pos + 1] ?? '') === "'") {
                                $buf .= "'";
                                $pos += 2;
                                continue;
                            }
                            $pos++;
                            break;
                        }
                        $values[] = $buf;
                    } else {
                        $seg = strcspn($sql, ',)', $pos);
                        $tok = trim(substr($sql, $pos, $seg));
                        $pos += $seg;
                        if (strcasecmp($tok, 'NULL') === 0) {
                            $values[] = null;
                        } elseif (str_starts_with($tok, '0x')) {
                            $values[] = (string) hex2bin(substr($tok, 2));
                        } else {
                            $values[] = is_numeric($tok) ? $tok + 0 : $tok;
                        }
                    }
                    while (ctype_space($sql[$pos])) {
                        $pos++;
                    }
                    if ($sql[$pos] === ',') {
                        $pos++;
                        continue;
                    }
                    if ($sql[$pos] === ')') {
                        $pos++;
                        break;
                    }
                    throw new \RuntimeException("Dump illisible près de l'octet $pos (table $table)");
                }
                $counts[$table]++;
                if (!$skip) {
                    $row = $cols && count($cols) === count($values) ? array_combine($cols, $values) : $values;
                    $onRow($table, $row);
                }
                while ($pos < $len && ctype_space($sql[$pos])) {
                    $pos++;
                }
                if (($sql[$pos] ?? '') === ',') {
                    $pos++;
                    continue;
                }
                if (($sql[$pos] ?? '') === ';') {
                    $pos++;
                }
                break;
            }
        }
        return $counts;
    }

    /** Colonnes déclarées dans les CREATE TABLE (pour les INSERT sans liste de colonnes). */
    private static function createColumns(string $sql): array
    {
        $out = [];
        if (preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`?([A-Za-z0-9_]+)`?\s*\((.*?)\)\s*(?:ENGINE|TYPE|DEFAULT|;)/s', $sql, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $cols = [];
                foreach (preg_split('/\R/', $m[2]) as $line) {
                    if (preg_match('/^\s*`([^`]+)`\s+\w/', $line, $c)) {
                        $cols[] = $c[1];
                    }
                }
                $out[$m[1]] = $cols;
            }
        }
        return $out;
    }
}
