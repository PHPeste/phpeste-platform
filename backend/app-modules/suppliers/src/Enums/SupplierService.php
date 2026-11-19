<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Enums;

enum SupplierService: string
{
    case Venue = 'venue';
    case Catering = 'catering';
    case AudioVisual = 'audio_visual';
    case Printing = 'printing';
    case Merch = 'merch';
    case Transport = 'transport';
    case Accommodation = 'accommodation';
    case PhotoVideo = 'photo_video';
    case Accessibility = 'accessibility';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Venue => 'Local',
            self::Catering => 'Alimentação',
            self::AudioVisual => 'Som, luz e vídeo',
            self::Printing => 'Gráfica',
            self::Merch => 'Brindes e camisetas',
            self::Transport => 'Transporte',
            self::Accommodation => 'Hospedagem',
            self::PhotoVideo => 'Foto e filmagem',
            self::Accessibility => 'Acessibilidade (Libras, legenda)',
            self::Other => 'Outro',
        };
    }
}
