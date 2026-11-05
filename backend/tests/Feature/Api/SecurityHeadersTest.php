<?php

declare(strict_types=1);

it('inclui os headers de segurança em toda resposta', function (): void {
    $this->getJson('/api/v1/ping')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy')
        ->assertHeader('Content-Security-Policy');
});

it('usa uma CSP mais frouxa (unpkg) só pra UI da documentação', function (): void {
    $response = $this->getJson('/api/v1/ping');
    $docsCsp = $this->getJson('/docs/api.json')->headers->get('Content-Security-Policy');

    expect($response->headers->get('Content-Security-Policy'))->not->toContain('unpkg.com')
        ->and($docsCsp)->toContain('unpkg.com');
});

it('não envia Strict-Transport-Security fora de HTTPS', function (): void {
    $this->getJson('/api/v1/ping')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('envia Strict-Transport-Security quando a requisição é HTTPS', function (): void {
    $this->get('https://localhost/api/v1/ping', ['Accept' => 'application/json'])
        ->assertHeader('Strict-Transport-Security');
});
