<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Data;

use PHPeste\Cfp\Enums\ProposalFormat;
use PHPeste\Cfp\Enums\ProposalLanguage;
use PHPeste\Cfp\Enums\ProposalLevel;

final class ProposalForm
{
    public const int TITLE_MAX = 90;

    public const int SUMMARY_MAX = 3_000;

    public const int AUDIENCE_MAX = 1_000;

    /**
     * @return array{formats: list<array{id: string, label: string, minutes: int}>, levels: list<array{id: string, label: string}>, languages: list<array{id: string, label: string}>, limits: array{title: int, summary: int, audience: int}}
     */
    public static function options(): array
    {
        return [
            'formats' => array_map(fn (ProposalFormat $format): array => ['id' => $format->value, 'label' => $format->label(), 'minutes' => $format->minutes()], ProposalFormat::cases()),
            'levels' => array_map(fn (ProposalLevel $level): array => ['id' => $level->value, 'label' => $level->label()], ProposalLevel::cases()),
            'languages' => array_map(fn (ProposalLanguage $language): array => ['id' => $language->value, 'label' => $language->label()], ProposalLanguage::cases()),
            'limits' => ['title' => self::TITLE_MAX, 'summary' => self::SUMMARY_MAX, 'audience' => self::AUDIENCE_MAX],
        ];
    }
}
