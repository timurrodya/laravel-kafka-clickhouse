<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prices_by_day')) {
            return;
        }
        Schema::create('prices_by_day', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('placement_id');
            $table->date('date');
            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('RUB');
            $table->timestamp('updated_at')->nullable();
            $table->unique(['placement_id', 'date']);
            $table->foreign('placement_id')->references('id')->on('placements');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prices_by_day');
    }
};
