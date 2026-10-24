<x-layouts::legal :title="__('Kebijakan Privasi')">
    <h1>Kebijakan Privasi</h1>
    <p>Berlaku sejak {{ \App\Models\User::TERMS_VERSION }}. Kebijakan ini menjelaskan bagaimana
        {{ config('app.name') }} mengumpulkan, menggunakan, dan melindungi data pribadi Anda, sesuai dengan
        Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi.</p>

    <h2>1. Data yang Kami Kumpulkan</h2>
    <ul>
        <li><strong>Data akun:</strong> nama, alamat email, nomor HP, kata sandi (tersimpan terenkripsi).</li>
        <li><strong>Data login pihak ketiga:</strong> nama, email, dan foto profil bila Anda masuk lewat Google.</li>
        <li><strong>Data transaksi:</strong> riwayat pemesanan, pembayaran, saldo wallet, dan poin.</li>
        <li><strong>Data kehadiran:</strong> waktu check-in pada match yang Anda ikuti.</li>
        <li><strong>Data teknis:</strong> alamat IP dan informasi perangkat, untuk keamanan dan pencegahan
            penyalahgunaan.</li>
    </ul>

    <h2>2. Dasar dan Tujuan Pemrosesan</h2>
    <p>Data diproses berdasarkan <strong>persetujuan Anda</strong> saat mendaftar dan
        <strong>pelaksanaan perjanjian layanan</strong> (Syarat &amp; Ketentuan), untuk keperluan: membuat dan
        mengelola akun, memproses pemesanan dan pembayaran, mengirim notifikasi terkait match dan e-ticket,
        mencegah kecurangan, serta memenuhi kewajiban hukum yang berlaku.</p>

    <h2>3. Pihak Ketiga yang Menerima Data</h2>
    <p>Kami menggunakan sejumlah penyedia layanan pihak ketiga untuk menjalankan platform ini. Data dibagikan
        hanya sebatas yang diperlukan masing-masing layanan:</p>
    <ul>
        <li><strong>Resend</strong> — pengiriman email (verifikasi akun, e-ticket, notifikasi).</li>
        <li><strong>Cloudflare</strong> — keamanan jaringan dan pencegahan spam/bot (Turnstile).</li>
        <li><strong>Google</strong> — autentikasi login (bila Anda memilih masuk dengan Google).</li>
        <li><strong>Penyedia hosting</strong> — penyimpanan data aplikasi dan basis data.</li>
        <li>Penyedia pembayaran/perbankan terkait, sebatas untuk rekonsiliasi transfer QRIS manual.</li>
    </ul>
    <p>Kami tidak menjual data pribadi Anda kepada pihak mana pun.</p>

    <h2>4. Lama Penyimpanan</h2>
    <p>Data akun disimpan selama akun Anda aktif. Data transaksi dan riwayat wallet/poin disimpan lebih lama
        sesuai kebutuhan pembukuan dan kewajiban hukum, meskipun akun sudah dihapus, kecuali diwajibkan sebaliknya
        oleh hukum yang berlaku.</p>

    <h2>5. Hak Anda sebagai Subjek Data</h2>
    <ul>
        <li><strong>Akses</strong> — meminta salinan data pribadi yang kami simpan tentang Anda.</li>
        <li><strong>Koreksi</strong> — memperbarui data yang tidak akurat lewat menu Pengaturan &gt; Profil.</li>
        <li><strong>Penghapusan</strong> — menghapus akun Anda secara mandiri kapan saja lewat menu
            Pengaturan &gt; Profil, atau dengan menghubungi kami. Perlu diketahui: <strong>sisa saldo wallet ikut
            terhapus</strong> bersama akun dan tidak dapat dikembalikan — lihat Syarat &amp; Ketentuan bagian 5.</li>
        <li><strong>Penarikan persetujuan</strong> — menghentikan penggunaan layanan dan meminta penghapusan data
            sesuai ketentuan penyimpanan pada bagian 4.</li>
    </ul>

    <h2>6. Cookie</h2>
    <p>Kami menggunakan cookie sesi yang diperlukan agar Anda tetap masuk (login) saat menggunakan aplikasi, serta
        cookie keamanan dari Cloudflare Turnstile untuk mencegah bot. Kami tidak menggunakan cookie untuk iklan
        pihak ketiga. Persetujuan atas penggunaan cookie ini diminta lewat notifikasi saat Anda pertama kali
        membuka {{ config('app.name') }}.</p>

    <h2>7. Keamanan Data</h2>
    <p>Kata sandi disimpan dalam bentuk terenkripsi (hashed) dan tidak pernah disimpan dalam bentuk teks biasa.
        Seluruh komunikasi antara perangkat Anda dan server kami dilindungi dengan HTTPS.</p>

    <h2>8. Perubahan Kebijakan</h2>
    <p>Kami dapat memperbarui Kebijakan Privasi ini dari waktu ke waktu. Perubahan yang bersifat material akan
        diberitahukan kepada Anda.</p>

    <h2>9. Kontak</h2>
    <p>Pertanyaan seputar privasi dan data pribadi Anda dapat disampaikan melalui
        <a href="mailto:halo@communityleague.id">halo@communityleague.id</a>.</p>
</x-layouts::legal>
