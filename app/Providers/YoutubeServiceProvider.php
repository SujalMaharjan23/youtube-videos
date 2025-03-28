<?php

namespace App\Providers;

use App\Services\YoutubeService;
use App\Services\YoutubeApiService;
use App\Services\YoutubeYtDlpService;
use Illuminate\Support\ServiceProvider;
use App\YoutubeInterface;

class YoutubeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->app->bind(YoutubeInterface::class . '.primary', function () {
            return new YoutubeApiService();
        });

        $this->app->bind(YoutubeInterface::class . '.fallback', function () {
            return new YoutubeYtDlpService();
        });

        $this->app->bind(YoutubeService::class, function ($app) {
            return new YoutubeService(
                $app->make(YoutubeInterface::class . '.primary'),
                $app->make(YoutubeInterface::class . '.fallback')
            );
        });
    }
}
