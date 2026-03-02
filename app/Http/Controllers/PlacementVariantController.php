<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Models\PlacementVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlacementVariantController extends Controller
{
    /**
     * Форма добавления варианта поиска (взрослые + возрасты детей).
     */
    public function create(Placement $placement): View
    {
        $placement->load('hotel');

        return view('placement-variants.create', ['placement' => $placement]);
    }

    /**
     * Сохранить новый вариант.
     */
    public function store(Request $request, Placement $placement): RedirectResponse
    {
        $validated = $request->validate([
            'adults' => ['required', 'integer', 'min:1', 'max:255'],
            'children_ages' => ['nullable', 'string', 'max:255'],
        ]);

        $agesStr = $validated['children_ages'] ?? '';
        $ages = array_filter(array_map('intval', preg_split('/\s*,\s*/', $agesStr)));
        sort($ages);
        $childrenAges = implode(',', $ages);

        $placement->variants()->create([
            'adults' => (int) $validated['adults'],
            'children_ages' => $childrenAges,
        ]);

        $label = $this->variantLabel((int) $validated['adults'], $ages);

        return redirect()
            ->route('hotels.show', $placement->hotel)
            ->with('success', 'Вариант «' . $label . '» добавлен.');
    }

    /**
     * Форма редактирования варианта.
     */
    public function edit(PlacementVariant $placementVariant): View
    {
        $placementVariant->load('placement.hotel');

        return view('placement-variants.edit', ['variant' => $placementVariant]);
    }

    /**
     * Обновить вариант.
     */
    public function update(Request $request, PlacementVariant $placementVariant): RedirectResponse
    {
        $validated = $request->validate([
            'adults' => ['required', 'integer', 'min:1', 'max:255'],
            'children_ages' => ['nullable', 'string', 'max:255'],
        ]);

        $agesStr = $validated['children_ages'] ?? '';
        $ages = array_filter(array_map('intval', preg_split('/\s*,\s*/', $agesStr)));
        sort($ages);
        $childrenAges = implode(',', $ages);

        $placementVariant->update([
            'adults' => (int) $validated['adults'],
            'children_ages' => $childrenAges,
        ]);

        return redirect()
            ->route('hotels.show', $placementVariant->placement->hotel)
            ->with('success', 'Вариант обновлён.');
    }

    /**
     * Удалить вариант.
     */
    public function destroy(PlacementVariant $placementVariant): RedirectResponse
    {
        $hotel = $placementVariant->placement->hotel;
        $label = $this->variantLabel(
            $placementVariant->adults,
            $placementVariant->children_ages ? array_filter(explode(',', $placementVariant->children_ages)) : []
        );
        $placementVariant->delete();

        return redirect()
            ->route('hotels.show', $hotel)
            ->with('success', 'Вариант «' . $label . '» удалён.');
    }

    private function variantLabel(int $adults, $ages): string
    {
        $ages = is_array($ages) ? $ages : array_filter(explode(',', (string) $ages));
        $a = $adults . ' ' . ($adults === 1 ? 'взр.' : 'взр.');
        if (empty($ages)) {
            return $a;
        }
        return $a . ', дети ' . implode(', ', $ages);
    }
}
