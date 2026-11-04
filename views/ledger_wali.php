<?php
/**
 * ==============================================================
 * views/ledger_wali.php
 * Ledger Rapor & Buku Induk — HANYA untuk Wali Kelas.
 * --------------------------------------------------------------
 * Variabel yang diharapkan dari guru.php:
 *   $hak_akses_kelas : 'wali_kelas' | 'guru_mapel'
 *   $data_ledger     : hasil fetch_ledger_rapor()
 *
 * Guard di bawah adalah pertahanan berlapis: sekalipun file ini
 * di-include tanpa sengaja, guru mapel tidak akan pernah melihat
 * data konsolidasi seluruh mapel.
 * ==============================================================
 */
if (!isset($hak_akses_kelas) || $hak_akses_kelas !== 'wali_kelas') {
    return;
}

$ledger       = (isset($data_ledger) && is_array($data_ledger)) ? $data_ledger : [];
$rombel       = $ledger['rombel'] ?? null;
$daftarMapel  = $ledger['daftar_mapel'] ?? [];
$daftarSiswa  = $ledger['daftar_siswa'] ?? [];
$matriksNilai = $ledger['matriks_nilai'] ?? [];
$kkmMapel     = $ledger['kkm_mapel'] ?? [];
$ringkasan    = $ledger['ringkasan'] ?? [];
$waliNama     = $ledger['wali_nama'] ?? null;
$tahunAjaran  = $ledger['tahun_ajaran'] ?? '-';
$semester     = $ledger['semester'] ?? '-';
$pesanError   = $ledger['error'] ?? null;

$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$angka = static function ($value, int $desimal = 1): string {
    if ($value === null || $value === '') {
        return '–';
    }
    return number_format((float) $value, $desimal, ',', '.');
};

$jumlahMapel = count($daftarMapel);
$jumlahSiswa = count($daftarSiswa);
$kolomTotal  = 3 + $jumlahMapel + 2 + 5;

$namaRombel = $rombel['nama'] ?? 'Kelas';
$ruangRombel = $rombel['ruang'] ?? null;

$predikatKelas = [
    'A' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
    'B' => 'bg-sky-100 text-sky-800 ring-sky-200',
    'C' => 'bg-amber-100 text-amber-800 ring-amber-200',
    'D' => 'bg-rose-100 text-rose-800 ring-rose-200',
];

// Rata-rata kelas per mapel untuk baris footer.
$rataMapel = [];
foreach ($daftarMapel as $mapel) {
    $mapelId = (int) $mapel['id'];
    $kumpulan = [];
    foreach ($daftarSiswa as $siswa) {
        $nilai = $matriksNilai[(int) $siswa['id']][$mapelId] ?? null;
        if ($nilai !== null) {
            $kumpulan[] = (float) $nilai;
        }
    }
    $rataMapel[$mapelId] = $kumpulan === [] ? null : array_sum($kumpulan) / count($kumpulan);
}

$totalRataKelas = 0.0;
$totalMapelTerisi = 0;
foreach ($daftarSiswa as $siswa) {
    if (($siswa['rata_rata'] ?? null) !== null) {
        $totalRataKelas += (float) $siswa['rata_rata'];
        $totalMapelTerisi++;
    }
}
$rataKelas = $totalMapelTerisi > 0 ? $totalRataKelas / $totalMapelTerisi : null;
?>
<style>
  /* Sticky + hover tanpa mengganggu warna status nilai. */
  #ledger-wali .ledger-scroll { -webkit-overflow-scrolling: touch; scrollbar-width: thin; }
  #ledger-wali .ledger-scroll::-webkit-scrollbar { height: 8px; width: 8px; }
  #ledger-wali .ledger-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
  #ledger-wali .ledger-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 9999px; }

  #ledger-wali tbody tr:hover > td { background-color: #eef2ff; }
  #ledger-wali tbody tr:hover > td.ledger-nilai-rendah { background-color: #ffe4e6; }
  #ledger-wali tbody tr:hover > td.ledger-selisih { background-color: #fef3c7; }

  #ledger-wali .ledger-nilai-rendah { background-color: #fff1f2; }
  #ledger-wali .ledger-selisih { background-color: #fffbeb; }

  @media print {
    @page { size: A4 landscape; margin: 8mm; }
    #ledger-wali { box-shadow: none !important; border: 0 !important; margin: 0 !important; }
    #ledger-wali .ledger-scroll { max-height: none !important; overflow: visible !important; }
    #ledger-wali .ledger-no-print { display: none !important; }
    #ledger-wali .ledger-sticky { position: static !important; box-shadow: none !important; }
    #ledger-wali table { width: 100% !important; min-width: 0 !important; font-size: 8px !important; }
    #ledger-wali thead { display: table-header-group; }
    #ledger-wali tr { break-inside: avoid; }
  }
</style>

<section id="ledger-wali" class="mt-8 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

  <!-- ============ HEADER ============ -->
  <div class="bg-gradient-to-r from-indigo-950 via-indigo-900 to-slate-900 px-4 sm:px-6 py-5 text-white">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <div class="flex items-start gap-3.5">
        <div class="w-11 h-11 rounded-2xl bg-white/10 ring-1 ring-white/15 flex items-center justify-center flex-shrink-0">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-6 h-6 text-amber-300" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
        </div>
        <div>
          <h2 class="text-base sm:text-lg font-bold tracking-wide leading-tight">Buku Induk &amp; Ledger Rapor</h2>
          <p class="text-[11px] sm:text-xs text-indigo-200 mt-1 max-w-2xl leading-relaxed">
            Rekapitulasi nilai rerata seluruh mata pelajaran dan akumulasi presensi kelas
            <span class="font-semibold text-white"><?= $e($namaRombel) ?></span>.
          </p>
          <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2.5 text-[10px] sm:text-[11px] text-indigo-200">
            <span class="inline-flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
              Wali Kelas: <span class="font-semibold text-white"><?= $e($waliNama ?: '-') ?></span>
            </span>
            <?php if ($ruangRombel) : ?>
              <span class="inline-flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-sky-400"></span>
                Ruang <?= $e($ruangRombel) ?>
              </span>
            <?php endif; ?>
            <span class="inline-flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
              TA <?= $e($tahunAjaran) ?> &middot; Semester <?= $e($semester) ?>
            </span>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2 sm:flex-col sm:items-end ledger-no-print">
        <span class="inline-flex items-center gap-1.5 bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-400/30 px-3 py-1.5 rounded-full text-[10px] sm:text-[11px] font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5" aria-hidden="true">
            <path fill-rule="evenodd" d="M16.704 5.296a1 1 0 010 1.415l-7.5 7.5a1 1 0 01-1.415 0l-3.5-3.5a1 1 0 111.415-1.415L8.5 12.086l6.79-6.79a1 1 0 011.414 0z" clip-rule="evenodd" />
          </svg>
          Hak Akses: Wali Kelas
        </span>
        <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-1.5 bg-white/10 hover:bg-white/20 ring-1 ring-white/20 px-3 py-1.5 rounded-full text-[10px] sm:text-[11px] font-semibold transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-3.5 h-3.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
          </svg>
          Cetak / PDF
        </button>
      </div>
    </div>
  </div>

  <?php if ($pesanError !== null) : ?>
    <div class="p-5">
      <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-xs text-rose-800 flex items-start gap-2.5">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 mt-0.5 flex-shrink-0" aria-hidden="true">
          <path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
        </svg>
        <div>
          <p class="font-bold">Ledger tidak dapat dimuat</p>
          <p class="mt-0.5"><?= $e($pesanError) ?></p>
        </div>
      </div>
    </div>
  <?php else : ?>

    <!-- ============ RINGKASAN ============ -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3 px-4 sm:px-6 py-4 bg-slate-50/70 border-b border-slate-200">
      <?php
      $kartu = [
          [
              'label' => 'Siswa Terdaftar',
              'nilai' => $angka($ringkasan['jumlah_siswa'] ?? $jumlahSiswa, 0),
              'sub'   => 'orang',
              'icon'  => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
              'warna' => 'bg-indigo-50 text-indigo-700',
          ],
          [
              'label' => 'Mapel Dinilai',
              'nilai' => $angka($ringkasan['jumlah_mapel'] ?? $jumlahMapel, 0),
              'sub'   => 'mata pelajaran',
              'icon'  => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
              'warna' => 'bg-sky-50 text-sky-700',
          ],
          [
              'label' => 'Rata-rata Kelas',
              'nilai' => $angka($ringkasan['rata_kelas'] ?? $rataKelas, 1),
              'sub'   => 'skala 0–100',
              'icon'  => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.5c0-.621.504-1.125 1.125-1.125h2.25C20.496 3.375 21 3.879 21 4.5v15.375c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.5z',
              'warna' => 'bg-violet-50 text-violet-700',
          ],
          [
              'label' => 'Kehadiran Kelas',
              'nilai' => $angka($ringkasan['persen_hadir'] ?? null, 1) . (($ringkasan['persen_hadir'] ?? null) !== null ? '%' : ''),
              'sub'   => 'seluruh mapel',
              'icon'  => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
              'warna' => 'bg-emerald-50 text-emerald-700',
          ],
      ];
      ?>
      <?php foreach ($kartu as $item) : ?>
        <div class="bg-white rounded-xl border border-slate-200 px-3.5 py-3 flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg <?= $e($item['warna']) ?> flex items-center justify-center flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="w-5 h-5" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="<?= $e($item['icon']) ?>" />
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold truncate"><?= $e($item['label']) ?></p>
            <p class="text-base sm:text-lg font-extrabold text-slate-800 leading-tight tabular-nums"><?= $e($item['nilai']) ?></p>
            <p class="text-[10px] text-slate-400 truncate"><?= $e($item['sub']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (($ringkasan['siswa_belum_lengkap'] ?? 0) > 0) : ?>
      <div class="px-4 sm:px-6 py-2.5 bg-amber-50 border-b border-amber-200 text-[11px] text-amber-900 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5 flex-shrink-0" aria-hidden="true">
          <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
        </svg>
        <span><strong><?= (int) $ringkasan['siswa_belum_lengkap'] ?> siswa</strong> belum memiliki nilai lengkap untuk seluruh mapel. Nilai yang kosong ditandai “–”.</span>
      </div>
    <?php endif; ?>

    <!-- ============ KETERANGAN ============ -->
    <div class="px-4 sm:px-6 pt-4 pb-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-[10px] sm:text-[11px] text-slate-500 ledger-no-print">
      <span class="inline-flex items-center gap-1.5">
        <span class="w-3 h-3 rounded bg-rose-100 ring-1 ring-rose-300"></span> Nilai &lt; KKM
      </span>
      <span class="inline-flex items-center gap-1.5">
        <span class="w-3 h-3 rounded bg-amber-50 ring-1 ring-amber-300"></span> Nilai di bawah rata-rata kelas
      </span>
      <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> H = Hadir</span>
      <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span> I = Izin</span>
      <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-sky-500"></span> S = Sakit</span>
      <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-rose-500"></span> A = Alpa</span>
      <span class="ml-auto hidden md:inline text-slate-400">Geser tabel ke samping untuk melihat seluruh mapel →</span>
    </div>

    <!-- ============ TABEL LEDGER ============ -->
    <div class="px-3 sm:px-6 pb-5">
      <div class="ledger-scroll overflow-auto max-h-[76vh] rounded-xl border border-slate-200">
        <table class="table-fixed text-[11px] sm:text-xs text-slate-700 border-separate border-spacing-0"
               style="width: <?= 826 + (88 * $jumlahMapel) ?>px;">
          <caption class="sr-only">Ledger rapor kelas <?= $e($namaRombel) ?>: rerata nilai per mata pelajaran dan rekapitulasi presensi.</caption>

          <thead>
            <tr class="bg-slate-100 text-slate-600">
              <th rowspan="2" class="ledger-sticky sticky left-0 top-0 z-40 bg-slate-100 px-1.5 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider border-b border-r border-slate-300" style="width:44px;">No</th>
              <th rowspan="2" class="ledger-sticky sticky left-[44px] top-0 z-40 bg-slate-100 px-2 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider border-b border-r border-slate-300" style="width:110px;">NISN</th>
              <th rowspan="2" class="ledger-sticky sticky left-[154px] top-0 z-40 bg-slate-100 px-2.5 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider border-b border-r border-slate-300 shadow-[6px_0_8px_-6px_rgba(15,23,42,0.25)]" style="width:220px;">Nama Siswa</th>

              <th colspan="<?= max(1, $jumlahMapel) ?>" class="sticky top-0 z-30 bg-sky-50 px-2 py-2 text-center text-[10px] font-bold uppercase tracking-wider text-sky-800 border-b border-r border-slate-300">
                Nilai Rerata per Mata Pelajaran
              </th>
              <th rowspan="2" class="sticky top-0 z-30 bg-violet-50 px-2 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-violet-800 border-b border-r border-slate-300" style="width:84px;">Rata²</th>
              <th rowspan="2" class="sticky top-0 z-30 bg-violet-50 px-1.5 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-violet-800 border-b border-r border-slate-300" style="width:68px;">Rank</th>

              <th colspan="5" class="sticky top-0 z-30 bg-emerald-50 px-2 py-2 text-center text-[10px] font-bold uppercase tracking-wider text-emerald-800 border-b border-r border-slate-300">
                Rekap Presensi (Semua Mapel)
              </th>
            </tr>
            <tr class="bg-slate-50 text-slate-500">
              <?php if ($jumlahMapel === 0) : ?>
                <th class="sticky top-[37px] z-30 bg-sky-50 px-2 py-2 text-center text-[10px] font-semibold border-b border-r border-slate-300">Belum ada mapel</th>
              <?php endif; ?>
              <?php foreach ($daftarMapel as $mapel) : ?>
                <?php $mapelId = (int) $mapel['id']; ?>
                <th class="sticky top-[37px] z-30 bg-sky-50/70 px-1.5 py-2 text-center align-top border-b border-r border-slate-300" style="width:88px;">
                  <span class="block text-[10px] font-semibold leading-tight text-slate-700 break-words"><?= $e($mapel['nama_mapel'] ?? '-') ?></span>
                  <span class="mt-1 inline-block text-[9px] font-medium text-slate-400">KKM <?= (int) ($kkmMapel[$mapelId] ?? 75) ?></span>
                </th>
              <?php endforeach; ?>

              <th class="sticky top-[37px] z-30 bg-emerald-50/70 px-1 py-2 text-center text-[10px] font-bold text-emerald-700 border-b border-r border-slate-300" style="width:52px;">H</th>
              <th class="sticky top-[37px] z-30 bg-emerald-50/70 px-1 py-2 text-center text-[10px] font-bold text-amber-600 border-b border-r border-slate-300" style="width:52px;">I</th>
              <th class="sticky top-[37px] z-30 bg-emerald-50/70 px-1 py-2 text-center text-[10px] font-bold text-sky-600 border-b border-r border-slate-300" style="width:52px;">S</th>
              <th class="sticky top-[37px] z-30 bg-emerald-50/70 px-1 py-2 text-center text-[10px] font-bold text-rose-600 border-b border-r border-slate-300" style="width:52px;">A</th>
              <th class="sticky top-[37px] z-30 bg-emerald-50/70 px-1.5 py-2 text-center text-[10px] font-bold text-slate-700 border-b border-slate-300" style="width:92px;">% Hadir</th>
            </tr>
          </thead>

          <tbody class="bg-white">
            <?php if ($jumlahSiswa === 0) : ?>
              <tr>
                <td colspan="<?= $kolomTotal ?>" class="px-4 py-10 text-center text-slate-500">
                  <p class="font-semibold text-slate-700">Belum ada siswa di rombel ini</p>
                  <p class="text-[11px] mt-1">Data akan muncul setelah siswa dimasukkan ke rombel melalui portal admin.</p>
                </td>
              </tr>
            <?php else : ?>
              <?php foreach ($daftarSiswa as $siswa) : ?>
                <?php
                $siswaId   = (int) $siswa['id'];
                $presensi  = $siswa['presensi'] ?? [];
                $rata      = $siswa['rata_rata'] ?? null;
                $predikat  = $siswa['predikat'] ?? null;
                $persen    = $siswa['persen_hadir'] ?? null;
                $peringkat = $siswa['peringkat'] ?? null;
                ?>
                <tr class="align-middle">
                  <td class="ledger-sticky sticky left-0 z-20 bg-white px-1.5 py-2.5 text-center text-[10px] text-slate-400 font-semibold border-b border-r border-slate-200 tabular-nums">
                    <?= (int) ($siswa['nomor'] ?? 0) ?>
                  </td>
                  <td class="ledger-sticky sticky left-[44px] z-20 bg-white px-2 py-2.5 text-[11px] text-slate-500 font-mono border-b border-r border-slate-200 truncate" title="<?= $e($siswa['nisn'] ?? '-') ?>">
                    <?= $e($siswa['nisn'] ?? '-') ?>
                  </td>
                  <td class="ledger-sticky sticky left-[154px] z-20 bg-white px-2.5 py-2.5 border-b border-r border-slate-200 shadow-[6px_0_8px_-6px_rgba(15,23,42,0.2)]">
                    <p class="font-semibold text-slate-800 text-[11px] sm:text-xs leading-snug break-words"><?= $e($siswa['nama_siswa'] ?? '-') ?></p>
                    <p class="text-[9px] text-slate-400 mt-0.5"><?= (int) ($siswa['jumlah_mapel_terisi'] ?? 0) ?>/<?= $jumlahMapel ?> mapel terisi</p>
                  </td>

                  <?php foreach ($daftarMapel as $mapel) : ?>
                    <?php
                    $mapelId = (int) $mapel['id'];
                    $nilai   = $matriksNilai[$siswaId][$mapelId] ?? null;
                    $kkm     = (int) ($kkmMapel[$mapelId] ?? 75);
                    $rataKls = $rataMapel[$mapelId] ?? null;
                    $kelas   = '';
                    if ($nilai !== null && $nilai < $kkm) {
                        $kelas = 'ledger-nilai-rendah';
                    } elseif ($nilai !== null && $rataKls !== null && $nilai < $rataKls) {
                        $kelas = 'ledger-selisih';
                    }
                    ?>
                    <td class="px-1.5 py-2.5 text-center border-b border-r border-slate-100 tabular-nums <?= $kelas ?>">
                      <?php if ($nilai === null) : ?>
                        <span class="text-slate-300">–</span>
                      <?php else : ?>
                        <span class="font-semibold <?= $nilai < $kkm ? 'text-rose-700' : 'text-slate-700' ?>"><?= $angka($nilai, 1) ?></span>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>

                  <td class="px-2 py-2.5 text-center border-b border-r border-slate-200 bg-violet-50/40 tabular-nums">
                    <span class="font-extrabold text-violet-800"><?= $angka($rata, 1) ?></span>
                    <?php if ($predikat !== null) : ?>
                      <span class="mt-0.5 inline-flex items-center rounded-md px-1 py-0.5 text-[9px] font-bold ring-1 <?= $e($predikatKelas[$predikat] ?? 'bg-slate-100 text-slate-600 ring-slate-200') ?>"
                            title="<?= $e(function_exists('ledger_predikat_label') ? ledger_predikat_label($predikat) : $predikat) ?>">
                        <?= $e($predikat) ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="px-1.5 py-2.5 text-center border-b border-r border-slate-200 bg-violet-50/40">
                    <?php if ($peringkat !== null) : ?>
                      <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-[10px] font-bold <?= $peringkat === 1 ? 'bg-amber-100 text-amber-800 ring-1 ring-amber-300' : 'bg-slate-100 text-slate-600' ?>">
                        <?= (int) $peringkat ?>
                      </span>
                    <?php else : ?>
                      <span class="text-slate-300">–</span>
                    <?php endif; ?>
                  </td>

                  <td class="px-1 py-2.5 text-center border-b border-r border-slate-100 text-emerald-700 font-semibold tabular-nums"><?= (int) ($presensi['total_h'] ?? 0) ?></td>
                  <td class="px-1 py-2.5 text-center border-b border-r border-slate-100 text-amber-600 tabular-nums"><?= (int) ($presensi['total_i'] ?? 0) ?></td>
                  <td class="px-1 py-2.5 text-center border-b border-r border-slate-100 text-sky-600 tabular-nums"><?= (int) ($presensi['total_s'] ?? 0) ?></td>
                  <td class="px-1 py-2.5 text-center border-b border-r border-slate-100 text-rose-600 font-semibold tabular-nums"><?= (int) ($presensi['total_a'] ?? 0) ?></td>
                  <td class="px-1.5 py-2 text-center border-b border-slate-200">
                    <?php if ($persen === null) : ?>
                      <span class="text-slate-300">–</span>
                    <?php else : ?>
                      <span class="block font-bold tabular-nums <?= $persen < 75 ? 'text-rose-600' : 'text-slate-700' ?>"><?= $angka($persen, 1) ?>%</span>
                      <span class="mt-1 block h-1 w-14 mx-auto rounded-full bg-slate-200 overflow-hidden">
                        <span class="block h-full rounded-full <?= $persen < 75 ? 'bg-rose-500' : ($persen >= 90 ? 'bg-emerald-500' : 'bg-amber-400') ?>"
                              style="width: <?= $e((string) max(0, min(100, (float) $persen))) ?>%"></span>
                      </span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>

          <?php if ($jumlahSiswa > 0 && $jumlahMapel > 0) : ?>
            <tfoot>
              <tr class="bg-slate-100 text-slate-600 font-semibold">
                <td colspan="3" class="ledger-sticky sticky left-0 z-20 bg-slate-100 px-3 py-2.5 text-[10px] uppercase tracking-wider border-t border-r border-slate-300">
                  Rata-rata Kelas per Mapel
                </td>
                <?php foreach ($daftarMapel as $mapel) : ?>
                  <?php
                  $mapelId = (int) $mapel['id'];
                  $rata    = $rataMapel[$mapelId] ?? null;
                  $kkm     = (int) ($kkmMapel[$mapelId] ?? 75);
                  ?>
                  <td class="px-1.5 py-2.5 text-center border-t border-r border-slate-300 tabular-nums <?= $rata !== null && $rata < $kkm ? 'text-rose-600' : 'text-slate-700' ?>">
                    <?= $angka($rata, 1) ?>
                  </td>
                <?php endforeach; ?>
                <td class="px-2 py-2.5 text-center border-t border-r border-slate-300 bg-violet-100/60 text-violet-800 tabular-nums"><?= $angka($rataKelas, 1) ?></td>
                <td class="px-1.5 py-2.5 text-center border-t border-r border-slate-300 text-slate-400">–</td>
                <?php
                $totalH = 0; $totalI = 0; $totalS = 0; $totalA = 0;
                foreach ($daftarSiswa as $siswa) {
                    $totalH += (int) ($siswa['presensi']['total_h'] ?? 0);
                    $totalI += (int) ($siswa['presensi']['total_i'] ?? 0);
                    $totalS += (int) ($siswa['presensi']['total_s'] ?? 0);
                    $totalA += (int) ($siswa['presensi']['total_a'] ?? 0);
                }
                ?>
                <td class="px-1 py-2.5 text-center border-t border-r border-slate-300 tabular-nums"><?= $totalH ?></td>
                <td class="px-1 py-2.5 text-center border-t border-r border-slate-300 tabular-nums"><?= $totalI ?></td>
                <td class="px-1 py-2.5 text-center border-t border-r border-slate-300 tabular-nums"><?= $totalS ?></td>
                <td class="px-1 py-2.5 text-center border-t border-r border-slate-300 tabular-nums"><?= $totalA ?></td>
                <td class="px-1.5 py-2.5 text-center border-t border-slate-300 tabular-nums"><?= $angka($ringkasan['persen_hadir'] ?? null, 1) ?>%</td>
              </tr>
            </tfoot>
          <?php endif; ?>
        </table>
      </div>

      <p class="mt-3 text-[10px] text-slate-400 leading-relaxed">
        Sumber data: <code class="font-semibold text-slate-500">nilai_siswa</code> (AVG per siswa/mapel, digabung dari seluruh guru mapel) dan
        <code class="font-semibold text-slate-500">presensi</code> (SUM H/I/S/A seluruh mapel).
        Nilai di bawah KKM ditandai merah; kehadiran di bawah 75% ditandai merah.
        Rata-rata dihitung dari mapel yang sudah memiliki nilai.
      </p>
    </div>
  <?php endif; ?>
</section>
