<x-layouts::legal :title="__('Syarat & Ketentuan')">
    <h1>Syarat &amp; Ketentuan</h1>
    <p>Berlaku sejak {{ \App\Models\User::TERMS_VERSION }}. Dengan mendaftar atau menggunakan {{ config('app.name') }},
        Anda menyetujui syarat dan ketentuan berikut.</p>

    <h2>1. Definisi &amp; Ruang Lingkup Layanan</h2>
    <p>{{ config('app.name') }} adalah platform penyelenggara match sepak bola dan mini soccer yang mempertemukan
        pemain dengan jadwal pertandingan. {{ config('app.name') }} <strong>bukan penyewa lapangan</strong> dan
        <strong>bukan penyedia jasa keuangan</strong>. Pembayaran yang diterima adalah pembayaran atas slot
        keikutsertaan dalam match, bukan simpanan atau produk keuangan.</p>

    <h2>2. Akun</h2>
    <ul>
        <li>Batas usia minimum pengguna adalah <strong>10 tahun</strong>. Pengguna di bawah usia 18 tahun wajib
            didaftarkan dan didampingi oleh orang tua atau wali yang bertanggung jawab penuh atas keikutsertaannya.</li>
        <li>Satu orang hanya boleh memiliki satu akun.</li>
        <li>Anda bertanggung jawab menjaga kerahasiaan kata sandi dan seluruh aktivitas yang terjadi di akun Anda.</li>
    </ul>

    <h2>3. Pemesanan &amp; Pembayaran</h2>
    <ul>
        <li>Harga yang ditampilkan saat pemesanan adalah harga yang berlaku dan mengikat.</li>
        <li>Setiap pemesanan mendapat <strong>nominal transfer unik</strong> dan masa tahan (<em>hold</em>) slot
            selama <strong>30 menit</strong> untuk menyelesaikan pembayaran.</li>
        <li>Verifikasi pembayaran diproses maksimum <strong>1 jam</strong> pada pukul 08.00–22.00 WIB; di luar jam
            tersebut diproses pada pagi hari berikutnya.</li>
        <li>Nominal transfer <strong>kurang</strong>: pembayaran ditolak, dana yang sudah masuk dikreditkan ke
            wallet (atau dikembalikan manual untuk tamu tanpa akun).</li>
        <li>Nominal transfer <strong>lebih</strong>: pembayaran diterima, selisih dikreditkan ke wallet.</li>
        <li>Transfer <strong>setelah masa tahan berakhir</strong>: slot sudah dilepas ke pengguna lain; dana yang
            masuk dikreditkan ke wallet.</li>
        <li>Transfer <strong>dua kali</strong>: satu diterima, kelebihannya dikreditkan ke wallet.</li>
        <li>Bukti pembayaran diverifikasi dengan mencocokkan mutasi rekening sungguhan, bukan hanya tampilan
            gambar bukti transfer.</li>
    </ul>

    <h2>4. Pembatalan oleh Pengguna</h2>
    <p>Pembatalan hanya dapat dilakukan paling lambat <strong>7 hari (H-7)</strong> sebelum jadwal match dimulai.
        Dana dikembalikan sebagai saldo wallet, <strong>bukan uang tunai</strong>.</p>

    <h2>5. Wallet</h2>
    <p><strong>Saldo wallet bukan uang sungguhan.</strong> Saldo wallet adalah poin/kredit internal yang hanya
        berlaku untuk transaksi di dalam {{ config('app.name') }} — bukan alat pembayaran yang sah dan bukan
        instrumen keuangan. Saldo <strong>tidak dapat ditarik tunai, diuangkan, atau dipindahtangankan</strong> ke
        pihak lain, dan saat ini <strong>tidak memiliki masa berlaku</strong>.</p>
    <p>Bila Anda menghapus akun secara mandiri, <strong>sisa saldo wallet ikut terhapus</strong> bersama akun dan
        tidak dapat dikembalikan.</p>

    <h2>6. Pembatalan oleh Penyelenggara</h2>
    <p>Bila match dibatalkan oleh penyelenggara (misalnya karena cuaca atau kondisi lapangan), seluruh peserta
        yang sudah membayar menerima <strong>saldo penuh ke wallet</strong> ditambah <strong>poin kompensasi</strong>
        sebagai permintaan maaf. Notifikasi dikirim lewat email, aplikasi, dan push. Dalam kondisi tertentu,
        <strong>Super Admin berwenang mengembalikan dana secara tunai</strong> atas permintaan peserta.</p>

    <h2>7. Membership</h2>
    <p>Membership berlaku <strong>bulanan</strong> dan <strong>tidak diperpanjang otomatis</strong> — pembayaran
        ulang dilakukan secara sadar oleh pengguna setiap periode. Tidak ada pengembalian dana secara
        proporsional (<em>pro-rata</em>) bila membership dihentikan di tengah periode. Benefit membership meliputi
        harga slot lebih murah, akumulasi poin, dan prioritas pemesanan serta prioritas daftar tunggu sebelum
        match dibuka untuk umum.</p>

    <h2>8. Poin</h2>
    <p>Poin diperoleh setelah <strong>check-in</strong> pada match, dari program referral, bonus membership, dan
        kompensasi pembatalan oleh penyelenggara. Poin <strong>tidak dapat diuangkan</strong>,
        <strong>tidak dapat dipindahtangankan</strong>, dan <strong>tidak kedaluwarsa</strong>. Poin dapat
        dibatalkan sewaktu-waktu bila terbukti diperoleh dengan cara curang.</p>

    <h2>9. Penukaran Hadiah</h2>
    <p>Penukaran poin dengan hadiah tunduk pada persetujuan admin dan ketersediaan stok. Penyerahan barang dan
        biaya pengiriman (bila ada) diatur secara terpisah per kasus dan dikomunikasikan langsung kepada pengguna.</p>

    <h2>10. Risiko Cedera</h2>
    <p><strong>Peserta bermain atas risiko sendiri.</strong> {{ config('app.name') }} tidak bertanggung jawab atas
        cedera, kehilangan, atau kerusakan barang yang terjadi selama kegiatan berlangsung. Peserta disarankan
        memastikan kondisi kesehatannya sendiri layak untuk mengikuti aktivitas fisik sebelum bermain.</p>

    <h2>11. Hak Citra &amp; Dokumentasi</h2>
    <p>Dengan mengikuti match, peserta menyetujui untuk difoto dan/atau direkam, dan dokumentasi tersebut dapat
        dipublikasikan oleh penyelenggara melalui kanal resmi {{ config('app.name') }}. Peserta yang berkeberatan
        dapat mengajukan permintaan penghapusan dokumentasi melalui kontak yang tercantum di bagian 15.</p>

    <h2>12. Perilaku &amp; Sanksi</h2>
    <p>Pengguna dilarang melakukan kecurangan, pelecehan, atau tindakan yang membahayakan pengguna lain maupun
        penyelenggara. Pelanggaran dapat berakibat peringatan, pembatalan booking, hingga pemblokiran akun secara
        permanen, sesuai tingkat keparahan pelanggaran.</p>

    <h2>13. Data Pribadi</h2>
    <p>Pengumpulan dan penggunaan data pribadi Anda diatur dalam
        <a href="{{ route('legal.privacy') }}" wire:navigate>Kebijakan Privasi</a> kami, yang merupakan bagian
        tidak terpisahkan dari Syarat &amp; Ketentuan ini.</p>

    <h2>14. Perubahan Syarat</h2>
    <p>Kami dapat mengubah Syarat &amp; Ketentuan ini dari waktu ke waktu. Perubahan yang bersifat material akan
        diberitahukan, dan Anda akan diminta menyetujui ulang versi terbaru saat login berikutnya sebelum dapat
        melanjutkan menggunakan layanan.</p>

    <h2>15. Kontak &amp; Penyelesaian Sengketa</h2>
    <p>Syarat &amp; Ketentuan ini tunduk pada hukum Republik Indonesia. Pertanyaan, keluhan, atau sengketa dapat
        disampaikan melalui <a href="mailto:halo@communityleague.id">halo@communityleague.id</a>.</p>
</x-layouts::legal>
