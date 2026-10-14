<?php

namespace App\Enums;

enum WebhookProvider: string
{
    case Xendit = 'xendit';
    case Jubelio = 'jubelio';
}
