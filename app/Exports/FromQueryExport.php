<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FromQueryExport implements FromQuery, WithHeadings
{
    public function __construct(\Illuminate\Database\Eloquent\Builder $builder, $heading = null)
    {
        $this->builder = $builder;
        $this->heading = $heading;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function query()
    {
        return $this->builder;
    }

    public function headings(): array
    {
        if ($this->heading) {
            return $this->heading;
        }
        $first = $this->query()->first();
        return $first ? array_keys($first->toArray()) : [];
    }
}
