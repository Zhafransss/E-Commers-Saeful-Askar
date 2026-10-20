<?php

namespace App\Enums;

enum WebhookProvider: string
{
    case Gapura = 'gapura';
    case Jubelio = 'jubelio';
}
