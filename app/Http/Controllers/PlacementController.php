<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Placement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlacementController extends Controller
{
    /**
     * Форма создания размещения для отеля.
     */
    public function create(Hotel $hotel): View
    {
        return view('placements.create', ['hotel' => $hotel]);
    }

    /**
     * Сохранить новое размещение.
     */
    public function store(Request $request, Hotel $hotel): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $hotel->placements()->create($validated);

        return redirect()
            ->route('hotels.show', $hotel)
            ->with('success', 'Размещение «' . $validated['name'] . '» создано.');
    }

    /**
     * Форма редактирования размещения.
     */
    public function edit(Placement $placement): View
    {
        $placement->load('hotel');

        return view('placements.edit', ['placement' => $placement]);
    }

    /**
     * Обновить размещение.
     */
    public function update(Request $request, Placement $placement): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $placement->update($validated);

        return redirect()
            ->route('hotels.show', $placement->hotel)
            ->with('success', 'Размещение обновлено.');
    }

    /**
     * Удалить размещение.
     */
    public function destroy(Placement $placement): RedirectResponse
    {
        $hotel = $placement->hotel;
        $name = $placement->name;
        $placement->delete();

        return redirect()
            ->route('hotels.show', $hotel)
            ->with('success', 'Размещение «' . $name . '» удалено.');
    }
}
