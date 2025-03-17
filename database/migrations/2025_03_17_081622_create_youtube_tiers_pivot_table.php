<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('youtube_tiers_pivot', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id');
            $table->unsignedBigInteger('tier_id');
            $table->timestamps();

            $table->foreign('channel_id')->references('channel_id')->on('youtube_channels')->onDelete('cascade');
            $table->foreign('tier_id')->references('id')->on('source_tiers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_tiers_pivot');
    }
};
