<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Contracts\MenuInsights;

/** The default until a module that knows about sales binds MenuInsights. */
final class NoMenuInsights implements MenuInsights
{
    public function popular(): array
    {
        return [];
    }

    public function pairs(): array
    {
        return [];
    }
}
