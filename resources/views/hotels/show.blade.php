@extends('layouts.app')

@section('title', $hotel->name)

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div>
            <h2 style="margin:0 0 0.25rem; font-size:1.1rem;">{{ $hotel->name }}</h2>
            @if($hotel->city)
                <span style="color:var(--muted); font-size:0.9rem;">{{ $hotel->city }}</span>
            @endif
        </div>
        <a href="{{ route('hotels.index') }}" class="btn btn-secondary">← К списку отелей</a>
    </div>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h2 style="margin:0; font-size:1.1rem;">Размещения</h2>
        <a href="{{ route('placements.create', $hotel) }}" class="btn">+ Добавить размещение</a>
    </div>
    @if($hotel->placements->isEmpty())
        <p class="empty">Нет размещений. Добавьте первое.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Варианты поиска (взрослые + дети)</th>
                    <th style="width:140px;">Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($hotel->placements as $pl)
                    <tr>
                        <td>{{ $pl->name }}</td>
                        <td>
                            @if($pl->variants->isEmpty())
                                <span style="color:var(--muted);">Нет вариантов</span>
                            @else
                                @foreach($pl->variants as $v)
                                    <span class="variant-tag">
                                        {{ $v->adults }} взр.@if($v->children_ages), дети {{ str_replace(',', ', ', $v->children_ages) }}@endif
                                        <a href="{{ route('placement-variants.edit', $v) }}" class="variant-edit">изм.</a>
                                        <form action="{{ route('placement-variants.destroy', $v) }}" method="post" class="form-inline variant-delete" onsubmit="return confirm('Удалить этот вариант?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-link btn-danger">×</button>
                                        </form>
                                    </span>
                                @endforeach
                            @endif
                            <a href="{{ route('placement-variants.create', $pl) }}" class="btn btn-sm" style="margin-left:0.5rem;">+ Вариант</a>
                        </td>
                        <td>
                            <a href="{{ route('placements.edit', $pl) }}" class="btn btn-sm">Изменить</a>
                            <form action="{{ route('placements.destroy', $pl) }}" method="post" style="display:inline;" class="form-inline" onsubmit="return confirm('Удалить размещение «{{ $pl->name }}»?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
