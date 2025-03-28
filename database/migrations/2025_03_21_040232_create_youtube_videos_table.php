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
        Schema::create('youtube_videos', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id');
            $table->string('video_id')->unique();
            $table->string('video_url');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail');
            $table->string('upload_date')->nullable();
            $table->integer('view_count')->nullable();
            $table->string('like_count')->nullable();
            $table->string('duration')->nullable();
            $table->boolean('is_short')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('channel_id')->references('channel_id')->on('youtube_channels')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_videos');
    }
};
