<?php
require_once __DIR__ . '/includes/auth.php';   // e(), is_admin(), csrf_token()…
$page_title = 'Tentang Kami';
$tahunKini  = date('Y');
?>
<!doctype html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title) ?> · <?= APP_NAME ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <!-- Font Awesome 6 (seluruh ikon halaman ini) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: {
      fontFamily: {
        sans: ['"DM Sans"', 'Segoe UI', 'system-ui', 'sans-serif'],
        hero: ['"DM Sans"', 'Segoe UI', 'system-ui', 'sans-serif'],
      },
      colors: { primary: {50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',400:'#60a5fa',
                          500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',900:'#1e3a8a'} },
      boxShadow: { card: '0 8px 24px -10px rgb(15 23 42 / .15)' }
    } } };
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= is_file(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : '1' ?>">
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased">

<!-- ============ NAVIGASI ============ -->
<nav class="sticky top-0 z-50 border-b border-slate-200/80 bg-slate-100/90 backdrop-blur">
  <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 md:px-8">

    <!-- Logo kiri -->
    <a href="index.php" class="flex flex-none items-center gap-2.5 text-lg font-extrabold tracking-tight">
      <img src="assets/img/logo.png" alt="Logo BPS Kab. Halut"
           class="w-10 h-10">
      <div class="leading-tight">
          <div class="font-bold text-slate-900">Badan Pusat Statistik</div>
          <div class="text-[11px] text-slate-500 -mt-0.5">Kabupaten Halmahera Utara</div>
      </div>
    </a>

    <!-- Menu tengah (desktop) -->
    <div class="hidden flex-1 items-center justify-center gap-1 text-sm font-semibold text-slate-700 lg:flex">
      <a href="index.php" class="rounded-lg px-3 py-2 transition hover:bg-white hover:text-slate-900">Beranda</a>
      <a href="index.php#shped" class="rounded-lg px-3 py-2 transition hover:bg-white hover:text-slate-900">Informasi SHPED</a>
      <a href="index.php#btnMetadata" class="rounded-lg px-3 py-2 transition hover:bg-white hover:text-slate-900">Metadata</a>
      <a href="tentang-kami.php" class="rounded-lg bg-white px-3 py-2 text-slate-900 shadow-sm ring-1 ring-slate-200" aria-current="page">Tentang Kami</a>
    </div>

    <!-- Kanan: lokasi tombol unduh buku panduan -->
    <div class="ml-auto hidden items-center gap-4 md:flex">
      <a href="docs/Buku_Panduan_Deteksi_Data_Tidur.pdf" download
         class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-slate-700">
        <i class="fa-solid fa-book-open mr-2 text-xs" aria-hidden="true"></i>Unduh Buku Panduan
      </a>
    </div>

    <!-- Tombol menu mobile -->
    <button id="btnMenu" class="ml-auto rounded-lg p-2 text-slate-600 hover:bg-white md:hidden" aria-label="Buka menu">
      <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
  </div>

  <!-- Menu mobile -->
  <div id="mobileMenu" class="hidden border-t border-slate-200 bg-slate-100 px-4 py-3 md:hidden">
    <a href="index.php" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-600">Beranda</a>
    <a href="index.php#shped" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-600">Informasi SHPED</a>
    <a href="index.php#btnMetadata" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-600">Metadata</a>
    <a href="docs/Buku_Panduan_Deteksi_Data_Tidur.pdf" download class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-600">
      <i class="fa-solid fa-book-open mr-1.5 text-xs" aria-hidden="true"></i>Buku Panduan <span class="text-xs text-slate-400">(PDF)</span>
    </a>
    <a href="docs/Buku_Panduan_Deteksi_Data_Tidur.pdf" download
       class="mt-2 block rounded-xl bg-slate-900 px-4 py-2.5 text-center text-sm font-semibold text-white">
      <i class="fa-solid fa-download mr-1.5 text-xs" aria-hidden="true"></i>Unduh Buku Panduan
    </a>
  </div>
</nav>

<!-- ============ IKON MENGAMBANG SISI KANAN ============ -->
<div class="fixed right-5 top-1/2 z-40 hidden -translate-y-1/2 flex-col gap-3 md:flex">
  <a href="index.php" title="Kembali ke Beranda"
     class="float-btn grid h-11 w-11 place-items-center rounded-full bg-white text-slate-500 shadow-card ring-1 ring-slate-200 hover:text-primary-600">
    <i class="fa-solid fa-house" aria-hidden="true"></i>
  </a>
  <a href="index.php#shped" title="Informasi Data SHPED"
     class="float-btn grid h-11 w-11 place-items-center rounded-full bg-white text-slate-500 shadow-card ring-1 ring-slate-200 hover:text-primary-600">
    <i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i>
  </a>
  </a>
  <button type="button" id="btnKeAtas" title="Kembali ke atas"
          class="float-btn grid h-11 w-11 place-items-center rounded-full bg-white text-slate-500 shadow-card ring-1 ring-slate-200 hover:text-primary-600">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
</div>

<main class="font-hero">

  <!-- ============ PROFIL KETUA TIM DISTRIBUSI ============ -->
  <section class="mx-auto max-w-7xl px-4 pb-16 pt-10 md:px-8 md:pb-24 md:pt-16">
    <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">

      <!-- Kiri: kartu profil (pengganti foto) + sapaan besar di bawahnya -->
      <div>
        <div class="relative aspect-[4/5] w-full rounded-2xl max-w-sm overflow-hidden">
          <img src="assets/img/ros.jpeg" alt="FOTO-KETUA-TIM-DISTRIBUSI">
        </div>
        <h1 class="max-w-sm text-center mt-8 text-4xl font-bold leading-tight tracking-tight text-slate-900 md:text-5xl">
          <span class="whitespace-nowrap">Rosani Hasanela</span>
        </h1>
      </div>

      <!-- jabatan + deskripsi -->
      <div>
        <h2 class="text-3xl font-extrabold leading-snug tracking-tight text-slate-900 md:text-[2.6rem] md:leading-[1.2]">
          Ketua Tim Distribusi Badan Pusat Statistik Kabupaten Halmahera Utara
        </h2>
        <p class="mt-4 max-w-xl text-sm leading-relaxed text-slate-500 md:text-base">
          Memimpin tim distribusi dan bertanggung jawab atas pengumpulan, pengolahan, serta
          penyebaran data harga SHPED di Kabupaten Halmahera Utara serta memastikan data bulanan
          lengkap dan berkualitas sebelum dianalisis oleh sistem Deteksi Data Tidur.
        </p>
      </div>

    </div>
  </section>

  <!-- ============ ANGGOTA TIM DISTRIBUSI ============ -->
  <section id="anggota" class="mx-auto mt-26 pt-20 max-w-7xl scroll-mt-24 px-4 pb-16 md:px-8 md:pb-20">
    <!-- <p class="text-xs text-center font-extrabold uppercase tracking-[0.2em] text-emerald-600">Tim Distribusi</p> -->
    <h2 class="mt-2 text-center text-2xl font-extrabold tracking-tight text-slate-900 md:text-3xl">Anggota Tim Distribusi</h2>

    <div class="mt-14 grid lg:grid-cols-3">
      <!-- Profil Anggota Tim -->
      <div class="flex text-center flex-col items-center gap-5 p-7">
        <img src="assets/img/profil-icon.jpg" class="w-32 h-32 rounded-full object-cover" alt="">
        <div class="min-w-0">
          <p class="text-xl font-extrabold tracking-tight text-slate-900">Muhammad Afwan</p>
          <!-- <p class="text-sm font-bold text-emerald-600">Anggota Tim Distribusi</p> -->
          <!-- <p class="mt-3 text-sm leading-relaxed text-slate-500">
            Membantu pemantauan harga komoditas pasar, entri dan verifikasi data hasil
            pencacahan, serta menyiapkan file Excel SHPED bulanan agar siap dianalisis
            dan diunggah ke sistem.
          </p> -->
        </div>
      </div>

      <div class="flex text-center flex-col items-center gap-5 p-7">
        <img src="assets/img/alifa.png" class="w-32 h-32 rounded-full" alt="">
        <div class="min-w-0">
          <p class="text-xl font-extrabold tracking-tight text-slate-900">Alifa Anindya</p>
        </div>
      </div>

      <div class="flex text-center flex-col items-center gap-5 p-7">
        <img src="assets/img/Cici.jpg.jpeg" class="w-32 h-32 rounded-full" alt="">
        <div class="min-w-0">
          <p class="text-xl font-extrabold tracking-tight text-slate-900">Meylin Man Hasan</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ MENTOR ============ -->
  <section id="mentor" class="mx-auto mt-28 flex flex-col items-center text-center max-w-7xl scroll-mt-24 px-4 pb-16 md:px-8 md:pb-20">
    <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900 md:text-3xl">Mentor</h2>

    <div class="mt-7 flex flex-col items-center text-center gap-5 rounded-xl bg-white p-7 shadow-card ring-1 ring-slate-100 lg:max-w-3xl">
      <div class="min-w-0 flex flex-col items-center">
        <img src="assets/img/frida.jpeg" class="w-32 h-32 rounded-full" alt="">
        <p class="mt-4 text-xl font-extrabold tracking-tight text-slate-900">Frida Oktafiana Arianti</p>
        <p class="text-sm font-bold text-violet-600">Mentor</p>
        <p class="mt-3 text-sm leading-relaxed text-slate-500">
          Membimbing tim dari sisi metodologi statistik dan pengembangan aplikasi —
          memberikan arahan kebijakan data serta meninjau hasil deteksi data tidur
          sebelum digunakan.
        </p>
      </div>
    </div>
  </section>

  <!-- ============ TIM PENGEMBANG WEBSITE ============ -->
  <section id="pengembang" class="mx-auto mt-28 max-w-7xl scroll-mt-24 px-4 pb-20 md:px-8 md:pb-24">
    <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-primary-600">Tim Pengembang</p>
    <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900 md:text-3xl">Pengembang Website</h2>
    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
      Empat pengembang dengan keahlian di bidangnya masing-masing membangun
      Sistem Deteksi Data Tidur dari halaman publik, dashboard admin, hingga layanan analisis.
    </p>

    <div class="mt-7 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
      
      <!-- CARD 1 -->
      <div class="rounded-3xl bg-white shadow-card ring-1 ring-slate-100 overflow-hidden flex flex-col">
        <div class="w-full">
          <img src="assets/img/ndes.png" class="w-full h-auto object-cover" alt="tim-pengembang-web">
        </div>
        
        <div class="p-7 flex-1">
          <p class="text-lg font-extrabold tracking-tight text-slate-900">Lukas Fernandes Urupoda</p>
          <div class="icon-sosmed flex flex-col mt-10 gap-2 text-sm">
            <a href="https://www.instagram.com/andesurpda/" target="_blank"><i class="fa-brands fa-instagram"></i> andesurpda</a>
            <a href="nandes.urpda@gmail.com"><i class="fa-regular fa-envelope"></i> nandes.urpda@gmail.com</a>
          </div>
        </div>
      </div>

      <!-- CARD 2 -->
      <div class="rounded-3xl bg-white shadow-card ring-1 ring-slate-100 overflow-hidden flex flex-col">
        <div class="w-full">
          <img src="assets/img/pengembang3.jpeg" class="w-full h- object-cover" alt="tim-pengembang-web">
        </div>
        
        <div class="p-7 flex-1">
          <p class="text-lg font-extrabold tracking-tight text-slate-900">Felin Febriani Toronge</p>
          <div class="icon-sosmed flex flex-col mt-10 gap-2 text-sm">
            <a href=""><i class="fa-brands fa-instagram"></i> felintoronge</a>
            <a href="nandes.urpda@gmail.com"><i class="fa-regular fa-envelope"></i> felintoronge2004@gmail.com</a>
          </div>
        </div>
      </div>

      <!-- CARD 3 -->
      <div class="rounded-3xl bg-white shadow-card ring-1 ring-slate-100 overflow-hidden flex flex-col">
        <div class="w-full">
          <img src="assets/img/tim-pengembang2.jpeg" class="w-full h-auto object-cover" alt="tim-pengembang-web">
        </div>
        
        <div class="p-7 flex-1">
          <p class="text-lg font-extrabold tracking-tight text-slate-900">Galan Kihi-Kihi</p>
          <div class="icon-sosmed flex flex-col mt-10 gap-2 text-sm">
            <a href="#"><i class="fa-brands fa-instagram"></i> galanddpo_</a>
            <a href="nandes.urpda@gmail.com"><i class="fa-regular fa-envelope"></i> gkihikihi@gmail.com</a>
          </div>
        </div>
      </div>

      <!-- CARD 4 -->
      <div class="rounded-3xl bg-white shadow-card ring-1 ring-slate-100 overflow-hidden flex flex-col">
        <div class="w-full">
          <img src="assets/img/yayaya.jpeg" class="w-full h-auto object-cover" alt="tim-pengembang-web">
        </div>
        
        <div class="p-7 flex-1">
          <p class="text-lg font-extrabold tracking-tight text-slate-900">Iqnatia Freyling Pesik</p>
          <div class="icon-sosmed flex flex-col mt-10 gap-2 text-sm">
            <a href="https://www.instagram.com/iqnatiaa?dlrf=NWNlcGJzMzZzdGo2" target="_blank"><i class="fa-brands fa-instagram"></i> iqnatiaa</a>
            <a href="nandes.urpda@gmail.com"><i class="fa-regular fa-envelope"></i> iqnatiafreyling@gmail.com</a>
          </div>
        </div>
      </div>
    </div>
  </section>

</main>

<!-- ============ Footer ============ -->
<footer class="border-t border-slate-200 bg-slate-100">
  <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 py-6 text-center text-xs text-slate-500 md:flex-row md:px-8 md:text-left">
    <p>&copy; <?= e((string)$tahunKini) ?> Tim Distribusi · Badan Pusat Statistik Kabupaten Halmahera Utara</p>
    <p>
      <a href="index.php" class="font-semibold text-slate-700 hover:text-primary-600">Beranda</a>
      <span class="mx-2 text-slate-300">·</span>
      <a href="docs/Buku_Panduan_Deteksi_Data_Tidur.pdf" download class="font-semibold text-slate-700 hover:text-primary-600">Buku Panduan (PDF)</a>
    </p>
  </div>
</footer>

<script>
  
</script>

</body>
</html>
