<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('enrollments')
    ->timezone('America/Sao_Paulo')
    // ->everyMinute()
    // ->everyThreeHours()
    ->everyThirtyMinutes()
    // ->hourly()
    ->withoutOverlapping(1440) // 24h de proteção
    ->onOneServer()
    ->runInBackground();

Schedule::command('installment')
    ->timezone('America/Sao_Paulo')
    // ->everyMinute()
    ->everyTwoHours()
    // ->everyThreeHours()
    //  ->hourly()
    ->withoutOverlapping(1440) // 24h de proteção
    ->onOneServer()
    ->runInBackground();


Schedule::command('financial')
    ->timezone('America/Sao_Paulo')
    // ->everyMinute()
    //->everyThreeHours()
    ->hourly()
    ->withoutOverlapping(1440) // 24h de proteção
    ->onOneServer()
    ->runInBackground();

Schedule::command('student')
    ->timezone('America/Sao_Paulo')
    // ->everyMinute()
    ->everyThreeHours()
    // ->hourly()
    ->withoutOverlapping(1440) // 24h de proteção
    ->onOneServer()
    ->runInBackground();
