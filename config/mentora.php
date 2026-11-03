<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disk Penyimpanan Dokumen Kredensial Mentor
    |--------------------------------------------------------------------------
    | Default 'local' (storage/app/private, tidak dapat diakses publik).
    | Ganti ke 's3' (atau disk lain yang terdaftar di config/filesystems.php)
    | lewat CREDENTIAL_DISK di .env untuk memakai cloud storage (TR-07).
    */
    'credential_disk' => env('CREDENTIAL_DISK', 'local'),

];
