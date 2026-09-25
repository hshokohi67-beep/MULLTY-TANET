<?php

return [

    // "local" in development; an S3-compatible bucket in production (same shape as MEDIA_DISK).
    'disk' => env('BACKUP_DISK', 'local'),

    // Backups older than this are pruned by backup:run.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

];
