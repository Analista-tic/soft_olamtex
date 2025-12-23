<?php

namespace App\Services\ProductSheet;

use App\Models\ProductSheet\Section;
use Illuminate\Support\Collection;

class SectionService
{
    public function options(): Collection
    {
        return Section::select('id', 'name')->orderBy('name')->get();
    }
}
