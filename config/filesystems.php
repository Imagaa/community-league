<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         * 'local' di dev/CI/test (bucket R2 tidak pernah dipanggil), 's3' di staging/produksi
         * lewat PUBLIC_DISK=s3 di .env server -- disk R2 (Cloudflare, S3-compatible) dikonfigurasi
         * lewat variabel AWS_* yang sama seperti disk 's3' bawaan Laravel di bawah. Bucket R2
         * SENGAJA tidak dibuat publik, jadi semua kode baca file lewat
         * Storage::disk('public')->temporaryUrl(), bukan ->url() -- presigned URL, bukan URL
         * bucket langsung. 'serve' => true di bawah supaya temporaryUrl() juga jalan native di
         * disk 'local' (fitur bawaan Laravel -- signed route storage.public, lihat
         * LocalFilesystemAdapter::temporaryUrl(), tidak perlu callback custom).
         */
        'public' => array_merge([
            'driver' => env('PUBLIC_DISK', 'local'),
            'visibility' => 'public',
            'serve' => true,
            'throw' => false,
            'report' => false,
        ], env('PUBLIC_DISK', 'local') === 'local' ? [
            'root' => storage_path('app/public'),
            // '/media', BUKAN '/storage' -- disk 'local' (di atas) sudah pakai '/storage' untuk
            // serve route-nya sendiri (dipakai Livewire buat file upload sementara); dua disk
            // serveable tidak boleh berbagi URI yang sama (Laravel melempar exception kalau
            // konflik). Path style ini juga bukan symlink public/storage lama -- semua kode baca
            // lewat temporaryUrl(), jadi prefix-nya boleh apa saja selama konsisten.
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/media',
        ] : [
            // TIDAK ada 'root' di sini -- kalau ikut diisi storage_path() seperti cabang local,
            // Flysystem S3 adapter membacanya sebagai path-prefix DI DALAM bucket (bukan sekadar
            // penanda direktori lokal), hasilnya semua key R2 kepentok prefix aneh
            // "/app/storage/app/public/..." alih-alih bersih dari root bucket.
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ]),

        // Bukti bayar -- TIDAK pernah diberi URL publik, hanya dialirkan lewat rute admin.
        // ponytail: di R2 memakai bucket yang sama dengan 'public' (nama file acak 24 karakter)
        // sampai PRIVATE_BUCKET diisi dengan bucket R2 terpisah tanpa akses publik.
        'proofs' => env('PUBLIC_DISK', 'local') === 'local' ? [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'throw' => false,
            'report' => false,
        ] : [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('PRIVATE_BUCKET', env('AWS_BUCKET')),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
