<?php

namespace App\Http\Controllers;

use App\Models\Availability;
use App\Models\Hotel;
use App\Models\Placement;
use App\Models\PlacementVariant;
use App\Models\PriceByDay;
use App\Services\ClickHouseService;
use Illuminate\View\View;

class StatsController extends Controller
{
    /**
     * Статистика: количество записей в MySQL и ClickHouse.
     */
    public function index(): View
    {
        $mysql = [
            'hotels' => Hotel::count(),
            'placements' => Placement::count(),
            'placement_variants' => PlacementVariant::count(),
            'availability' => Availability::count(),
            'prices_by_day' => PriceByDay::count(),
        ];

        $clickhouse = [];
        $chError = null;
        try {
            $clickhouse = ClickHouseService::fromConfig()->getTableCounts();
        } catch (\Throwable $e) {
            $chError = $e->getMessage();
        }

        return view('stats.index', [
            'mysql' => $mysql,
            'clickhouse' => $clickhouse,
            'chError' => $chError,
        ]);
    }
}
