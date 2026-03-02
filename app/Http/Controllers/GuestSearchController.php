<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Services\ClickHouseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuestSearchController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    /**
     * Форма поиска по взрослым и детям (без выбора размещения).
     * Вывод: отели → размещения с суммой цен за период и доступностью (есть ли хотя бы один доступный день).
     * Агрегация выполняется в ClickHouse.
     */
    public function index(Request $request): View
    {
        $dateFrom = $request->get('date_from', now()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->addDays(7)->format('Y-m-d'));
        $adults = (int) $request->get('adults', 2);
        $childrenAges = $this->parseChildrenAges($request->get('children_ages'));

        $hotels = [];
        $error = null;
        $hasSearched = $request->has('date_from') || $request->has('adults');

        if ($hasSearched) {
            $from = $dateFrom;
            $to = $dateTo;
            $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
            if ($days > self::MAX_RANGE_DAYS) {
                $error = 'Диапазон дат не должен превышать ' . self::MAX_RANGE_DAYS . ' дней.';
            } else {
                try {
                    $ch = ClickHouseService::fromConfig();
                    $rows = $ch->searchByGuestsOnlyAggregated($from, $to, $adults, $childrenAges);

                    if (! empty($rows)) {
                        $placementIds = array_column($rows, 'placement_id');
                        $placements = Placement::with('hotel')->whereIn('id', $placementIds)->get()->keyBy('id');

                        $byPlacement = [];
                        foreach ($rows as $row) {
                            $pid = (int) $row['placement_id'];
                            $byPlacement[$pid] = (object) [
                                'total_price' => isset($row['total_price']) ? (float) $row['total_price'] : null,
                                'available' => (bool) ($row['available'] ?? 0),
                                'currency' => $row['currency'] ?? 'RUB',
                            ];
                        }

                        $hotelsById = [];
                        foreach ($placements as $placement) {
                            $hotel = $placement->hotel;
                            if (! $hotel) {
                                continue;
                            }
                            $hid = $hotel->id;
                            if (! isset($hotelsById[$hid])) {
                                $hotelsById[$hid] = (object) [
                                    'id' => $hotel->id,
                                    'name' => $hotel->name,
                                    'city' => $hotel->city,
                                    'placements' => [],
                                ];
                            }
                            $agg = $byPlacement[$placement->id] ?? null;
                            $hotelsById[$hid]->placements[] = (object) [
                                'id' => $placement->id,
                                'name' => $placement->name,
                                'total_price' => $agg->total_price ?? null,
                                'available' => $agg->available ?? false,
                                'currency' => $agg->currency ?? 'RUB',
                            ];
                        }
                        $hotels = array_values($hotelsById);
                    }
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return view('search.index', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'adults' => $adults,
            'childrenAges' => $childrenAges,
            'childrenAgesStr' => implode(',', $childrenAges),
            'hotels' => $hotels,
            'error' => $error,
            'hasSearched' => $hasSearched,
        ]);
    }

    private function parseChildrenAges(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return array_values(array_map('intval', array_filter($value)));
        }
        $ages = array_map('intval', array_filter(explode(',', (string) $value)));

        return array_values($ages);
    }
}
