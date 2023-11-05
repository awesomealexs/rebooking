<?php

namespace App\Enum;

enum PostStatus
{
    case draft;
    case publish;
    case inherit;
}