<?php

namespace App\Enums;

/**
 * How a place name of the repo fits the tree's list of localities.
 */
enum PlaceMatchKind: string
{
    case Sure = 'sure';
    case Doubtful = 'doubtful';
    case New = 'new';
}
