<?php

return [
    // Disk for public product/banner images. Use "s3" (S3-compatible) in production; "public" locally.
    'media_disk' => env('MEDIA_DISK', 'public'),
];
