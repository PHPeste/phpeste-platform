<?php

declare(strict_types=1);

namespace PHPeste\Community\Enums;

enum EditionRole: string
{
    case Organizer = 'organizador';
    case Finance = 'financeiro';
    case Reviewer = 'avaliador';
    case CheckIn = 'checkin';
    case Volunteer = 'voluntario';
}
