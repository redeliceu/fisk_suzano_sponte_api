<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Sincronização com a Sponte
|--------------------------------------------------------------------------
|
| O mesmo código roda em várias pastas do droplet (uma por unidade). Para elas não
| chamarem a Sponte ao mesmo tempo:
|  - cada command começa numa hora diferente (SPONTE_<CMD>_START_HOUR) e repete a cada
|    SPONTE_<CMD>_EVERY_HOURS horas;
|  - cada unidade usa um minuto diferente (SPONTE_SCHEDULE_OFFSET, 0-59).
| Padrão: student 01h, enrollments 02h, financial 03h, installment 04h, a cada 6h.
|
| enrollments, financial e installment processam só alunos ativos no ciclo frequente.
| Uma vez por semana (SPONTE_FULL_SCAN_DAY, padrão domingo) rodam com --all na mesma
| START_HOUR. A varredura usa o mesmo lock do ciclo frequente e é registrada antes dele:
| no horário em comum ela vence, e o ciclo frequente pula enquanto ela estiver rodando.
|
| O nome do lock inclui o caminho da instalação, para o lock de uma unidade nunca
| bloquear outra que compartilhe o mesmo cache.
*/

$sponte = config('services.sponte.schedule');
$minute = max(0, min(59, (int) ($sponte['offset'] ?? 0)));
$lockMinutes = max(1, (int) ($sponte['lock_minutes'] ?? 360));
$fullScan = $sponte['full_scan'] ?? [];
$fullScanDay = max(0, min(6, (int) ($fullScan['day'] ?? 0)));
$fullScanLock = max(1, (int) ($fullScan['lock_minutes'] ?? 1440));
$installation = substr(sha1(base_path()), 0, 12);

foreach ($sponte['commands'] ?? [] as $command => $cfg) {
    $every = max(1, min(24, (int) ($cfg['every_hours'] ?? 6)));
    $start = max(0, min(23, (int) ($cfg['start_hour'] ?? 0)));
    $mutex = "sponte-$installation-$command";

    if ($command !== 'student' && ($fullScan['enabled'] ?? true)) {
        Schedule::command($command, ['--all'])
            ->timezone('America/Sao_Paulo')
            ->cron("$minute $start * * $fullScanDay")
            ->createMutexNameUsing($mutex)
            ->withoutOverlapping($fullScanLock)
            ->runInBackground();
    }

    Schedule::command($command)
        ->timezone('America/Sao_Paulo')
        ->cron("$minute $start-23/$every * * *")
        ->createMutexNameUsing($mutex)
        ->withoutOverlapping($lockMinutes)
        ->runInBackground();
}
