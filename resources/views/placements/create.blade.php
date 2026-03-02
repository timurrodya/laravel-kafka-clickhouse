@extends('layouts.app')

@section('title', 'Новое размещение')

@section('content')
<div class="card">
    <h2 style="margin:0 0 1rem; font-size:1.1rem;">Добавить размещение в «{{ $hotel->name }}»</h2>
    <form method="post" action="{{ route('placements.store', $hotel) }}">
        @csrf
        <div class="form-group" style="max-width:400px;">
            <label for="name">Название размещения</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required maxlength="255" placeholder="Например: Стандарт двуместный">
            @error('name')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </div>
        <div class="form-row">
            <button type="submit">Создать</button>
            <a href="{{ route('hotels.show', $hotel) }}" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</div>
@endsection
