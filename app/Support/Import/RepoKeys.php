<?php

namespace App\Support\Import;

use App\Enums\RepoKeyKind;
use InvalidArgumentException;

/**
 * The shape of the keys that identify a repo record: `repo_id` of an imported record and the key of a repo snapshot.
 *
 * - person: `O-0042`
 * - birth, death and burial: `O-0042/urodzenie`, `O-0042/zgon`, `O-0042/pochowek`
 * - parent-child pair: `O-0084>O-0042` (parent, then child)
 * - wedding: `O-0001+O-0004/slub` (the sorted pair of persons)
 * - source: `ZR-0012` (a scan with its description; names no person)
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

    public static function burial(string $id): string
    {
        return $id.'/pochowek';
    }

    public static function relation(string $parentId, string $childId): string
    {
        return $parentId.'>'.$childId;
    }

    public static function marriage(string $firstId, string $secondId): string
    {
        return $firstId.'+'.$secondId.'/slub';
    }

    public static function source(string $id): string
    {
        return $id;
    }

    public static function kindOf(string $key): RepoKeyKind
    {
        return match (true) {
            str_ends_with($key, '/urodzenie') => RepoKeyKind::Birth,
            str_ends_with($key, '/zgon') => RepoKeyKind::Death,
            str_ends_with($key, '/pochowek') => RepoKeyKind::Burial,
            str_ends_with($key, '/slub') => RepoKeyKind::Marriage,
            str_contains($key, '>') => RepoKeyKind::Relation,
            str_starts_with($key, 'ZR-') => RepoKeyKind::Source,
            default => RepoKeyKind::Person,
        };
    }

    /**
     * The repo ids of the persons a key names: one for a person, a birth, a death and a burial, two for a pair and a wedding, none for a source.
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
            RepoKeyKind::Burial => [substr($key, 0, -strlen('/pochowek'))],
            RepoKeyKind::Relation => explode('>', $key),
            RepoKeyKind::Marriage => explode('+', substr($key, 0, -strlen('/slub'))),
            RepoKeyKind::Source => [],
        };

        if (in_array($kind, [RepoKeyKind::Relation, RepoKeyKind::Marriage], true) && count($ids) !== 2) {
            throw new InvalidArgumentException("Klucz repo {$key} nie wskazuje dwóch osób.");
        }

        return $ids;
    }
}
