<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('availability')) {
            return;
        }
        Schema::create('availability', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('placement_id');
            $table->date('date');
            $table->unsignedTinyInteger('available')->default(1);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['placement_id', 'date']);
            $table->foreign('placement_id')->references('id')->on('placements');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability');
    }
};
