<?php
/**
 * includes/footer.php — Footer bersama + pemuat script tambahan.
 * Set $scripts = ['assets/js/xxx.js'] SEBELUM include untuk memuat JS halaman.
 */
?>
<!-- ===== FOOTER ===== -->
<footer class="border-t border-slate-200 bg-white mt-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col sm:flex-row items-center justify-center gap-3 text-sm text-slate-500">
        <p>&copy; <?= date('Y') ?> Badan Pusat Statistik Kabupaten Halmahera Utara</p>
    </div>
</footer>
<?php
/* Cache-busting: ?v= waktu terakhir file diubah → browser selalu memuat JS
   terbaru setiap kali kode diperbarui (mencegah error "... of null" akibat
   JS lama yang masih tersimpan di cache browser). */
foreach (($scripts ?? []) as $__s):
  $__path = __DIR__ . '/../' . $__s;
  $__ver  = is_file($__path) ? filemtime($__path) : '1';
?>
  <script src="<?= e($__s) ?>?v=<?= $__ver ?>"></script>
<?php endforeach; ?>
</body>
</html>
