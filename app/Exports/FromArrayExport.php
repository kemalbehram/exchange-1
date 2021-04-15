<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FromArrayExport implements FromArray, WithHeadings
{
    public function __construct($arrays, $heading = null)
    {
        $this->arrays = $arrays;
        $this->heading = $heading;
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return $this->collections;
    }

    public function headings(): array
    {
        if ($this->heading) {
            return $this->heading;
        }
        return count($this->arrays) > 0 ? array_keys(reset($this->arrays)) : [];
    }
}
