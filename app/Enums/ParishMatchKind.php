<?php

namespace App\Enums;

/**
 * How a parish of a source of the repo fits the parishes of its seat in the tree.
 */
enum ParishMatchKind: string
{
    case Sure = 'sure';
    case Choice = 'choice';
    case New = 'new';
    case None = 'none';
}
