<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Services\ClickHouseService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    public function index(Request $request): View
    {
        $hotels = Hotel::orderBy('name')->get();

        $hotelId = $request->integer('hotel_id', 0);
        $dateFrom = $request->get('date_from', now()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->addDays(7)->format('Y-m-d'));

        $rows = [];
        $error = null;

        if ($hotelId > 0) {
            try {
                $service = ClickHouseService::fromConfig();
                $rows = $service->getAvailabilityAndPrices($hotelId, $dateFrom, $dateTo);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('availability.index', [
            'hotels' => $hotels,
            'hotelId' => $hotelId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'rows' => $rows,
            'error' => $error,
        ]);
    }
}
