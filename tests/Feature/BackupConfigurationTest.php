<?php

use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

it('backs up only the database to the local and off-site S3 buckets', function () {
    expect(config('backup.backup.destination.disks'))->toBe(['backups', 'backup_s3'])
        ->and(config('backup.backup.destination.continue_on_failure'))->toBeTrue()
        ->and(config('backup.backup.source.files.include'))->toBe([])
        ->and(config('backup.backup.source.databases'))->toBe([config('database.default')]);
});

it('encrypts and verifies every backup archive', function () {
    expect(config('backup.backup.encryption'))->toBe('default')
        ->and(config('backup.backup.password'))->toBe(env('BACKUP_ARCHIVE_PASSWORD'))
        ->and(config('backup.backup.verify_backup'))->toBeTrue()
        ->and(config('backup.backup.database_dump_file_extension'))->toBe('backup');
});

it('caps stored backups well under the off-site free tier', function () {
    expect(config('backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than'))
        ->toBe(8000)
        ->and(config('backup.monitor_backups.0.health_checks'))
        ->toHaveKey(MaximumStorageInMegabytes::class, 8000);
});

it('mails failure alerts but stays silent on success', function () {
    $notifications = config('backup.notifications.notifications');

    expect($notifications[BackupHasFailedNotification::class])->toBe(['mail'])
        ->and($notifications[UnhealthyBackupWasFoundNotification::class])->toBe(['mail'])
        ->and($notifications[CleanupHasFailedNotification::class])->toBe(['mail'])
        ->and($notifications[BackupWasSuccessfulNotification::class])->toBe([])
        ->and($notifications[HealthyBackupWasFoundNotification::class])->toBe([]);
});

it('defines the off-site disk as an S3 endpoint', function () {
    expect(config('filesystems.disks.backup_s3.driver'))->toBe('s3')
        ->and(config('filesystems.disks.backup_s3.use_path_style_endpoint'))->toBeTrue()
        ->and(config('filesystems.disks.backups.driver'))->toBe('s3')
        ->and(config('filesystems.disks.backups.bucket'))->not->toBeEmpty();
});

it('dumps PostgreSQL in a restorable binary format', function () {
    expect(config('database.connections.pgsql.dump.add_extra_option'))->toBe('--format=c')
        ->and(config('database.connections.pgsql.dump.timeout'))->toBeGreaterThan(60);
});

it('schedules the backup, clean and monitor commands', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('backup:run')
        ->expectsOutputToContain('backup:clean')
        ->expectsOutputToContain('backup:monitor')
        ->assertExitCode(0);
});
