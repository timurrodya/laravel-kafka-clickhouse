@extends('layouts.app')

@section('title', 'Поиск по гостям')

@section('content')
<style>
.hotel-block { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border); }
.hotel-block:last-child { border-bottom: none; }
.hotel-name { font-size: 1.1rem; margin: 0 0 0.75rem; color: var(--text); }
.hotel-city { color: var(--muted); font-weight: 400; }
.placement-block { margin-bottom: 1.25rem; margin-left: 0.5rem; }
.placement-name { font-size: 0.95rem; font-weight: 600; color: var(--muted); margin: 0 0 0.5rem; }
.placement-row { display: flex; gap: 1rem; align-items: center; font-size: 0.9rem; margin-bottom: 0.5rem; }
.placement-row .price { font-weight: 600; }
.placement-row .available-yes { color: var(--success, #0a0); }
.placement-row .available-no { color: var(--muted); }
</style>
<div class="card">
    <h2 style="margin:0 0 1rem; font-size:1.1rem;">Поиск по составу гостей</h2>
    <p style="color:var(--muted); font-size:0.9rem; margin:0 0 1rem;">Укажите даты и один вариант состава гостей (взрослые + возрасты детей). В результатах — все отели и размещения, у которых есть этот вариант и данные за выбранный период. Чтобы увидеть другой состав, измените параметры и нажмите «Искать» снова.</p>
    <form method="get" action="{{ route('search.index') }}">
        <div class="form-row">
            <div class="form-group">
                <label for="date_from">Дата заезда</label>
                <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}" required>
            </div>
            <div class="form-group">
                <label for="date_to">Дата выезда</label>
                <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}" required>
            </div>
            <div class="form-group">
                <label for="adults">Взрослых</label>
                <input type="number" name="adults" id="adults" min="1" max="20" value="{{ $adults }}" required>
            </div>
            <div class="form-group">
                <label for="children_ages">Возрасты детей (через запятую, например 5,10)</label>
                <input type="text" name="children_ages" id="children_ages" placeholder="5, 10" value="{{ $childrenAgesStr }}" style="max-width:140px;">
            </div>
            <div class="form-group">
                <button type="submit">Искать</button>
            </div>
        </div>
        <p style="color:var(--muted); font-size:0.85rem; margin:0.5rem 0 0;">Быстрый выбор варианта: 
            @foreach([['adults' => 2, 'ages' => []], ['adults' => 2, 'ages' => [5]], ['adults' => 2, 'ages' => [5, 10]], ['adults' => 1, 'ages' => []]] as $v)
                <a href="{{ route('search.index', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'adults' => $v['adults'], 'children_ages' => $v['ages']]) }}" style="color:var(--accent); margin-right:0.5rem;">{{ $v['adults'] }} взр.@if(!empty($v['ages'])), {{ implode(',', $v['ages']) }} лет @endif</a>
            @endforeach
        </p>
    </form>
</div>

@if($error)
    <div class="alert alert-error">{{ $error }}</div>
@endif

@if($hasSearched && !$error)
    <div class="card">
        <h2 style="margin:0 0 1rem; font-size:1.1rem;">Результаты поиска</h2>
        @if(count($hotels) === 0)
            <p class="empty">По вашим параметрам ({{ $adults }} взр.@if(count($childrenAges) > 0), дети {{ implode(', ', $childrenAges) }} лет @endif) за период {{ $dateFrom }} — {{ $dateTo }} ничего не найдено.</p>
        @else
            @php
                $totalPlacements = array_sum(array_map(fn($h) => count($h->placements), $hotels));
            @endphp
            <p style="color:var(--muted); font-size:0.9rem; margin-bottom:1rem;">
                Найдено отелей: <strong>{{ count($hotels) }}</strong>, размещений: <strong>{{ $totalPlacements }}</strong>. Вариант поиска: {{ $adults }} взр.@if(count($childrenAges) > 0), дети {{ implode(', ', $childrenAges) }} лет @endif — для другого состава гостей измените параметры и нажмите «Искать» снова.
            </p>
            @foreach($hotels as $hotel)
                <div class="hotel-block">
                    <h3 class="hotel-name">{{ $hotel->name }}@if($hotel->city) <span class="hotel-city">({{ $hotel->city }})</span>@endif</h3>
                    @foreach($hotel->placements as $placement)
                        <div class="placement-block">
                            <h4 class="placement-name">{{ $placement->name }}</h4>
                            <div class="placement-row">
                                <span class="price">
                                    Сумма за период: {{ $placement->total_price !== null ? number_format($placement->total_price, 0, ',', ' ') . ' ' . ($placement->currency ?? 'RUB') : '—' }}
                                </span>
                                <span class="{{ $placement->available ? 'available-yes' : 'available-no' }}">
                                    Доступно: {{ $placement->available ? 'Да' : 'Нет' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>
@endif
@endsection
