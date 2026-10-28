<?php

return [
    // Disk for public product/banner images. Use "s3" (S3-compatible) in production; "public" locally.
    'media_disk' => env('MEDIA_DISK', 'public'),

    // This store has no email service yet. While false, NOTHING is ever sent (order mails, wholesale decisions, quotes,
    // reminders, password reset) and the screens that depend on email are hidden. Set STORE_MAIL_ENABLED=true once SMTP works.
    'mail_enabled' => (bool) env('STORE_MAIL_ENABLED', false),
];
