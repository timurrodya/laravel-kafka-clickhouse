@extends('layouts.app')

@section('title', 'Редактировать размещение')

@section('content')
<div class="card">
    <h2 style="margin:0 0 1rem; font-size:1.1rem;">Редактировать размещение</h2>
    <p style="color:var(--muted); font-size:0.9rem; margin:0 0 1rem;">Отель: {{ $placement->hotel->name }}</p>
    <form method="post" action="{{ route('placements.update', $placement) }}">
        @csrf
        @method('PUT')
        <div class="form-group" style="max-width:400px;">
            <label for="name">Название размещения</label>
            <input type="text" name="name" id="name" value="{{ old('name', $placement->name) }}" required maxlength="255">
            @error('name')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </div>
        <div class="form-row">
            <button type="submit">Сохранить</button>
            <a href="{{ route('hotels.show', $placement->hotel) }}" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</div>
@endsection
