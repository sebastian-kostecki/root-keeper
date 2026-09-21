<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

test('production backups include the database and storage and run at 03:45 Warsaw', function () {
    expect(config('backup.backup.source.files.include'))->toBe([storage_path('app')])
        ->and(config('backup.backup.source.files.exclude'))->toContain(storage_path('app/backups'))
        ->and(config('backup.backup.source.databases'))->toBe([config('database.default')])
        ->and(config('backup.backup.destination.disks'))->toContain('backups');

    $byCommand = collect($this->app->make(Schedule::class)->events())
        ->keyBy(fn (Event $event): string => match (true) {
            str_contains((string) $event->command, 'backup:run') => 'backup:run',
            str_contains((string) $event->command, 'backup:clean') => 'backup:clean',
            str_contains((string) $event->command, 'backup:monitor') => 'backup:monitor',
            default => (string) $event->command,
        });

    expect($byCommand->get('backup:run'))->toBeInstanceOf(Event::class)
        ->and($byCommand->get('backup:run')->expression)->toBe('45 3 * * *')
        ->and((string) $byCommand->get('backup:run')->timezone)->toBe('Europe/Warsaw')
        ->and($byCommand->get('backup:run')->environments)->toBe(['production']);

    expect($byCommand->get('backup:clean'))->toBeInstanceOf(Event::class)
        ->and($byCommand->get('backup:clean')->expression)->toBe('15 4 * * *')
        ->and((string) $byCommand->get('backup:clean')->timezone)->toBe('Europe/Warsaw')
        ->and($byCommand->get('backup:clean')->environments)->toBe(['production']);

    expect($byCommand->get('backup:monitor'))->toBeInstanceOf(Event::class)
        ->and($byCommand->get('backup:monitor')->expression)->toBe('45 4 * * *')
        ->and((string) $byCommand->get('backup:monitor')->timezone)->toBe('Europe/Warsaw')
        ->and($byCommand->get('backup:monitor')->environments)->toBe(['production']);
});
