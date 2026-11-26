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
