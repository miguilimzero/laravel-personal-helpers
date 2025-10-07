<?php

namespace Miguilim\Helpers;

use Error;

use Flowframe\Trend\Adapters\MySqlAdapter;
use Flowframe\Trend\Adapters\PgsqlAdapter;
use Flowframe\Trend\Adapters\SqliteAdapter;
use Flowframe\Trend\Trend as BaseTrend;

class Trend extends BaseTrend
{
    protected function getSqlDate(): string
    {
        $adapter = match ($this->builder->getConnection()->getDriverName()) {
            'mysql'       => new MySqlAdapter,
            'sqlite'      => new SqliteAdapter,
            'pgsql'       => new PgsqlAdapter,
            'singlestore' => new SingleStoreTrendAdapter,
            default       => throw new Error('Unsupported database driver.'),
        };

        return $adapter->format($this->dateColumn, $this->interval);
    }

    protected function getCarbonDateFormat(): string
    {
        return match ($this->interval) {
            'minute' => 'H:i:00',
            'hour'   => 'H:00',
            'day'    => 'Y-m-d',
            'week'   => 'Y-W',
            'month'  => 'Y-m',
            'year'   => 'Y',
            default  => throw new Error('Invalid interval.'),
        };
    }
}
