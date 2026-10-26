<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function perPage(Request $request, int $default = 15): int
    {
        return max(1, min(100, (int) $request->query('per_page', $default)));
    }
}
