<?php

namespace App\Enums;

enum RepoKeyKind: string
{
    case Person = 'person';
    case Birth = 'birth';
    case Death = 'death';
    case Relation = 'relation';
    case Marriage = 'marriage';
}
