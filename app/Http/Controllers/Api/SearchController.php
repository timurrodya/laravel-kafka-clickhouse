<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Placement;
use App\Services\ClickHouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class SearchController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    /**
     * Поиск по составу гостей (и опционально по размещению).
     * Без placement_id — возвращает все отели и размещения с доступными ценами по датам.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->normalizeChildrenAges($request);

        $validated = $request->validate([
            'placement_id' => ['nullable', 'integer', 'exists:placements,id'],
            'date_from' => ['required', 'date', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'adults' => ['required', 'integer', 'min:1', 'max:255'],
            'children_ages' => ['nullable', 'array'],
            'children_ages.*' => ['integer', 'min:0', 'max:17'],
        ]);

        $from = $validated['date_from'];
        $to = $validated['date_to'];
        $days = \Carbon\Carbon::parse($from)->diffInDays(\Carbon\Carbon::parse($to)) + 1;
        if ($days > self::MAX_RANGE_DAYS) {
            return Response::json([
                'message' => 'Диапазон дат не должен превышать ' . self::MAX_RANGE_DAYS . ' дней.',
            ], 422);
        }

        $adults = (int) $validated['adults'];
        $childrenAges = $validated['children_ages'] ?? [];
        $ch = ClickHouseService::fromConfig();

        if (! empty($validated['placement_id'])) {
            return $this->searchByPlacement(
                $ch,
                (int) $validated['placement_id'],
                $from,
                $to,
                $adults,
                $childrenAges
            );
        }

        return $this->searchByGuests($ch, $from, $to, $adults, $childrenAges);
    }

    private function searchByPlacement(
        ClickHouseService $ch,
        int $placementId,
        string $from,
        string $to,
        int $adults,
        array $childrenAges
    ): JsonResponse {
        $rows = $ch->searchByPlacementAndGuests($placementId, $from, $to, $adults, $childrenAges);
        $dates = [];
        foreach ($rows as $row) {
            $dates[] = [
                'date' => $row['date'],
                'available' => (bool) ($row['available'] ?? 0),
                'price' => isset($row['price']) ? (float) $row['price'] : null,
                'currency' => $row['currency'] ?? null,
            ];
        }

        return Response::json([
            'data' => [
                'placement_id' => $placementId,
                'adults' => $adults,
                'children_ages' => $childrenAges,
                'date_from' => $from,
                'date_to' => $to,
                'dates' => $dates,
            ],
        ]);
    }

    private function searchByGuests(
        ClickHouseService $ch,
        string $from,
        string $to,
        int $adults,
        array $childrenAges
    ): JsonResponse {
        $rows = $ch->searchByGuestsOnly($from, $to, $adults, $childrenAges);
        if (empty($rows)) {
            return Response::json([
                'data' => [
                    'adults' => $adults,
                    'children_ages' => $childrenAges,
                    'date_from' => $from,
                    'date_to' => $to,
                    'hotels' => [],
                ],
            ]);
        }

        $placementIds = array_values(array_unique(array_column($rows, 'placement_id')));
        $placements = Placement::with('hotel')->whereIn('id', $placementIds)->get()->keyBy('id');

        $byPlacement = [];
        foreach ($rows as $row) {
            $pid = (int) $row['placement_id'];
            if (! isset($byPlacement[$pid])) {
                $byPlacement[$pid] = [];
            }
            $byPlacement[$pid][] = [
                'date' => $row['date'],
                'available' => (bool) ($row['available'] ?? 0),
                'price' => isset($row['price']) ? (float) $row['price'] : null,
                'currency' => $row['currency'] ?? null,
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
                $hotelsById[$hid] = [
                    'id' => $hotel->id,
                    'name' => $hotel->name,
                    'city' => $hotel->city,
                    'placements' => [],
                ];
            }
            $hotelsById[$hid]['placements'][] = [
                'id' => $placement->id,
                'name' => $placement->name,
                'dates' => $byPlacement[$placement->id] ?? [],
            ];
        }

        return Response::json([
            'data' => [
                'adults' => $adults,
                'children_ages' => $childrenAges,
                'date_from' => $from,
                'date_to' => $to,
                'hotels' => array_values($hotelsById),
            ],
        ]);
    }

    /**
     * Привести children_ages к массиву: из query приходит строка "10" (последнее при children_ages=5&children_ages=10),
     * строка "5,10" или массив. Нормализуем в массив целых чисел.
     */
    private function normalizeChildrenAges(Request $request): void
    {
        $value = $request->input('children_ages');

        if ($value === null || $value === '') {
            $request->merge(['children_ages' => []]);
            return;
        }

        if (is_array($value)) {
            $request->merge(['children_ages' => array_values(array_map('intval', array_filter($value)))]);
            return;
        }

        $ages = array_map('intval', array_filter(explode(',', (string) $value)));
        $request->merge(['children_ages' => array_values($ages)]);
    }
}
