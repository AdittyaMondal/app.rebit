<?php


use App\Info\ColibriPlus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 |--------------------------------------------------------------------------
 | Story Clear Command
 |--------------------------------------------------------------------------
 | This command clears expired stories from the database every day at 00:00.
 |--------------------------------------------------------------------------
 */

Schedule::command('story:clear')->dailyAt('00:00');

Schedule::command('chat:invite-clear')->weekly();

Artisan::command('app:version', function () {
    $this->info(ColibriPlus::VERSION);
});

Artisan::command('db:test', function () {
    try {
        DB::connection()->getPdo();

        $this->info('OK. Your app is connected to database: ' . DB::connection()->getDatabaseName());
    }
    catch (Exception $e) {
        $this->error('Could not connect to the database: ' . $e->getMessage());
    }
});