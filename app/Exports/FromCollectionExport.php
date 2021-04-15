<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FromCollectionExport implements FromCollection, WithHeadings
{
    public function __construct(\Illuminate\Database\Eloquent\Collection $collections, $heading = null)
    {
        $this->collections = $collections;
        $this->heading = $heading;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->collections;
    }

    public function headings(): array
    {
        if ($this->heading) {
            return $this->heading;
        }
        $first = $this->collection()->first();
        return $first ? array_keys($first->toArray()) : [];
    }
}
