<?php

declare(strict_types=1);

namespace PHPeste\Branding\Enums;

enum ThemePreset: string
{
    case Sertao = 'sertao';
    case Mare = 'mare';
    case Brasa = 'brasa';

    public function label(): string
    {
        return match ($this) {
            self::Sertao => 'Sertão',
            self::Mare => 'Maré',
            self::Brasa => 'Brasa',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Sertao => 'Padrão da plataforma, tons terrosos',
            self::Mare => 'Azul escuro, para edições litorâneas',
            self::Brasa => 'Fundo escuro com laranja',
        };
    }

    /**
     * @return array<string, string>
     */
    public function colors(): array
    {
        return match ($this) {
            self::Sertao => [
                'bg' => '#FBF4E4', 'surface' => '#FFFBF2', 'surface_2' => '#F5EAD0', 'line' => '#E4D3AB',
                'ink' => '#3A1607', 'ink_2' => '#6F4A38', 'ink_3' => '#7F5A47', 'link' => '#9C4220',
                'brand' => '#6B2A12', 'on_brand' => '#FFF8E8', 'accent' => '#E8B544', 'on_accent' => '#3A1607', 'support' => '#B4532A',
                'ok' => '#4B6A24', 'ok_bg' => '#E6EBCF', 'warn' => '#8A5A00', 'warn_bg' => '#F8E7B9',
                'danger' => '#A12B1F', 'danger_bg' => '#F6D9D2', 'info' => '#2F5D6B', 'info_bg' => '#D9E7E9',
            ],
            self::Mare => [
                'bg' => '#0F1B2D', 'surface' => '#16273F', 'surface_2' => '#1D3250', 'line' => '#27415F',
                'ink' => '#F2F6FA', 'ink_2' => '#C3D0DE', 'ink_3' => '#A3B4C7', 'link' => '#7CC4F5',
                'brand' => '#2C78CA', 'on_brand' => '#FFFFFF', 'accent' => '#7CC4F5', 'on_accent' => '#0F1B2D', 'support' => '#2C78CA',
                'ok' => '#8FD18A', 'ok_bg' => '#183A2A', 'warn' => '#F2C46B', 'warn_bg' => '#3A2F14',
                'danger' => '#FF8F85', 'danger_bg' => '#3F1D24', 'info' => '#7CC4F5', 'info_bg' => '#173552',
            ],
            self::Brasa => [
                'bg' => '#12100E', 'surface' => '#1E1A17', 'surface_2' => '#29231F', 'line' => '#3A322B',
                'ink' => '#F4E8D3', 'ink_2' => '#CBBCA6', 'ink_3' => '#AB9C88', 'link' => '#FFB067',
                'brand' => '#FF7A1A', 'on_brand' => '#12100E', 'accent' => '#FFB067', 'on_accent' => '#12100E', 'support' => '#FF7A1A',
                'ok' => '#9CCC7A', 'ok_bg' => '#1F2A17', 'warn' => '#FFC861', 'warn_bg' => '#33270F',
                'danger' => '#FF8A7A', 'danger_bg' => '#3A1A16', 'info' => '#8CC4D8', 'info_bg' => '#16282E',
            ],
        };
    }

    /**
     * @return array{colors: array<string, string>, fonts: array{heading: string, body: string}, radius: int}
     */
    public function theme(): array
    {
        return [
            'colors' => $this->colors(),
            'fonts' => ['heading' => ThemeFont::Lexend->value, 'body' => ThemeFont::Lexend->value],
            'radius' => 10,
        ];
    }
}
