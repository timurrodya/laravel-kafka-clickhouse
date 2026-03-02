<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\View\View;

class HotelController extends Controller
{
    /**
     * Список отелей с ссылками на управление размещениями.
     */
    public function index(): View
    {
        $hotels = Hotel::withCount('placements')->orderBy('name')->get();

        return view('hotels.index', ['hotels' => $hotels]);
    }

    /**
     * Отель и его размещения (просмотр и управление).
     */
    public function show(Hotel $hotel): View
    {
        $hotel->load('placements.variants');

        return view('hotels.show', ['hotel' => $hotel]);
    }
}
