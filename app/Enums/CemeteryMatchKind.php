<?php

namespace App\Enums;

/**
 * How a cemetery of the repo fits the cemeteries of its locality in the tree.
 */
enum CemeteryMatchKind: string
{
    case Sure = 'sure';
    case Choice = 'choice';
    case New = 'new';
    case None = 'none';
}
