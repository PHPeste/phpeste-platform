<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(['api', 'idempotent'])
        ->post('/__test/idempotent', fn () => response()->json(['value' => random_int(1, 1_000_000)]));
});

it('exige o header Idempotency-Key', function (): void {
    $this->postJson('/__test/idempotent')
        ->assertStatus(400)
        ->assertJson(['code' => 'idempotency_key_required']);
});

it('repete a mesma resposta pra mesma chave', function (): void {
    $first = $this->postJson('/__test/idempotent', [], ['Idempotency-Key' => 'abc-123']);
    $second = $this->postJson('/__test/idempotent', [], ['Idempotency-Key' => 'abc-123']);

    $first->assertOk();
    expect($second->json('value'))->toBe($first->json('value'));
});

it('gera uma resposta nova pra uma chave diferente', function (): void {
    $first = $this->postJson('/__test/idempotent', [], ['Idempotency-Key' => 'key-1']);
    $second = $this->postJson('/__test/idempotent', [], ['Idempotency-Key' => 'key-2']);

    expect($second->json('value'))->not->toBe($first->json('value'));
});
