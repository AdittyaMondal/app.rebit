<?php

namespace App\Providers;

use App\Data\DataCapsule;
use App\Models\Censor;
use App\Services\Censor\CensorService;
use Illuminate\Support\Facades\Cache;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Support\Languages;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RoundRobinService::class, function () {
            return new RoundRobinService();
        });

        $this->app->singleton(DataCapsule::class, function () {
            return new DataCapsule();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        bcscale(2);

        /*
         * The banned-word list was cached with rememberForever and nothing ever
         * forgot it, so an admin editing the list had no effect until the cache
         * was cleared by hand.
         */
        Censor::saved(fn () => Cache::forget(CensorService::CACHE_KEY));
        Censor::deleted(fn () => Cache::forget(CensorService::CACHE_KEY));


        View::composer('*', function($view) {
            $view->with('localeName', (new Languages())->getLocaleName());
            $view->with('buildNumber', cache()->rememberForever('frontend_build_number', function() {
                return file_get_contents(storage_path('frontend/build.num')) ?? random_int(1, 1000000);
            }));
        });

        LogViewer::auth(function() {
            return auth_check() && me()->isRoot();
        });

        $livewireComponents = [
            'admin.config.pwa' => \App\Livewire\Admin\Config\PWA::class,
            'admin.config.ffmpeg' => \App\Livewire\Admin\Config\FFMPeg::class,
            'admin.config.ffmpeg-test' => \App\Livewire\Admin\Config\FFMPegTest::class,
        ];

        foreach ($livewireComponents as $alias => $class) {
            Livewire::component($alias, $class);
        }
    }
}
