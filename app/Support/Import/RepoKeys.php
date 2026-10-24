<?php

namespace App\Support\Import;

use App\Enums\RepoKeyKind;
use InvalidArgumentException;

/**
 * The shape of the keys that identify a repo record: `repo_id` of an imported record and the key of a repo snapshot.
 *
 * - person: `O-0042`
 * - birth and death: `O-0042/urodzenie`, `O-0042/zgon`
 * - parent-child pair: `O-0084>O-0042` (parent, then child)
 * - wedding: `O-0001+O-0004/slub` (the sorted pair of persons)
 */
final class RepoKeys
{
    public static function person(string $id): string
    {
        return $id;
    }

    public static function birth(string $id): string
    {
        return $id.'/urodzenie';
    }

    public static function death(string $id): string
    {
        return $id.'/zgon';
    }

    public static function relation(string $parentId, string $childId): string
    {
        return $parentId.'>'.$childId;
    }

    public static function marriage(string $firstId, string $secondId): string
    {
        return $firstId.'+'.$secondId.'/slub';
    }

    public static function kindOf(string $key): RepoKeyKind
    {
        return match (true) {
            str_ends_with($key, '/urodzenie') => RepoKeyKind::Birth,
            str_ends_with($key, '/zgon') => RepoKeyKind::Death,
            str_ends_with($key, '/slub') => RepoKeyKind::Marriage,
            str_contains($key, '>') => RepoKeyKind::Relation,
            default => RepoKeyKind::Person,
        };
    }

    /**
     * The repo ids of the persons a key names: one for a person, a birth and a death, two for a pair and a wedding.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException when the key of a pair or a wedding does not hold two ids
     */
    public static function personIdsOf(string $key): array
    {
        $kind = self::kindOf($key);
        $ids = match ($kind) {
            RepoKeyKind::Person => [$key],
            RepoKeyKind::Birth => [substr($key, 0, -strlen('/urodzenie'))],
            RepoKeyKind::Death => [substr($key, 0, -strlen('/zgon'))],
            RepoKeyKind::Relation => explode('>', $key),
            RepoKeyKind::Marriage => explode('+', substr($key, 0, -strlen('/slub'))),
        };

        if (in_array($kind, [RepoKeyKind::Relation, RepoKeyKind::Marriage], true) && count($ids) !== 2) {
            throw new InvalidArgumentException("Klucz repo {$key} nie wskazuje dwóch osób.");
        }

        return $ids;
    }
}
