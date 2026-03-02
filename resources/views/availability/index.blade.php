@extends('layouts.app')

@section('title', 'Доступность и цены')

@section('content')
<div class="card">
    <form method="get" action="{{ route('availability.index') }}">
        <div class="form-row">
            <div class="form-group">
                <label for="hotel_id">Отель</label>
                <select name="hotel_id" id="hotel_id">
                    <option value="">— Выберите отель —</option>
                    @foreach($hotels as $h)
                        <option value="{{ $h->id }}" @selected($h->id == $hotelId)>{{ $h->name }} @if($h->city)({{ $h->city }})@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="placement_id">Размещение</label>
                <select name="placement_id" id="placement_id" required>
                    <option value="">— Сначала выберите отель —</option>
                    @foreach($placements as $pl)
                        <option value="{{ $pl->id }}" data-hotel-id="{{ $pl->hotel_id }}" @selected($pl->id == $placementId)>{{ $pl->hotel->name }} — {{ $pl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="date_from">Дата с</label>
                <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}" required>
            </div>
            <div class="form-group">
                <label for="date_to">Дата по</label>
                <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}" required>
            </div>
            <div class="form-group">
                <button type="submit">Показать</button>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('hotel_id').addEventListener('change', function() {
    var hid = this.value;
    var sel = document.getElementById('placement_id');
    for (var i = 0; i < sel.options.length; i++) {
        var opt = sel.options[i];
        if (opt.value === '') { opt.style.display = 'block'; opt.disabled = !hid; continue; }
        opt.style.display = (!hid || opt.dataset.hotelId === hid) ? 'block' : 'none';
        opt.disabled = hid && opt.dataset.hotelId !== hid;
    }
    if (!hid) sel.value = '';
});
document.getElementById('hotel_id').dispatchEvent(new Event('change'));
</script>

@if($error)
    <div class="alert alert-error">{{ $error }}</div>
@endif

@if($placementId > 0 && !$error)
    <div class="card">
        <h2 style="margin:0 0 1rem; font-size:1.1rem;">Доступность и цены (данные из ClickHouse)</h2>
        @if(count($rows) === 0)
            <p class="empty">За выбранный период записей нет.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Доступность</th>
                        <th>Цена</th>
                        <th>Валюта</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>{{ $row['date'] ?? '' }}</td>
                            <td class="{{ (int)($row['available'] ?? 0) === 1 ? 'available-yes' : 'available-no' }}">
                                {{ (int)($row['available'] ?? 0) === 1 ? 'Да' : 'Нет' }}
                            </td>
                            <td>{{ $row['price'] ?? '—' }}</td>
                            <td>{{ $row['currency'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif
@endsection
