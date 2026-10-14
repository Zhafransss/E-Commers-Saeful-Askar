<?php

namespace App\Enums;

/**
 * "Scheduled" is not a stored status: it is `published` with published_at in the future.
 */
enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
