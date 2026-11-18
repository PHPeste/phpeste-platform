<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use PHPeste\Cfp\Enums\CfpStatus;
use PHPeste\Cfp\Models\CfpSetting;

it('deriva o status do CFP das datas e do estado da edição', function (?string $opens, ?string $closes, bool $editionInCfp, string $now, CfpStatus $expected): void {
    $settings = new CfpSetting(['opens_at' => $opens, 'closes_at' => $closes]);

    expect($settings->statusFor($editionInCfp, Date::parse($now)))->toBe($expected);
})->with([
    'sem datas' => [null, null, true, '2027-03-01', CfpStatus::Draft],
    'antes de abrir, edição em CFP' => ['2027-03-01', '2027-04-15', true, '2027-02-01', CfpStatus::Scheduled],
    'antes de abrir, edição em rascunho' => ['2027-03-01', '2027-04-15', false, '2027-02-01', CfpStatus::Draft],
    'na janela, edição em CFP' => ['2027-03-01', '2027-04-15', true, '2027-03-10', CfpStatus::Open],
    'na janela, edição ainda em rascunho' => ['2027-03-01', '2027-04-15', false, '2027-03-10', CfpStatus::Draft],
    'exatamente no fechamento' => ['2027-03-01', '2027-04-15', true, '2027-04-15', CfpStatus::Closed],
    'depois de fechar' => ['2027-03-01', '2027-04-15', false, '2027-05-01', CfpStatus::Closed],
]);
