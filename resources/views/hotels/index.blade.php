@extends('layouts.app')

@section('title', 'Отели и размещения')

@section('content')
<div class="card">
    <h2 style="margin:0 0 1rem; font-size:1.1rem;">Отели</h2>
    <p style="color:var(--muted); font-size:0.9rem; margin:0 0 1rem;">Выберите отель, чтобы просмотреть и управлять его размещениями.</p>
    @if($hotels->isEmpty())
        <p class="empty">Нет отелей. Добавьте отель через сидер или админку.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Отель</th>
                    <th>Город</th>
                    <th>Размещений</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($hotels as $h)
                    <tr>
                        <td>{{ $h->name }}</td>
                        <td>{{ $h->city ?? '—' }}</td>
                        <td>{{ $h->placements_count }}</td>
                        <td>
                            <a href="{{ route('hotels.show', $h) }}" class="btn btn-sm">Размещения</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
