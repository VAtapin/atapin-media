<?php

namespace App\Http\Controllers;

use App\Services\InstallationHealth;

class InstallationHealthController extends Controller
{
    public function __invoke(InstallationHealth $health)
    {
        $report = $health->report();

        return response()->json($report, $report['status'] === 'ok' ? 200 : 503)->header('Cache-Control', 'private, no-store');
    }
}
