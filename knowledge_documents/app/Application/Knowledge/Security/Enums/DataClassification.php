<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\Enums;

enum DataClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Personal = 'personal';
    case Sensitive = 'sensitive';
    case Restricted = 'restricted';
}
