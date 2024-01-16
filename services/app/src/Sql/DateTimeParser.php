<?php

declare(strict_types=1);

namespace Nsfisis\Albatross\Sql;

use DateTimeImmutable;
use DateTimeZone;

final class DateTimeParser
{
    private const FORMATS = [
        'Y-m-d H:i:s.u',
        'Y-m-d H:i:s',
    ];

    public static function parse(string $s): DateTimeImmutable|false
    {
        foreach (self::FORMATS as $format) {
            $dt = DateTimeImmutable::createFromFormat(
                $format,
                $s,
                new DateTimeZone('UTC'),
            );
            if ($dt !== false) {
                return $dt;
            }
        }
        return false;
    }
}
