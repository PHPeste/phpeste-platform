<?php

declare(strict_types=1);

namespace PHPeste\Branding\Enums;

enum LogoSlot: string
{
    case Primary = 'primary';
    case Dark = 'dark';
    case Favicon = 'favicon';
    case AppIcon = 'app_icon';

    /**
     * @return list<string>
     */
    public function rules(): array
    {
        return match ($this) {
            self::Primary, self::Dark => ['required', 'file', 'mimes:svg,png', 'max:1024'],
            self::Favicon => ['required', 'file', 'mimes:svg,png', 'max:512'],
            self::AppIcon => ['required', 'file', 'mimes:png', 'max:1024', 'dimensions:min_width=512,ratio=1'],
        };
    }
}
