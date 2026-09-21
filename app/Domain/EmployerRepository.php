<?php
declare(strict_types=1);

namespace App\Domain;

use App\Storage\Repository;

final class EmployerRepository extends Repository
{
    protected static function dir(): string
    {
        return self::dataPath('employers');
    }

    protected static function type(): string
    {
        return 'employer';
    }
}
