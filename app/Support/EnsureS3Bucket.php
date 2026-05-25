<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Guarantees the bucket backing a Laravel `s3`-driver filesystem
 * disk exists on the configured endpoint (MinIO locally, real S3
 * in production). Used by seeders + deployment scripts so a fresh
 * clone of the Sail stack doesn't need a manual `mc mb`.
 *
 * Runtime cost: one `listBuckets()` + conditional `createBucket()`
 * call against the endpoint. Safe to call repeatedly (idempotent).
 */
class EnsureS3Bucket
{
    /**
     * Ensure the bucket for `$disk` exists, creating it if missing.
     * Returns true when the bucket is present after the call (either
     * pre-existing or just created), false when the check itself
     * failed (endpoint down, bad credentials, etc.) — the caller
     * decides whether that's fatal.
     */
    public static function ensure(string $disk = 's3'): bool
    {
        $bucket = config("filesystems.disks.{$disk}.bucket");

        if (! $bucket) {
            Log::warning("EnsureS3Bucket: disk [{$disk}] has no bucket configured.");

            return false;
        }

        try {
            /** @var \Aws\S3\S3Client $client */
            $client = Storage::disk($disk)->getClient();

            $existing = collect($client->listBuckets()->get('Buckets') ?? [])
                ->pluck('Name')
                ->all();

            if (in_array($bucket, $existing, true)) {
                return true;
            }

            $client->createBucket(['Bucket' => $bucket]);

            return true;
        } catch (Throwable $e) {
            Log::warning("EnsureS3Bucket: failed to ensure [{$bucket}] on disk [{$disk}]: ".$e->getMessage());

            return false;
        }
    }
}
