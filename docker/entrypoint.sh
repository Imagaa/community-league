#!/bin/sh
set -e

# Bersihkan cache lama, lalu bangun ulang untuk konfigurasi runtime saat ini.
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link || true

# Scheduler tidak pernah benar-benar jalan sebelumnya -- container cuma menjalankan FrankenPHP,
# tidak ada apa pun yang memanggil `php artisan schedule:run`. `bookings:expire-holds` sudah jadi
# dead code sejak awal. Background schedule:work di sini (proses persisten yang tidur sampai menit
# berikutnya, bukan perlu cron eksternal) supaya jadwal di routes/console.php benar-benar jalan.
php artisan schedule:work &

exec frankenphp php-server --root /app/public --listen "${SERVER_NAME}"
