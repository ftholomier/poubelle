<?php
declare(strict_types=1);

namespace App\Domain;

use App\Storage\Repository;

final class CvRepository extends Repository
{
    protected static function dir(): string
    {
        return self::dataPath('cv');
    }

    protected static function type(): string
    {
        return 'cv';
    }
}
