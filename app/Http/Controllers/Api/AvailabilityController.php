<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Availability;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

class AvailabilityController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    /**
     * Создать или обновить доступность на дату или диапазон дат для размещения.
     * Либо одна дата (date), либо диапазон (date_from + date_to) включительно.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'placement_id' => ['required', 'integer', 'exists:placements,id'],
            'date' => ['required_without_all:date_from,date_to', 'nullable', 'date', 'date_format:Y-m-d'],
            'date_from' => ['required_without:date', 'nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['required_without:date', 'nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'available' => ['required', Rule::in([0, 1, true, false, '0', '1'])],
        ]);

        $this->validateDateRange($request, $validated);

        $available = (int) filter_var($validated['available'], FILTER_VALIDATE_BOOLEAN);
        $placementId = (int) $validated['placement_id'];
        $dates = $this->collectDates($validated);

        $created = [];
        foreach ($dates as $dateStr) {
            $record = Availability::updateOrCreate(
                [
                    'placement_id' => $placementId,
                    'date' => $dateStr,
                ],
                ['available' => $available]
            );
            $created[] = [
                'id' => $record->id,
                'date' => $record->date->format('Y-m-d'),
                'available' => (bool) $record->available,
            ];
        }

        return Response::json([
            'message' => 'ok',
            'data' => [
                'placement_id' => $placementId,
                'count' => count($created),
                'dates' => $created,
            ],
        ], 201);
    }

    private function validateDateRange(Request $request, array $validated): void
    {
        if (empty($validated['date_from']) || empty($validated['date_to'])) {
            return;
        }

        $from = Carbon::parse($validated['date_from']);
        $to = Carbon::parse($validated['date_to']);
        $days = $from->diffInDays($to) + 1;

        if ($days > self::MAX_RANGE_DAYS) {
            abort(422, 'Диапазон дат не должен превышать ' . self::MAX_RANGE_DAYS . ' дней.');
        }
    }

    private function collectDates(array $validated): array
    {
        if (!empty($validated['date'])) {
            return [$validated['date']];
        }

        $from = Carbon::parse($validated['date_from']);
        $to = Carbon::parse($validated['date_to']);
        $dates = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $dates[] = $d->format('Y-m-d');
        }
        return $dates;
    }
}
