<?php

namespace Miguilim\Helpers;

use Error;

use Flowframe\Trend\Adapters\MySqlAdapter;

class SingleStoreTrendAdapter extends MySqlAdapter
{
    public function format(string $column, string $interval): string
    {
        $format = match ($interval) {
            'minute' => '%H:%i:00',
            'hour'   => '%H:00',
            'day'    => '%Y-%m-%d',
            'week'   => '%Y-%u',
            'month'  => '%Y-%m',
            'year'   => '%Y',
            default  => throw new Error('Invalid interval.'),
        };

        return "date_format({$column}, '{$format}')";
    }
}
