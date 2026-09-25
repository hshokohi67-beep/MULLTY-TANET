<?php

return [

    // "local" in development; an S3-compatible bucket in production (same shape as MEDIA_DISK).
    'disk' => env('BACKUP_DISK', 'local'),

    // The database connection to dump (defaults to the app's default connection).
    'connection' => env('BACKUP_CONNECTION'),

    // Backups older than this are pruned by backup:run.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

];
