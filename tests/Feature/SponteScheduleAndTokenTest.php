<?php

use App\Http\Middleware\ApiTokenMiddleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;

it('agenda os commands em horários diferentes, a cada 6h por padrão', function () {
    $events = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn ($e) => [trim(preg_replace('/^.*artisan.?\s+/', '', $e->command), "' ") => $e->expression]);

    expect($events->only(['student', 'enrollments', 'financial', 'installment'])->all())->toBe([
        'student' => '0 1-23/6 * * *',
        'enrollments' => '0 2-23/6 * * *',
        'financial' => '0 3-23/6 * * *',
        'installment' => '0 4-23/6 * * *',
    ]);
});

it('agenda a varredura completa semanal (domingo) com o mesmo lock do ciclo frequente', function () {
    $events = collect(app(Schedule::class)->events());
    $command = fn ($e) => trim(preg_replace('/^.*artisan.?\s+/', '', $e->command), "' ");

    $full = $events->filter(fn ($e) => str_ends_with($command($e), '--all'))
        ->mapWithKeys(fn ($e) => [$command($e) => $e->expression]);

    expect($full->all())->toBe([
        'enrollments --all' => '0 2 * * 0',
        'financial --all' => '0 3 * * 0',
        'installment --all' => '0 4 * * 0',
    ]);

    foreach (['enrollments', 'financial', 'installment'] as $name) {
        $pair = $events->filter(fn ($e) => in_array($command($e), [$name, "$name --all"], true));

        expect($pair)->toHaveCount(2)
            ->and($pair->map->mutexName()->unique())->toHaveCount(1)
            // a varredura vem antes, para ganhar o lock no horário em comum
            ->and($command($pair->first()))->toBe("$name --all");
    }
});

it('nega acesso quando API_ACCESS_TOKEN está vazio', function () {
    config(['services.api_access_token' => '']);

    $request = Request::create('/api/alunos', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer ']);
    $response = (new ApiTokenMiddleware)->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(401);
});

it('libera acesso com o token correto', function () {
    config(['services.api_access_token' => 'abc']);

    $request = Request::create('/api/alunos', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer abc']);
    $response = (new ApiTokenMiddleware)->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(200);
});
