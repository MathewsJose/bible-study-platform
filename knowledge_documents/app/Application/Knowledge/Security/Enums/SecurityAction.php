<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\Enums;

enum SecurityAction: string
{
    case Allow = 'allow';
    case Redact = 'redact';
    case Block = 'block';
}
