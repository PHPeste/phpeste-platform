<?php

declare(strict_types=1);

it('responde 200 com X-Request-Id', function (): void {
    $this->getJson('/api/v1/ping')
        ->assertOk()
        ->assertJson(['status' => 'ok'])
        ->assertHeader('X-Request-Id');
});

it('ecoa o X-Request-Id enviado pelo cliente', function (): void {
    $this->withHeader('X-Request-Id', 'meu-id-de-teste')
        ->getJson('/api/v1/ping')
        ->assertOk()
        ->assertHeader('X-Request-Id', 'meu-id-de-teste')
        ->assertJson(['request_id' => 'meu-id-de-teste']);
});

it('responde com o envelope de erro em rota inexistente', function (): void {
    $this->getJson('/api/v1/nao-existe')
        ->assertStatus(404)
        ->assertJsonStructure(['code', 'message', 'errors', 'request_id'])
        ->assertJson(['code' => 'not_found']);
});
