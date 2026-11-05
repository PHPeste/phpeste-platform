<?php

declare(strict_types=1);

it('libera CORS pra origem permitida', function (): void {
    $this->withHeaders([
        'Origin' => 'http://localhost:3000',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/ping')
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
});

it('não libera CORS pra origem fora da lista', function (): void {
    $response = $this->withHeaders([
        'Origin' => 'https://site-malicioso.example',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/ping');

    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://site-malicioso.example');
});

it('expõe X-Request-Id pro front conseguir ler via CORS', function (): void {
    $this->withHeaders(['Origin' => 'http://localhost:3000'])
        ->getJson('/api/v1/ping')
        ->assertHeader('Access-Control-Expose-Headers', 'X-Request-Id');
});
