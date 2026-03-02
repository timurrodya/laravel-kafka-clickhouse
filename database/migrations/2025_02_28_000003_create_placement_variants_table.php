<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('placement_variants')) {
            return;
        }
        Schema::create('placement_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('placement_id');
            $table->unsignedTinyInteger('adults')->default(1);
            $table->string('children_ages', 255)->default('');
            $table->timestamp('updated_at')->nullable();
            $table->unique(['placement_id', 'adults', 'children_ages']);
            $table->foreign('placement_id')->references('id')->on('placements');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placement_variants');
    }
};
