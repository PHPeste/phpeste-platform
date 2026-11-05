<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Redis;

it('responde 200 quando banco e redis estão de pé', function (): void {
    Redis::shouldReceive('connection->ping')->andReturn(true);

    $this->get('/up')
        ->assertOk()
        ->assertJson(['status' => 'ok', 'checks' => ['database' => true, 'redis' => true]]);
});

it('responde 503 quando o banco cai', function (): void {
    Redis::shouldReceive('connection->ping')->andReturn(true);
    DB::shouldReceive('connection->getPdo')->andThrow(new RuntimeException('conexão recusada'));

    $this->get('/up')
        ->assertStatus(503)
        ->assertJson(['status' => 'degraded', 'checks' => ['database' => false, 'redis' => true]]);
});

it('responde 503 quando o redis cai', function (): void {
    Redis::shouldReceive('connection->ping')->andThrow(new RuntimeException('conexão recusada'));

    $this->get('/up')
        ->assertStatus(503)
        ->assertJson(['status' => 'degraded', 'checks' => ['database' => true, 'redis' => false]]);
});
