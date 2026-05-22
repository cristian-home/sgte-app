<?php

use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

it('backs up only the database to MinIO and off-site R2', function () {
    expect(config('backup.backup.destination.disks'))->toBe(['backups', 'r2'])
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

it('caps stored backups well under the R2 free tier', function () {
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

it('defines the R2 off-site disk as an S3 endpoint', function () {
    expect(config('filesystems.disks.r2.driver'))->toBe('s3')
        ->and(config('filesystems.disks.r2.use_path_style_endpoint'))->toBeTrue()
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
