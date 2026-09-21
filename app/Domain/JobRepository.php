<?php
declare(strict_types=1);

namespace App\Domain;

use App\Storage\Repository;

final class JobRepository extends Repository
{
    protected static function dir(): string
    {
        return self::dataPath('jobs');
    }

    protected static function type(): string
    {
        return 'job';
    }
}
