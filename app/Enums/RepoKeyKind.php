<?php

namespace App\Enums;

enum RepoKeyKind: string
{
    case Person = 'person';
    case Birth = 'birth';
    case Death = 'death';
    case Burial = 'burial';
    case Relation = 'relation';
    case Marriage = 'marriage';
    case Source = 'source';
}
