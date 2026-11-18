<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use PHPeste\Cfp\Events\CfpClosed;
use PHPeste\Cfp\Events\CfpOpened;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Events\EditionStatusChanged;
use PHPeste\Community\Models\Edition;

beforeEach(function (): void {
    $this->travelTo('2027-02-01 12:00:00');
});

it('dispara CfpOpened quando a janela abre e CfpClosed quando fecha, uma vez cada', function (): void {
    Event::fake([CfpOpened::class, CfpClosed::class]);
    $edition = Edition::factory()->status(EditionStatus::CfpOpen)->create();
    CfpSetting::create(['edition_id' => $edition->id, 'opens_at' => '2027-03-01 00:00:00', 'closes_at' => '2027-04-15 00:00:00']);

    $this->artisan('cfp:sync-status')->assertSuccessful();
    Event::assertNothingDispatched();

    $this->travelTo('2027-03-01 00:00:30');
    $this->artisan('cfp:sync-status');
    $this->artisan('cfp:sync-status');
    Event::assertDispatchedTimes(CfpOpened::class, 1);
    Event::assertDispatched(CfpOpened::class, fn (CfpOpened $event): bool => $event->editionId === $edition->id);

    $this->travelTo('2027-04-15 00:01:00');
    $this->artisan('cfp:sync-status');
    $this->artisan('cfp:sync-status');
    Event::assertDispatchedTimes(CfpClosed::class, 1);
});

it('abre na hora em que a edição passa para CFP aberto dentro da janela', function (): void {
    Event::fake([CfpOpened::class]);
    $edition = Edition::factory()->create();
    CfpSetting::create(['edition_id' => $edition->id, 'opens_at' => '2027-01-01 00:00:00', 'closes_at' => '2027-04-15 00:00:00']);

    $this->artisan('cfp:sync-status');
    Event::assertNotDispatched(CfpOpened::class);

    $edition->update(['status' => EditionStatus::CfpOpen]);
    event(new EditionStatusChanged($edition->id, EditionStatus::Draft, EditionStatus::CfpOpen));

    Event::assertDispatched(CfpOpened::class, fn (CfpOpened $event): bool => $event->editionId === $edition->id);
});

it('está agendado para rodar a cada minuto', function (): void {
    $events = collect(resolve(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'cfp:sync-status'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('* * * * *');
});
