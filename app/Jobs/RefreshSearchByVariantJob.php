<?php

namespace App\Jobs;

use App\Services\ClickHouseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshSearchByVariantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue(config('queue.default'));
    }

    public function handle(): void
    {
        try {
            ClickHouseService::fromConfig()->refreshSearchByVariant();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
