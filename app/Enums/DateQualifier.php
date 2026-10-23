<?php

namespace App\Enums;

enum DateQualifier: string
{
    case Exact = 'exact';
    case Approximate = 'approximate';
    case Before = 'before';
    case After = 'after';
    case Range = 'range';
}
