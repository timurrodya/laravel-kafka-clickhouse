@extends('layouts.app')

@section('title', 'Редактировать вариант поиска')

@section('content')
<div class="card">
    <h2 style="margin:0 0 1rem; font-size:1.1rem;">Редактировать вариант поиска</h2>
    <p style="color:var(--muted); font-size:0.9rem; margin:0 0 1rem;">Размещение: {{ $variant->placement->name }} ({{ $variant->placement->hotel->name }})</p>
    <form method="post" action="{{ route('placement-variants.update', $variant) }}">
        @csrf
        @method('PUT')
        <div class="form-row" style="flex-wrap:wrap;">
            <div class="form-group" style="max-width:120px;">
                <label for="adults">Взрослых</label>
                <input type="number" name="adults" id="adults" value="{{ old('adults', $variant->adults) }}" min="1" max="255" required>
                @error('adults')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group" style="max-width:220px;">
                <label for="children_ages">Возрасты детей (через запятую)</label>
                <input type="text" name="children_ages" id="children_ages" value="{{ old('children_ages', $variant->children_ages) }}" placeholder="5, 10">
                @error('children_ages')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <div class="form-row">
            <button type="submit">Сохранить</button>
            <a href="{{ route('hotels.show', $variant->placement->hotel) }}" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</div>
@endsection
