@extends('layouts.app')

@section('title', 'Статистика')

@section('content')
<div class="card">
    <h2 style="margin:0 0 1rem; font-size:1.1rem;">Количество записей</h2>
    <p style="color:var(--muted); font-size:0.9rem; margin:0 0 1rem;">Сравнение объёмов данных в MySQL и ClickHouse.</p>
    <table>
        <thead>
            <tr>
                <th>Таблица / сущность</th>
                <th>MySQL</th>
                <th>ClickHouse</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>hotels</td>
                <td>{{ number_format($mysql['hotels']) }}</td>
                <td>—</td>
            </tr>
            <tr>
                <td>placements</td>
                <td>{{ number_format($mysql['placements']) }}</td>
                <td>—</td>
            </tr>
            <tr>
                <td>placement_variants</td>
                <td>{{ number_format($mysql['placement_variants']) }}</td>
                <td>{{ isset($clickhouse['placement_variants']) && $clickhouse['placement_variants'] !== null ? number_format($clickhouse['placement_variants']) : '—' }}</td>
            </tr>
            <tr>
                <td>availability / availability_final</td>
                <td>{{ number_format($mysql['availability']) }}</td>
                <td>{{ isset($clickhouse['availability_final']) && $clickhouse['availability_final'] !== null ? number_format($clickhouse['availability_final']) : '—' }}</td>
            </tr>
            <tr>
                <td>prices_by_day / prices_by_day_final</td>
                <td>{{ number_format($mysql['prices_by_day']) }}</td>
                <td>{{ isset($clickhouse['prices_by_day_final']) && $clickhouse['prices_by_day_final'] !== null ? number_format($clickhouse['prices_by_day_final']) : '—' }}</td>
            </tr>
            <tr>
                <td>—</td>
                <td>—</td>
                <td>—</td>
            </tr>
            <tr>
                <td><strong>search_by_variant</strong> (поиск без JOIN)</td>
                <td>—</td>
                <td>{{ isset($clickhouse['search_by_variant']) && $clickhouse['search_by_variant'] !== null ? number_format($clickhouse['search_by_variant']) : '—' }}</td>
            </tr>
        </tbody>
    </table>
    @if($chError)
        <p class="alert alert-error" style="margin-top:1rem;">ClickHouse: {{ $chError }}</p>
    @endif
</div>

<div class="card">
    <h2 style="margin:0 0 0.5rem; font-size:1.05rem;">Почему поиск не работает после <code>db:seed</code>?</h2>
    <p style="color:var(--muted); font-size:0.9rem; margin:0 0 0.75rem;">Поиск читает из таблицы <strong>search_by_variant</strong> в ClickHouse. Она заполняется:</p>
    <ul style="margin:0 0 0.5rem; padding-left:1.5rem; color:var(--muted); font-size:0.9rem;">
        <li>автоматически при поступлении данных из Kafka в <code>availability_final</code> и <code>prices_by_day_final</code> (через Materialized Views);</li>
        <li>или вручную после сида — выполните две команды:</li>
    </ul>
    <pre style="background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:1rem; font-size:0.85rem; overflow-x:auto; margin:0;">docker compose exec laravel php artisan clickhouse:sync-placement-variants
docker compose exec laravel php artisan clickhouse:refresh-search-table</pre>
    <p style="color:var(--muted); font-size:0.85rem; margin:0.5rem 0 0;">Первая синхронизирует справочник вариантов (взрослые/дети) из MySQL в CH; вторая пересобирает <strong>search_by_variant</strong> из финальных таблиц. Если в MySQL много размещений (например, после сида 100 отелей × 20 размещений), но поиск показывает «1 отель, 1 размещение» — в CH в <code>placement_variants</code> скорее всего старые данные: выполните обе команды по порядку.</p>
</div>
@endsection
