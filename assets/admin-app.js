/**
 * ==============================================================
 * ADMIN DASHBOARD CONTROLLER
 * --------------------------------------------------------------
 * Seluruh data berasal dari database melalui api.php.
 * Tidak ada array data dummy di file ini.
 * ==============================================================
 */
(function () {
  'use strict';

  const CFG = window.ADMIN_CONFIG || {};
  const API_URL = CFG.apiUrl || 'api.php';
  const CSRF = CFG.csrf || '';

  const state = {
    stats: {},
    teachers: [],
    students: [],
    mapel: [],
    rombel: [],
    monitoring: [],
    archivedStudents: [],
    archivedTeachers: [],
    settings: {},
    activity: [],
    pendingUsers: [],
    detailRombel: null
  };

  let selectedStudentIds = new Set();
  let currentFilteredStudentIds = [];
  let currentDetailRombel = null;
  let currentAdminTab = 'overview';

  const ADMIN_TABS = ['overview', 'approval', 'guru', 'siswa', 'mapel', 'rombel', 'monitoring-guru', 'arsip-siswa', 'arsip-guru', 'settings'];
  const ADMIN_TAB_TITLES = {
    'overview': 'Ringkasan Sistem Akademik',
    'approval': 'Persetujuan Registrasi Akun Baru',
    'guru': 'Manajemen Guru & Tenaga Pendidik',
    'siswa': 'Manajemen Siswa (Buku Induk)',
    'mapel': 'Mata Pelajaran & Standar KKM',
    'rombel': 'Struktur Kelas & Rombongan Belajar',
    'monitoring-guru': 'Pemantauan Hasil Input Portal Guru',
    'arsip-siswa': 'Master Arsip Siswa (Alumni & Mutasi Keluar)',
    'arsip-guru': 'Master Arsip Guru & Staff (Pensiun & Mutasi)',
    'settings': 'Pengaturan Satuan Pendidikan'
  };

  /* ============================================================
   * UTILITAS
   * ============================================================ */
  function esc(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function asNumber(value, fallback = 0) {
    const n = Number(value);
    return Number.isFinite(n) ? n : fallback;
  }

  function initials(name) {
    return String(name || '?')
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map(function (part) { return part.charAt(0).toUpperCase(); })
      .join('');
  }

  function statusBadge(status) {
    switch (status) {
      case 'Lulus':
        return '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">Alumni / Lulus</span>';
      case 'Mutasi':
        return '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">Mutasi / Pindah</span>';
      case 'Mengundurkan Diri':
        return '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Mengundurkan Diri</span>';
      default:
        return '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Aktif Belajar</span>';
    }
  }

  function formatRelative(value) {
    if (!value) return '-';
    const date = new Date(String(value).replace(' ', 'T'));
    if (isNaN(date.getTime())) return String(value);
    const diff = Math.floor((Date.now() - date.getTime()) / 1000);
    if (diff < 60) return 'Baru saja';
    if (diff < 3600) return Math.floor(diff / 60) + ' menit yang lalu';
    if (diff < 86400) return Math.floor(diff / 3600) + ' jam yang lalu';
    if (diff < 604800) return Math.floor(diff / 86400) + ' hari yang lalu';
    return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  function showToast(title, message) {
    const box = document.getElementById('toast-box');
    const titleEl = document.getElementById('toast-title');
    const msgEl = document.getElementById('toast-msg');
    if (!box || !titleEl || !msgEl) return;
    titleEl.innerText = title;
    msgEl.innerText = message;
    box.classList.remove('translate-y-24', 'opacity-0');
    window.clearTimeout(showToast._timer);
    showToast._timer = window.setTimeout(function () {
      box.classList.add('translate-y-24', 'opacity-0');
    }, 3800);
  }

  function downloadFile(content, fileName, mimeType) {
    const blob = new Blob(['\ufeff' + content], { type: mimeType || 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function csvCell(value) {
    return '"' + String(value === null || value === undefined ? '' : value).replace(/"/g, '""') + '"';
  }

  /* ============================================================
   * API
   * ============================================================ */
  async function api(action, data, formData) {
    const headers = {
      'X-CSRF-Token': CSRF,
      'X-Requested-With': 'XMLHttpRequest'
    };
    let body;

    if (formData instanceof FormData) {
      formData.append('action', action);
      body = formData;
    } else {
      const params = new URLSearchParams();
      params.append('action', action);
      Object.keys(data || {}).forEach(function (key) {
        const value = data[key];
        params.append(key, (value !== null && typeof value === 'object') ? JSON.stringify(value) : value);
      });
      headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
      body = params.toString();
    }

    const response = await fetch(API_URL, {
      method: 'POST',
      headers: headers,
      body: body,
      credentials: 'same-origin'
    });

    let json;
    try {
      json = await response.json();
    } catch (e) {
      throw new Error('Respons server tidak valid (HTTP ' + response.status + ').');
    }

    if (response.status === 401) {
      window.location.href = 'login.php?next=admin.php';
      throw new Error('Sesi berakhir.');
    }
    if (!response.ok || !json.success) {
      throw new Error(json.message || ('Permintaan gagal (HTTP ' + response.status + ').'));
    }
    return json.data || {};
  }

  /* ============================================================
   * BOOTSTRAP & ROUTING
   * ============================================================ */
  async function loadBootstrap(showSpinner) {
    if (showSpinner) showToast('Memuat data', 'Mengambil data terbaru dari database...');
    const data = await api('admin_bootstrap');
    state.stats = data.stats || {};
    state.teachers = data.teachers || [];
    state.students = data.students || [];
    state.mapel = data.mapel || [];
    state.rombel = data.rombel || [];
    state.monitoring = data.monitoring || [];
    state.archivedStudents = data.archivedStudents || [];
    state.archivedTeachers = data.archivedTeachers || [];
    state.settings = data.settings || {};
    state.activity = data.activity || [];
    state.pendingUsers = data.pendingUsers || [];
    selectedStudentIds.clear();
    populateSelects();
    applySettingsForm();
    renderAll();
  }

  function renderAll() {
    renderStats();
    renderApprovalTable();
    renderTeachersTable();
    renderStudentsTable();
    renderMapelTable();
    renderRombelCards('ALL');
    renderMonitoringGuruTable();
    renderArchivedStudentsTable();
    renderArchivedTeachersTable();
  }

  function initRouting() {
    let saved = null;
    try {
      saved = sessionStorage.getItem('smp_admin_active_tab') || localStorage.getItem('smp_admin_active_tab');
    } catch (e) { /* ignore */ }
    const hash = (window.location.hash || '').replace(/^#\/?/, '').trim();
    const target = ADMIN_TABS.indexOf(hash) !== -1 ? hash : (ADMIN_TABS.indexOf(saved) !== -1 ? saved : 'overview');
    switchAdminTab(target, false);
  }

  function switchAdminTab(tab, updateHistory) {
    if (ADMIN_TABS.indexOf(tab) === -1) tab = 'overview';
    currentAdminTab = tab;
    try {
      sessionStorage.setItem('smp_admin_active_tab', tab);
      localStorage.setItem('smp_admin_active_tab', tab);
    } catch (e) { /* ignore */ }

    if (updateHistory !== false && window.location.hash !== '#' + tab) {
      try {
        history.pushState({ tab: tab }, '', '#' + tab);
      } catch (e) {
        window.location.hash = '#' + tab;
      }
    }

    closeAllModals();
    ADMIN_TABS.forEach(function (t) {
      const section = document.getElementById('tab-' + t);
      const button = document.getElementById('nav-btn-' + t);
      if (!section || !button) return;
      section.classList.toggle('hidden', t !== tab);
      if (t === tab) {
        button.classList.add('bg-indigo-900', 'text-white', 'shadow-sm', 'font-bold');
        button.classList.remove('text-indigo-200', 'hover:bg-indigo-900/60');
      } else {
        button.classList.remove('bg-indigo-900', 'text-white', 'shadow-sm', 'font-bold');
        button.classList.add('text-indigo-200', 'hover:bg-indigo-900/60');
      }
    });

    const title = document.getElementById('topbar-title');
    if (title) title.innerText = ADMIN_TAB_TITLES[tab] || 'Admin Panel';
    document.title = (ADMIN_TAB_TITLES[tab] || 'Admin Panel') + ' - SMPN 7 Purwakarta';
    if (window.innerWidth < 768) closeMobileSidebar();
    window.scrollTo(0, 0);
  }

  function closeAllModals() {
    document.querySelectorAll('[id^="modal-"]').forEach(function (modal) {
      modal.classList.add('hidden');
    });
    const menu = document.getElementById('portal-switcher-menu');
    if (menu) menu.classList.add('hidden');
    closeMobileSidebar();
  }

  /* ============================================================
   * RENDER: STATISTIK & OVERVIEW
   * ============================================================ */
  function renderStats() {
    const s = state.stats || {};
    const setText = function (id, value) {
      const el = document.getElementById(id);
      if (el) el.innerText = value;
    };

    setText('stat-total-siswa', s.total_siswa || 0);
    setText('stat-total-guru', s.total_guru || 0);
    setText('stat-total-mapel', s.total_mapel || 0);
    setText('stat-total-rombel', s.total_rombel || 0);
    setText('stat-rata-nilai', (s.total_nilai > 0 ? Number(s.rata_nilai).toFixed(1) : '0.0'));
    setText('stat-siswa-sub', (s.total_rombel || 0) + ' Rombongan Belajar');
    setText('stat-guru-sub', (s.total_guru || 0) + ' akun guru aktif');
    setText('stat-mapel-sub', 'KKM Rata-Rata: ' + (s.rata_kkm || 0));
    setText('stat-rombel-sub', 'Kelas 7, 8, dan 9');
    setText('stat-nilai-sub', (s.total_nilai || 0).toLocaleString('id-ID') + ' nilai terinput');

    setText('sidebar-count-guru', s.total_guru || 0);
    setText('sidebar-count-siswa', s.total_siswa_all || 0);
    setText('sidebar-count-mapel', s.total_mapel || 0);
    setText('sidebar-count-rombel', (s.total_rombel || 0) + ' Rombel');
    setText('sidebar-count-arsip-siswa', state.archivedStudents.length);
    setText('sidebar-count-arsip-guru', state.archivedTeachers.length);
    setText('sidebar-count-approval', (state.pendingUsers || []).length);
    setText('rombel-total-label', s.total_rombel || 0);

    setText('dist-summary', 'Total ' + (s.total_siswa_all || 0) + ' siswa terdaftar di ' + (s.total_rombel || 0) + ' rombongan belajar.');
    const distMap = {};
    (s.distribusi || []).forEach(function (row) { distMap[row.tingkat] = row.jumlah; });
    setText('dist-7', distMap[7] || 0);
    setText('dist-8', distMap[8] || 0);
    setText('dist-9', distMap[9] || 0);

    renderActivity();
  }

  function renderActivity() {
    const container = document.getElementById('overview-activity-list');
    if (!container) return;
    if (!state.activity.length) {
      container.innerHTML = '<div class="py-6 text-center text-slate-400 text-[11px]">Belum ada aktivitas guru yang tercatat.</div>';
      return;
    }
    container.innerHTML = state.activity.map(function (item) {
      const color = item.type === 'jurnal' ? 'bg-emerald-50 text-emerald-700' : 'bg-indigo-50 text-indigo-700';
      return '<div class="py-2.5 flex items-start gap-3">' +
        '<div class="w-7 h-7 rounded-lg ' + color + ' flex items-center justify-center font-bold text-[10px] flex-shrink-0 mt-0.5">' + esc(initials(item.title)) + '</div>' +
        '<div><p class="text-slate-800 font-bold">' + esc(item.title) + '</p>' +
        '<p class="text-slate-500 text-[11px]">' + esc(item.text) + '</p>' +
        '<p class="text-[10px] text-slate-400 mt-0.5">' + esc(formatRelative(item.time)) + '</p></div></div>';
    }).join('');
  }

  /* ============================================================
   * SELECT POPULATION
   * ============================================================ */
  function populateSelects() {
    const mapelFilter = document.getElementById('filter-guru-mapel');
    if (mapelFilter) {
      const current = mapelFilter.value;
      mapelFilter.innerHTML = '<option value="ALL">Semua Mata Pelajaran</option>' +
        state.mapel.map(function (m) {
          return '<option value="' + esc(m.name) + '">' + esc(m.name) + '</option>';
        }).join('');
      mapelFilter.value = current || 'ALL';
    }

    const rombelFilter = document.getElementById('filter-siswa-rombel');
    fillRombelSelect(rombelFilter, true, rombelFilter?.value || 'ALL');
    fillRombelSelect(document.getElementById('input-siswa-class'), false, 'VIII A');
    fillRombelSelect(document.getElementById('bulk-target-class'), false, 'VIII A');
    populateJenjangFilter();
  }

  function fillRombelSelect(select, includeAll, preferred) {
    if (!select) return;
    const previous = select.value;
    let html = includeAll ? '<option value="ALL">Semua Rombel</option>' : '';
    [7, 8, 9].forEach(function (grade) {
      const list = state.rombel.filter(function (r) { return Number(r.tingkat) === grade; });
      if (!list.length) return;
      html += '<optgroup label="Jenjang Kelas ' + grade + '">';
      list.forEach(function (r) {
        html += '<option value="' + esc(r.name) + '">Kelas ' + esc(r.name) + '</option>';
      });
      html += '</optgroup>';
    });
    select.innerHTML = html;
    const values = Array.prototype.map.call(select.options, function (o) { return o.value; });
    const wanted = values.indexOf(previous) !== -1 ? previous : preferred;
    if (values.indexOf(wanted) !== -1) select.value = wanted;
  }

  function populateJenjangFilter() {
    const select = document.getElementById('filter-siswa-rombel');
    if (!select) return;
    const j = document.getElementById('filter-siswa-jenjang')?.value || 'ALL';
    const current = select.value;
    let html = '<option value="ALL">Semua Rombel</option>';
    state.rombel.forEach(function (r) {
      if (j !== 'ALL' && String(r.tingkat) !== String(j)) return;
      html += '<option value="' + esc(r.name) + '">Kelas ' + esc(r.name) + '</option>';
    });
    select.innerHTML = html;
    const values = Array.prototype.map.call(select.options, function (o) { return o.value; });
    select.value = values.indexOf(current) !== -1 ? current : 'ALL';
  }

  function applySettingsForm() {
    const s = state.settings || {};
    const map = {
      'set-school-name': 'school_name',
      'set-npsn': 'npsn',
      'set-address': 'address',
      'set-headmaster': 'headmaster',
      'set-headmaster-nip': 'headmaster_nip',
      'set-tahun-ajaran': 'tahun_ajaran',
      'set-semester': 'semester',
      'set-deadline': 'deadline_nilai'
    };
    Object.keys(map).forEach(function (id) {
      const el = document.getElementById(id);
      if (el && s[map[id]] !== undefined && s[map[id]] !== null) {
        el.value = s[map[id]];
      }
    });
  }

  /* ============================================================
   * PERSETUJUAN PENDAFTARAN AKUN (APPROVAL QUEUE)
   * ============================================================ */
  function renderApprovalTable() {
    const list = state.pendingUsers || [];
    const tbody = document.getElementById('approval-table-body');
    const emptyState = document.getElementById('approval-empty-state');
    const countBadge = document.getElementById('sidebar-count-approval');
    const totalCountEl = document.getElementById('approval-total-count');
    const overviewAlert = document.getElementById('overview-pending-alert');
    const overviewText = document.getElementById('overview-pending-text');

    // Update count badges
    if (countBadge) {
      countBadge.innerText = list.length;
      countBadge.classList.toggle('hidden', list.length === 0);
    }
    if (totalCountEl) totalCountEl.innerText = list.length + ' Permintaan';

    // Overview notification banner
    if (overviewAlert) {
      overviewAlert.classList.toggle('hidden', list.length === 0);
      if (overviewText) {
        overviewText.innerText = 'Terdapat ' + list.length + ' akun baru yang mendaftar mandiri dan menunggu verifikasi Admin.';
      }
    }

    if (!tbody) return;

    if (list.length === 0) {
      tbody.innerHTML = '';
      if (emptyState) emptyState.classList.remove('hidden');
      return;
    }

    if (emptyState) emptyState.classList.add('hidden');

    tbody.innerHTML = list.map(function (u, idx) {
      const isGuru = u.role === 'guru';
      const roleBadge = isGuru
        ? '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200"><span>👨‍🏫</span> <span>Guru</span></span>'
        : '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-blue-800 border border-blue-200"><span>🎒</span> <span>Murid</span></span>';

      return '<tr class="hover:bg-slate-50 transition-colors">' +
        '<td class="py-3.5 px-4 text-center font-bold text-slate-400">' + (idx + 1) + '</td>' +
        '<td class="py-3.5 px-4">' +
          '<div class="font-extrabold text-slate-900 text-xs">' + esc(u.username) + '</div>' +
          '<div class="text-[10px] text-slate-400 font-mono mt-0.5">ID: #' + u.id + '</div>' +
        '</td>' +
        '<td class="py-3.5 px-4 text-center">' + roleBadge + '</td>' +
        '<td class="py-3.5 px-4 text-slate-600 font-medium">' + esc(u.created_at || '-') + '</td>' +
        '<td class="py-3.5 px-4 text-center">' +
          '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-300">' +
            '<span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>' +
            '<span>Menunggu</span>' +
          '</span>' +
        '</td>' +
        '<td class="py-3.5 px-4 text-center">' +
          '<div class="inline-flex items-center gap-2 justify-center">' +
            '<button onclick="approveUser(' + u.id + ', \'' + esc(u.username) + '\')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white font-bold rounded-xl text-xs shadow-xs transition-all flex items-center gap-1">' +
              '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' +
              '<span>Setujui</span>' +
            '</button>' +
            '<button onclick="rejectUser(' + u.id + ', \'' + esc(u.username) + '\')" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 active:scale-95 font-bold rounded-xl text-xs transition-all flex items-center gap-1">' +
              '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
              '<span>Tolak</span>' +
            '</button>' +
          '</div>' +
        '</td>' +
      '</tr>';
    }).join('');
  }

  async function approveUser(id, username) {
    if (!confirm('Setujui akun "' + username + '"?\nPengguna ini akan segera diaktifkan dan dapat login ke portal.')) {
      return;
    }
    try {
      showToast('Memproses...', 'Mengaktifkan akun ' + username + '...');
      const res = await api('approve_user', { id: id });
      state.pendingUsers = res.pendingUsers || [];
      if (res.stats) state.stats = res.stats;
      renderApprovalTable();
      renderStats();
      showToast('Akun Disetujui', 'Akun ' + username + ' berhasil diaktifkan.');
    } catch (err) {
      showToast('Gagal Menyetujui', err.message);
    }
  }

  async function rejectUser(id, username) {
    if (!confirm('Tolak pendaftaran akun "' + username + '"?\nData pendaftaran ini akan dihapus permanen.')) {
      return;
    }
    try {
      showToast('Memproses...', 'Menolak pendaftaran ' + username + '...');
      const res = await api('reject_user', { id: id });
      state.pendingUsers = res.pendingUsers || [];
      if (res.stats) state.stats = res.stats;
      renderApprovalTable();
      renderStats();
      showToast('Pendaftaran Ditolak', 'Permintaan akun ' + username + ' telah ditolak.');
    } catch (err) {
      showToast('Gagal Menolak', err.message);
    }
  }

  async function reloadPendingUsers() {
    try {
      showToast('Menyegarkan...', 'Mengambil data antrean pendaftaran terbaru...');
      const res = await api('get_pending_users');
      state.pendingUsers = res.pendingUsers || [];
      renderApprovalTable();
      showToast('Berhasil', 'Data antrean persetujuan telah diperbarui.');
    } catch (err) {
      showToast('Gagal Memuat', err.message);
    }
  }

  /* ============================================================
   * TAB GURU
   * ============================================================ */
  function renderTeachersTable() {
    const tbody = document.getElementById('teacher-table-body');
    if (!tbody) return;
    const search = (document.getElementById('filter-guru-search')?.value || '').toLowerCase();
    const mapelFilter = document.getElementById('filter-guru-mapel')?.value || 'ALL';
    const waliFilter = document.getElementById('filter-guru-wali')?.value || 'ALL';

    const filtered = state.teachers.filter(function (g) {
      const haystack = [g.name, g.nip, g.kode, g.mapel, g.wali, g.kelas].join(' ').toLowerCase();
      const matchSearch = !search || haystack.indexOf(search) !== -1;
      const matchMapel = mapelFilter === 'ALL' || String(g.mapel || '').toLowerCase().indexOf(mapelFilter.toLowerCase()) !== -1;
      const isWali = String(g.wali || '').indexOf('Wali Kelas') !== -1;
      const matchWali = waliFilter === 'ALL' || (waliFilter === 'WALI' && isWali) || (waliFilter === 'NON_WALI' && !isWali);
      return matchSearch && matchMapel && matchWali;
    });

    tbody.innerHTML = filtered.map(function (g, idx) {
      const isKepsek = String(g.wali || '').indexOf('Kepala Sekolah') !== -1;
      const isWali = String(g.wali || '').indexOf('Wali Kelas') !== -1;
      const waliClass = isKepsek
        ? 'text-indigo-800 font-bold bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200'
        : (isWali ? 'text-amber-800 font-bold bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200' : 'text-slate-500');
      const statusColor = g.status === 'Aktif' ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-amber-700 bg-amber-50 border-amber-200';
      return '<tr class="hover:bg-slate-50/80 transition-colors">' +
        '<td class="py-3 px-4 text-center font-bold text-slate-400">' + (idx + 1) + '</td>' +
        '<td class="py-3 px-4"><p class="font-bold text-slate-900 text-xs">' + esc(g.name) + '</p>' +
        '<div class="flex flex-wrap items-center gap-1.5 mt-0.5">' +
        '<span class="inline-block bg-slate-100 text-indigo-900 font-semibold px-1.5 py-0.5 rounded text-[10px]">Kd: ' + esc(g.kode) + '</span>' +
        '<span class="text-slate-500 font-mono text-[11px]">NIP. ' + esc(g.nip) + '</span>' +
        (g.username && g.username !== '-' ? '<span class="inline-block bg-slate-50 text-slate-600 font-mono px-1.5 py-0.5 rounded text-[10px] border border-slate-200">@' + esc(g.username) + '</span>' : '') +
        '</div></td>' +
        '<td class="py-3 px-4"><span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-800 font-semibold text-[11px] border border-indigo-100">' + esc(g.mapel) + '</span>' +
        (g.kelas && g.kelas !== '-' ? '<p class="text-[11px] text-slate-500 font-medium mt-1">Kelas: <span class="text-slate-700 font-semibold">' + esc(g.kelas) + '</span></p>' : '') + '</td>' +
        '<td class="py-3 px-4"><span class="font-medium inline-block text-[11px] ' + waliClass + '">' + esc(g.wali) + '</span></td>' +
        '<td class="py-3 px-4 text-center"><span class="text-xs font-bold text-slate-800">' + esc(g.phone) + '</span></td>' +
        '<td class="py-3 px-4 text-center"><span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full border ' + statusColor + '"><span class="w-1.5 h-1.5 rounded-full bg-current"></span> ' + esc(g.status) + '</span></td>' +
        '<td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">' +
        '<button onclick="editTeacher(' + g.id + ')" class="px-2.5 py-1 text-indigo-600 hover:text-indigo-900 rounded-lg hover:bg-indigo-50 font-bold transition-colors">Edit</button>' +
        '<button onclick="deleteTeacher(' + g.id + ')" class="px-2.5 py-1 text-rose-600 hover:text-rose-900 rounded-lg hover:bg-rose-50 font-bold transition-colors">Hapus</button>' +
        '</td></tr>';
    }).join('');

    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-400 text-xs">Tidak ada data guru yang cocok.</td></tr>';
    }

    const label = document.getElementById('teacher-count-label');
    if (label) label.innerText = 'Menampilkan ' + filtered.length + ' dari total ' + state.teachers.length + ' guru';
  }

  function openAddTeacherModal() {
    document.getElementById('modal-guru-title').innerText = 'Tambah Guru Baru';
    document.getElementById('input-guru-id').value = '';
    document.getElementById('input-guru-name').value = '';
    document.getElementById('input-guru-nip').value = '';
    document.getElementById('input-guru-mapel').value = '';
    document.getElementById('input-guru-wali').value = 'Bukan Wali Kelas';
    document.getElementById('input-guru-kelas').value = '';
    document.getElementById('input-guru-jam').value = '4';
    document.getElementById('input-guru-phone').value = '';
    document.getElementById('input-guru-pass').value = '';
    document.getElementById('input-guru-status').value = 'Aktif';
    document.getElementById('modal-guru').classList.remove('hidden');
  }

  function closeModalGuru() {
    document.getElementById('modal-guru').classList.add('hidden');
  }

  function editTeacher(id) {
    const g = state.teachers.find(function (x) { return Number(x.id) === Number(id); });
    if (!g) return;
    document.getElementById('modal-guru-title').innerText = 'Edit Data Guru (Kode: ' + g.kode + ')';
    document.getElementById('input-guru-id').value = g.id;
    document.getElementById('input-guru-name').value = g.name || '';
    document.getElementById('input-guru-nip').value = g.nip === '-' ? '' : (g.nip || '');
    document.getElementById('input-guru-mapel').value = g.mapel || '';
    document.getElementById('input-guru-wali').value = g.wali || 'Bukan Wali Kelas';
    document.getElementById('input-guru-kelas').value = g.kelas || '';
    document.getElementById('input-guru-jam').value = g.jam_per_minggu || 0;
    document.getElementById('input-guru-phone').value = g.phone === '-' ? '' : (g.phone || '');
    document.getElementById('input-guru-pass').value = '';
    document.getElementById('input-guru-status').value = g.status || 'Aktif';
    document.getElementById('modal-guru').classList.remove('hidden');
  }

  async function handleSaveTeacher(event) {
    event.preventDefault();
    const id = document.getElementById('input-guru-id').value;
    const payload = {
      id: id || 0,
      name: document.getElementById('input-guru-name').value.trim(),
      nip: document.getElementById('input-guru-nip').value.trim(),
      mapel: document.getElementById('input-guru-mapel').value.trim(),
      wali: document.getElementById('input-guru-wali').value.trim() || 'Bukan Wali Kelas',
      kelas: document.getElementById('input-guru-kelas').value.trim(),
      jam_per_minggu: document.getElementById('input-guru-jam').value || 0,
      phone: document.getElementById('input-guru-phone').value.trim(),
      status: document.getElementById('input-guru-status').value,
      password: document.getElementById('input-guru-pass').value
    };
    if (!payload.name) {
      alert('Nama guru wajib diisi.');
      return;
    }
    const button = document.getElementById('btn-save-teacher');
    if (button) { button.disabled = true; button.innerText = 'Menyimpan...'; }
    try {
      const result = await api('save_teacher', payload);
      closeModalGuru();
      await loadBootstrap(false);
      showToast('Berhasil Disimpan', result.message || 'Data guru tersimpan di database.');
    } catch (err) {
      alert('Gagal menyimpan: ' + err.message);
    } finally {
      if (button) { button.disabled = false; button.innerText = 'Simpan ke Database'; }
    }
  }

  async function deleteTeacher(id) {
    const g = state.teachers.find(function (x) { return Number(x.id) === Number(id); });
    if (!g) return;
    if (!confirm('Hapus ' + g.name + ' beserta akun login-nya dari database?')) return;
    try {
      const result = await api('delete_teacher', { id: id });
      await loadBootstrap(false);
      showToast('Guru Dihapus', result.message);
    } catch (err) {
      alert('Gagal menghapus: ' + err.message);
    }
  }

  function exportTeachersCSV() {
    let csv = 'No,Nama Lengkap,NIP,Mata Pelajaran,Kelas,Wali Kelas,Telepon,Status\n';
    state.teachers.forEach(function (g, i) {
      csv += [i + 1, csvCell(g.name), csvCell(g.nip), csvCell(g.mapel), csvCell(g.kelas), csvCell(g.wali), csvCell(g.phone), csvCell(g.status)].join(',') + '\n';
    });
    downloadFile(csv, 'Data_Guru_SMPN7_Purwakarta.csv');
    showToast('Download Berhasil', 'Data guru berhasil diunduh dari database.');
  }

  function openUploadTeacherModal() {
    document.getElementById('modal-upload-guru').classList.remove('hidden');
  }

  function closeModalUploadGuru() {
    document.getElementById('modal-upload-guru').classList.add('hidden');
  }

  function downloadTeacherCSVTemplate() {
    const template = 'NIP,NAMA_LENGKAP,MATA_PELAJARAN,TUGAS_TAMBAHAN_WALI,NO_TELEPON,STATUS\n19850101 201001 1 001,Nama Guru Contoh,Matematika,Wali Kelas VII A,08123456789,Aktif\n';
    downloadFile(template, 'Template_Upload_Guru_SMPN7.csv');
  }

  async function handleImportTeachers(event) {
    event.preventDefault();
    const form = event.target;
    if (!form.querySelector('input[type="file"]').files.length) {
      alert('Pilih berkas CSV terlebih dahulu.');
      return;
    }
    try {
      const result = await api('import_teachers', {}, new FormData(form));
      closeModalUploadGuru();
      form.reset();
      await loadBootstrap(false);
      showToast('Import Selesai', result.message || 'Data guru berhasil diimpor.');
    } catch (err) {
      alert('Import gagal: ' + err.message);
    }
  }

  /* ============================================================
   * TAB SISWA
   * ============================================================ */
  function renderStudentsTable() {
    const tbody = document.getElementById('student-table-body');
    if (!tbody) return;
    const search = (document.getElementById('filter-siswa-search')?.value || '').toLowerCase();
    const jenjang = document.getElementById('filter-siswa-jenjang')?.value || 'ALL';
    const rombel = document.getElementById('filter-siswa-rombel')?.value || 'ALL';
    const status = document.getElementById('filter-siswa-status')?.value || 'ALL';

    const filtered = state.students.filter(function (s) {
      const matchSearch = !search || String(s.name).toLowerCase().indexOf(search) !== -1 || String(s.nisn).toLowerCase().indexOf(search) !== -1;
      const matchJenjang = jenjang === 'ALL' || String(s.tingkat) === String(jenjang);
      const matchRombel = rombel === 'ALL' || s.class === rombel;
      const matchStatus = status === 'ALL' || s.status === status;
      return matchSearch && matchJenjang && matchRombel && matchStatus;
    });

    currentFilteredStudentIds = filtered.map(function (s) { return s.id; });

    tbody.innerHTML = filtered.map(function (s, idx) {
      const checked = selectedStudentIds.has(s.id);
      const rowClass = checked ? 'bg-indigo-50/60 transition-colors' : 'hover:bg-slate-50/80 transition-colors';
      return '<tr class="' + rowClass + '">' +
        '<td class="py-3 px-3 text-center"><input type="checkbox" class="student-row-checkbox w-4 h-4 rounded border-slate-300 text-indigo-950 focus:ring-indigo-600 cursor-pointer" value="' + s.id + '" ' + (checked ? 'checked' : '') + ' onchange="handleStudentRowCheck(' + s.id + ', this.checked)"></td>' +
        '<td class="py-3 px-4 text-center font-bold text-slate-400">' + (idx + 1) + '</td>' +
        '<td class="py-3 px-4 font-mono font-bold text-slate-800">' + esc(s.nisn) + '</td>' +
        '<td class="py-3 px-4 font-bold text-indigo-950">' + esc(s.name) + '</td>' +
        '<td class="py-3 px-4 text-center"><span class="px-2 py-0.5 rounded text-[10px] font-bold ' + (s.gender === 'L' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800') + '">' + esc(s.gender) + '</span></td>' +
        '<td class="py-3 px-4 font-bold text-slate-700"><span class="px-2 py-1 rounded-lg bg-slate-100 border border-slate-200">' + esc(s.class) + '</span></td>' +
        '<td class="py-3 px-4 text-slate-600">' + esc(s.parent) + '</td>' +
        '<td class="py-3 px-4 font-mono text-slate-500 text-[11px]">' + esc(s.phone) + '</td>' +
        '<td class="py-3 px-4 text-center">' + statusBadge(s.status) + '</td>' +
        '<td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">' +
        '<button onclick="editStudent(' + s.id + ')" class="p-1 text-indigo-600 hover:text-indigo-900 rounded-lg hover:bg-indigo-50 font-bold">Edit</button>' +
        '<button onclick="deleteStudent(' + s.id + ')" class="p-1 text-rose-600 hover:text-rose-900 rounded-lg hover:bg-rose-50 font-bold">Hapus</button>' +
        '</td></tr>';
    }).join('');

    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="10" class="py-8 text-center text-slate-400 text-xs">Tidak ada data siswa yang cocok.</td></tr>';
    }

    const label = document.getElementById('student-count-label');
    if (label) label.innerText = 'Menampilkan ' + filtered.length + ' dari ' + state.students.length + ' siswa';
    updateBulkToolbar();
  }

  function toggleSelectAllStudents(checked) {
    if (checked) {
      currentFilteredStudentIds.forEach(function (id) { selectedStudentIds.add(id); });
    } else {
      currentFilteredStudentIds.forEach(function (id) { selectedStudentIds.delete(id); });
    }
    renderStudentsTable();
  }

  function handleStudentRowCheck(id, checked) {
    if (checked) selectedStudentIds.add(Number(id));
    else selectedStudentIds.delete(Number(id));
    updateBulkToolbar();
  }

  function updateBulkToolbar() {
    const count = selectedStudentIds.size;
    const toolbar = document.getElementById('bulk-student-toolbar');
    const badge = document.getElementById('bulk-selected-count');
    const checkAll = document.getElementById('check-all-students');
    if (badge) badge.innerText = count;
    if (toolbar) toolbar.classList.toggle('hidden', count === 0);
    if (checkAll) {
      checkAll.checked = currentFilteredStudentIds.length > 0 && currentFilteredStudentIds.every(function (id) { return selectedStudentIds.has(id); });
    }
  }

  function clearAllStudentSelection() {
    selectedStudentIds.clear();
    renderStudentsTable();
  }

  async function applyBulkClassMove() {
    if (!selectedStudentIds.size) {
      alert('Pilih siswa terlebih dahulu.');
      return;
    }
    const target = document.getElementById('bulk-target-class').value;
    try {
      const result = await api('bulk_students', { ids: Array.from(selectedStudentIds), class: target });
      await loadBootstrap(false);
      showToast('Rombel Diperbarui', result.message);
    } catch (err) {
      alert('Gagal memindahkan: ' + err.message);
    }
  }

  async function applyBulkStatusChange() {
    if (!selectedStudentIds.size) {
      alert('Pilih siswa terlebih dahulu.');
      return;
    }
    const target = document.getElementById('bulk-target-status').value;
    try {
      const result = await api('bulk_students', { ids: Array.from(selectedStudentIds), status: target });
      await loadBootstrap(false);
      showToast('Status Diperbarui', result.message);
    } catch (err) {
      alert('Gagal mengubah status: ' + err.message);
    }
  }

  function handleJenjangChange() {
    populateJenjangFilter();
    renderStudentsTable();
  }

  function openAddStudentModal() {
    document.getElementById('input-siswa-id').value = '';
    document.getElementById('modal-siswa-title').innerText = 'Tambah Siswa Baru';
    document.getElementById('modal-siswa-desc').innerText = 'Masukkan data peserta didik ke buku induk sekolah.';
    document.getElementById('modal-siswa-submit-text').innerText = 'Daftarkan Siswa';
    document.getElementById('input-siswa-name').value = '';
    document.getElementById('input-siswa-nisn').value = '';
    document.getElementById('input-siswa-gender').value = 'L';
    document.getElementById('input-siswa-status').value = 'Aktif';
    document.getElementById('input-siswa-parent').value = '';
    document.getElementById('input-siswa-phone').value = '';
    fillRombelSelect(document.getElementById('input-siswa-class'), false, 'VIII A');
    document.getElementById('modal-siswa').classList.remove('hidden');
  }

  function editStudent(id) {
    const s = state.students.find(function (x) { return Number(x.id) === Number(id); });
    if (!s) return;
    document.getElementById('input-siswa-id').value = s.id;
    document.getElementById('modal-siswa-title').innerText = 'Edit Data Siswa';
    document.getElementById('modal-siswa-desc').innerText = 'Perbarui data induk untuk ' + s.name + ' (' + s.nisn + ').';
    document.getElementById('modal-siswa-submit-text').innerText = 'Simpan Perubahan Siswa';
    document.getElementById('input-siswa-name').value = s.name;
    document.getElementById('input-siswa-nisn').value = s.nisn === '-' ? '' : s.nisn;
    document.getElementById('input-siswa-gender').value = s.gender;
    document.getElementById('input-siswa-status').value = s.status;
    document.getElementById('input-siswa-parent').value = s.parent === '-' ? '' : s.parent;
    document.getElementById('input-siswa-phone').value = s.phone === '-' ? '' : s.phone;
    fillRombelSelect(document.getElementById('input-siswa-class'), false, s.class);
    document.getElementById('modal-siswa').classList.remove('hidden');
  }

  function closeModalSiswa() {
    document.getElementById('modal-siswa').classList.add('hidden');
  }

  async function handleSaveStudent(event) {
    event.preventDefault();
    const payload = {
      id: document.getElementById('input-siswa-id').value || 0,
      nisn: document.getElementById('input-siswa-nisn').value.trim(),
      name: document.getElementById('input-siswa-name').value.trim(),
      gender: document.getElementById('input-siswa-gender').value,
      class: document.getElementById('input-siswa-class').value,
      status: document.getElementById('input-siswa-status').value,
      parent: document.getElementById('input-siswa-parent').value.trim() || '-',
      phone: document.getElementById('input-siswa-phone').value.trim() || '-'
    };
    if (!payload.name) {
      alert('Nama siswa wajib diisi.');
      return;
    }
    try {
      const result = await api('save_student', payload);
      closeModalSiswa();
      await loadBootstrap(false);
      showToast('Berhasil', result.message);
    } catch (err) {
      alert('Gagal menyimpan: ' + err.message);
    }
  }

  async function deleteStudent(id) {
    const s = state.students.find(function (x) { return Number(x.id) === Number(id); });
    if (!s) return;
    if (!confirm('Hapus ' + s.name + ' dari buku induk? Tindakan ini juga menghapus nilai dan presensinya.')) return;
    try {
      const result = await api('delete_student', { id: id });
      await loadBootstrap(false);
      showToast('Terhapus', result.message);
    } catch (err) {
      alert('Gagal menghapus: ' + err.message);
    }
  }

  function openImportModal() {
    document.getElementById('modal-import').classList.remove('hidden');
  }

  function closeModalImport() {
    document.getElementById('modal-import').classList.add('hidden');
  }

  function downloadCSVTemplate() {
    const template = 'NISN,NAMA_LENGKAP,JENIS_KELAMIN,ROMBEL,NAMA_ORANG_TUA,NO_TELEPON,STATUS\n0098427999,Nama Contoh,L,VIII A,Orang Tua Contoh,08123456789,Aktif\n';
    downloadFile(template, 'Template_Upload_Siswa_SMPN7.csv');
  }

  function exportStudentsCSV() {
    let csv = 'No,NISN,Nama Siswa,JK,Kelas,Nama Wali,No HP,Status\n';
    state.students.forEach(function (s, i) {
      csv += [i + 1, csvCell(s.nisn), csvCell(s.name), csvCell(s.gender), csvCell(s.class), csvCell(s.parent), csvCell(s.phone), csvCell(s.status)].join(',') + '\n';
    });
    downloadFile(csv, 'Data_Siswa_SMPN7_Purwakarta.csv');
    showToast('Download Berhasil', 'Data siswa berhasil diunduh dari database.');
  }

  async function handleImportStudents(event) {
    event.preventDefault();
    const form = event.target;
    if (!form.querySelector('input[type="file"]').files.length) {
      alert('Pilih berkas CSV terlebih dahulu.');
      return;
    }
    try {
      const result = await api('import_students', {}, new FormData(form));
      closeModalImport();
      form.reset();
      await loadBootstrap(false);
      showToast('Import Selesai', result.message || 'Data siswa berhasil diimpor.');
    } catch (err) {
      alert('Import gagal: ' + err.message);
    }
  }

  /* ============================================================
   * TAB MAPEL
   * ============================================================ */
  function renderMapelTable() {
    const tbody = document.getElementById('mapel-table-body');
    if (!tbody) return;
    tbody.innerHTML = state.mapel.map(function (m, idx) {
      return '<tr class="hover:bg-slate-50/80 transition-colors">' +
        '<td class="py-3 px-4 font-mono font-bold text-slate-500 text-center">' + esc(m.code) + '</td>' +
        '<td class="py-3 px-4 font-bold text-slate-900">' + esc(m.name) + '</td>' +
        '<td class="py-3 px-4"><span class="text-[11px] font-medium text-slate-600 bg-slate-100 px-2 py-0.5 rounded">' + esc(m.group) + '</span></td>' +
        '<td class="py-3 px-4 text-center font-bold text-slate-700">' + m.hours + ' JP</td>' +
        '<td class="py-3 px-4 text-center"><span class="px-2.5 py-1 rounded-lg font-black text-amber-900 bg-amber-100 border border-amber-200">' + m.kkm + '</span></td>' +
        '<td class="py-3 px-4 text-slate-700 font-semibold">' + esc(m.lead) + '</td>' +
        '<td class="py-3 px-4 text-center text-slate-600">' + m.rombelCovered + ' Kelas</td>' +
        '<td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">' +
        '<button onclick="editMapel(\'' + esc(m.code) + '\')" class="p-1 text-indigo-600 hover:text-indigo-900 font-bold hover:bg-indigo-50 rounded">Edit</button>' +
        '<button onclick="deleteMapel(\'' + esc(m.code) + '\')" class="p-1 text-rose-600 hover:text-rose-900 font-bold hover:bg-rose-50 rounded">Hapus</button>' +
        '</td></tr>';
    }).join('');
    if (!state.mapel.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="py-8 text-center text-slate-400 text-xs">Belum ada mata pelajaran.</td></tr>';
    }
  }

  function openAddMapelModal() {
    const nextNum = state.mapel.length + 1;
    document.getElementById('input-mapel-orig-code').value = '';
    document.getElementById('modal-mapel-title').innerText = 'Tambah Mata Pelajaran Baru';
    document.getElementById('modal-mapel-desc').innerText = 'Isi rincian kurikulum mapel secara manual oleh Administrator.';
    document.getElementById('modal-mapel-submit-text').innerText = 'Simpan Mata Pelajaran';
    document.getElementById('input-mapel-code').value = 'MP-' + String(nextNum).padStart(2, '0');
    document.getElementById('input-mapel-name').value = '';
    document.getElementById('input-mapel-group').value = 'Kelompok B (Pengembangan)';
    document.getElementById('input-mapel-hours').value = 2;
    document.getElementById('input-mapel-kkm').value = 75;
    document.getElementById('input-mapel-lead').value = '';
    document.getElementById('input-mapel-rombel-count').value = '15';
    document.getElementById('modal-mapel').classList.remove('hidden');
  }

  function editMapel(code) {
    const m = state.mapel.find(function (x) { return x.code === code; });
    if (!m) return;
    document.getElementById('input-mapel-orig-code').value = m.code;
    document.getElementById('modal-mapel-title').innerText = 'Edit Mata Pelajaran';
    document.getElementById('modal-mapel-desc').innerText = 'Perbarui rincian kurikulum untuk ' + m.name + ' (' + m.code + ').';
    document.getElementById('modal-mapel-submit-text').innerText = 'Simpan Perubahan Mapel';
    document.getElementById('input-mapel-code').value = m.code;
    document.getElementById('input-mapel-name').value = m.name;
    document.getElementById('input-mapel-group').value = m.group;
    document.getElementById('input-mapel-hours').value = m.hours;
    document.getElementById('input-mapel-kkm').value = m.kkm;
    document.getElementById('input-mapel-lead').value = m.lead === 'Belum Ditugaskan' ? '' : m.lead;
    document.getElementById('input-mapel-rombel-count').value = String(m.rombelCovered);
    document.getElementById('modal-mapel').classList.remove('hidden');
  }

  function closeModalMapel() {
    document.getElementById('modal-mapel').classList.add('hidden');
  }

  async function handleSaveMapel(event) {
    event.preventDefault();
    const payload = {
      orig_code: document.getElementById('input-mapel-orig-code').value,
      code: document.getElementById('input-mapel-code').value.trim(),
      name: document.getElementById('input-mapel-name').value.trim(),
      group: document.getElementById('input-mapel-group').value,
      hours: document.getElementById('input-mapel-hours').value,
      kkm: document.getElementById('input-mapel-kkm').value,
      lead: document.getElementById('input-mapel-lead').value.trim(),
      rombel_covered: document.getElementById('input-mapel-rombel-count').value
    };
    if (!payload.code || !payload.name) {
      alert('Kode dan nama mata pelajaran wajib diisi.');
      return;
    }
    try {
      const result = await api('save_mapel', payload);
      closeModalMapel();
      await loadBootstrap(false);
      showToast('Mata Pelajaran Disimpan', result.message);
    } catch (err) {
      alert('Gagal menyimpan: ' + err.message);
    }
  }

  async function deleteMapel(code) {
    const m = state.mapel.find(function (x) { return x.code === code; });
    if (!m) return;
    if (!confirm('Hapus mata pelajaran ' + m.name + '?')) return;
    try {
      const result = await api('delete_mapel', { code: code });
      await loadBootstrap(false);
      showToast('Mata Pelajaran Dihapus', result.message);
    } catch (err) {
      alert('Gagal menghapus: ' + err.message);
    }
  }

  /* ============================================================
   * TAB ROMBEL
   * ============================================================ */
  function renderRombelCards(gradeFilter) {
    const grid = document.getElementById('rombel-cards-grid');
    if (!grid) return;
    const filter = gradeFilter || 'ALL';

    ['all', '7', '8', '9'].forEach(function (g) {
      const btn = document.getElementById('btn-rf-' + g);
      if (!btn) return;
      const active = g.toLowerCase() === String(filter).toLowerCase();
      btn.className = 'px-3 py-1 rounded-lg ' + (active ? 'bg-white shadow-xs text-indigo-950 font-bold' : 'text-slate-600 hover:text-slate-900 font-semibold');
    });

    const countAll = state.rombel.length;
    [7, 8, 9].forEach(function (grade) {
      const el = document.getElementById('rf-count-' + grade);
      if (el) el.innerText = state.rombel.filter(function (r) { return Number(r.tingkat) === grade; }).length;
    });
    const allEl = document.getElementById('rf-count-all');
    if (allEl) allEl.innerText = countAll;

    const filtered = state.rombel.filter(function (r) {
      return filter === 'ALL' || String(r.tingkat) === String(filter);
    });

    grid.innerHTML = filtered.map(function (r) {
      const fill = r.max > 0 ? Math.min(100, Math.round((r.count / r.max) * 100)) : 0;
      return '<div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between hover:border-indigo-500 hover:shadow-md transition-all group">' +
        '<div>' +
        '<div class="flex items-center justify-between mb-3">' +
        '<span class="w-10 h-10 rounded-xl bg-indigo-950 text-amber-300 font-extrabold flex items-center justify-center text-sm shadow-sm">' + esc(r.name) + '</span>' +
        '<span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md">' + esc(r.room) + '</span></div>' +
        '<div class="flex items-center justify-between"><h4 class="font-black text-slate-900 text-sm">Kelas ' + esc(r.name) + '</h4>' +
        '<button onclick="openDetailRombelModal(\'' + esc(r.name) + '\')" class="text-[10px] text-indigo-600 font-bold hover:underline">Pantau &rarr;</button></div>' +
        '<p class="text-slate-500 text-[11px] mt-1.5 truncate">Wali: <strong>' + esc(r.wali) + '</strong></p>' +
        '<div class="mt-3 h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-indigo-600" style="width:' + fill + '%"></div></div>' +
        '</div>' +
        '<div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">' +
        '<span class="font-semibold text-slate-600">' + r.count + ' / ' + r.max + ' Siswa</span>' +
        '<div class="flex items-center gap-2">' +
        '<button onclick="changeWaliKelas(\'' + esc(r.name) + '\')" class="text-slate-400 hover:text-indigo-600 p-1 rounded font-semibold text-[10px] hover:bg-slate-50">Ganti Wali</button>' +
        '<button onclick="openDetailRombelModal(\'' + esc(r.name) + '\')" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-900 font-bold rounded-lg text-[10px]">Buka</button>' +
        '</div></div></div>';
    }).join('');

    if (!filtered.length) {
      grid.innerHTML = '<div class="col-span-full py-8 text-center text-slate-400 text-xs">Belum ada rombel.</div>';
    }
  }

  function filterRombelCards(grade) {
    renderRombelCards(grade);
  }

  async function changeWaliKelas(rombelName) {
    const current = state.rombel.find(function (r) { return r.name === rombelName; });
    const answer = prompt('Ketik NAMA LENGKAP guru yang akan menjadi wali kelas ' + rombelName + ':', current ? current.wali : '');
    if (!answer) return;
    const teacher = state.teachers.find(function (t) { return t.name.toLowerCase() === answer.trim().toLowerCase(); });
    if (!teacher) {
      alert('Guru dengan nama tersebut tidak ditemukan. Ketik nama persis seperti pada tabel Data Guru.');
      return;
    }
    try {
      const result = await api('save_rombel_wali', { rombel: rombelName, wali_guru_id: teacher.id });
      await loadBootstrap(false);
      showToast('Wali Kelas Diperbarui', result.message);
    } catch (err) {
      alert('Gagal memperbarui wali kelas: ' + err.message);
    }
  }

  async function openAddRombelModal() {
    const nama = prompt('Nama rombel baru (contoh: VIII J):');
    if (!nama) return;
    const tingkat = prompt('Tingkat (7, 8, atau 9):', '8');
    if (!tingkat || [7, 8, 9].indexOf(Number(tingkat)) === -1) {
      alert('Tingkat harus 7, 8, atau 9.');
      return;
    }
    const ruang = prompt('Nama ruang (opsional):', '-') || '-';
    const kapasitas = prompt('Kapasitas maksimal siswa:', '32') || 32;
    try {
      const result = await api('save_rombel', { nama: nama, tingkat: tingkat, ruang: ruang, kapasitas: kapasitas });
      await loadBootstrap(false);
      showToast('Rombel Dibuat', result.message);
    } catch (err) {
      alert('Gagal membuat rombel: ' + err.message);
    }
  }

  async function openDetailRombelModal(rombelName) {
    currentDetailRombel = rombelName;
    try {
      const data = await api('rombel_detail', { rombel: rombelName });
      state.detailRombel = data;
      const r = data.rombel || {};
      document.getElementById('detail-rombel-badge').innerText = rombelName.replace(/\s+/g, '');
      document.getElementById('detail-rombel-title').innerText = 'Pemantauan Kelas ' + rombelName;
      document.getElementById('detail-rombel-sub').innerText = 'Wali Kelas: ' + (r.wali || '-') + ' · Ruang: ' + (r.room || '-') + ' · ' + (r.count || 0) + ' / ' + (r.max || 0) + ' Siswa Aktif';
      renderDetailRombelMapel();
      renderDetailRombelSiswa();
      switchRombelDetailTab('mapel');
      document.getElementById('modal-detail-rombel').classList.remove('hidden');
    } catch (err) {
      alert('Gagal memuat detail rombel: ' + err.message);
    }
  }

  function closeDetailRombelModal() {
    document.getElementById('modal-detail-rombel').classList.add('hidden');
  }

  function switchRombelDetailTab(tab) {
    const isMapel = tab === 'mapel';
    document.getElementById('sub-view-rombel-mapel').classList.toggle('hidden', !isMapel);
    document.getElementById('sub-view-rombel-siswa').classList.toggle('hidden', isMapel);
    const btnMapel = document.getElementById('btn-sub-rombel-mapel');
    const btnSiswa = document.getElementById('btn-sub-rombel-siswa');
    if (isMapel) {
      btnMapel.className = 'px-4 py-1.5 rounded-lg bg-indigo-950 text-white shadow-xs font-bold';
      btnSiswa.className = 'px-4 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 font-semibold';
    } else {
      btnSiswa.className = 'px-4 py-1.5 rounded-lg bg-indigo-950 text-white shadow-xs font-bold';
      btnMapel.className = 'px-4 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 font-semibold';
    }
  }

  function renderDetailRombelMapel() {
    const tbody = document.getElementById('detail-rombel-mapel-tbody');
    const detail = state.detailRombel;
    if (!tbody || !detail) return;
    const rows = detail.mapel || [];
    const complete = rows.filter(function (m) { return m.status === 'Lengkap'; }).length;
    const badge = document.getElementById('detail-rombel-complete-badge');
    if (badge) badge.innerText = complete + ' / ' + rows.length + ' Mapel Lengkap';

    tbody.innerHTML = rows.map(function (m, idx) {
      const statusClass = m.status === 'Lengkap' ? 'bg-emerald-100 text-emerald-800' : (m.status === 'Aktif Mengisi' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600');
      return '<tr class="hover:bg-slate-50 transition-colors">' +
        '<td class="py-2.5 px-4 text-center font-bold text-slate-400">' + (idx + 1) + '</td>' +
        '<td class="py-2.5 px-4 font-bold text-slate-800">' + esc(m.name) + '</td>' +
        '<td class="py-2.5 px-4 text-slate-600">' + esc(m.lead) + '</td>' +
        '<td class="py-2.5 px-4 text-center font-mono font-bold text-slate-500">' + m.kkm + '</td>' +
        '<td class="py-2.5 px-4 text-center"><span class="font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">' + m.columns + ' Kolom</span></td>' +
        '<td class="py-2.5 px-4 text-center font-black ' + (m.avg !== '-' && Number(m.avg) >= m.kkm ? 'text-indigo-950' : 'text-rose-600') + '">' + (m.avg === '-' ? '-' : Number(m.avg).toFixed(1)) + '</td>' +
        '<td class="py-2.5 px-4 text-center"><span class="font-semibold text-slate-700">' + m.passPct + '% Tuntas</span>' +
        ' <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold ' + statusClass + '">' + esc(m.status) + '</span></td>' +
        '<td class="py-2.5 px-4 text-right"><button onclick="switchAdminTab(\'monitoring-guru\')" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-900 rounded-lg font-bold text-[11px]">Pantau &rarr;</button></td>' +
        '</tr>';
    }).join('');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="py-6 text-center text-slate-400 text-xs">Belum ada data mata pelajaran.</td></tr>';
    }
  }

  function renderDetailRombelSiswa() {
    const tbody = document.getElementById('detail-rombel-siswa-tbody');
    const detail = state.detailRombel;
    if (!tbody || !detail) return;
    const students = detail.students || [];

    let totalAvg = 0;
    let avgCount = 0;
    let totalAtt = 0;
    let attCount = 0;
    let passed = 0;

    students.forEach(function (s) {
      if (s.avg !== '-') { totalAvg += Number(s.avg); avgCount++; }
      if (s.attend !== null) { totalAtt += Number(s.attend); attCount++; }
      if (s.avg !== '-' && Number(s.avg) >= 75) passed++;
    });

    const classAvg = avgCount ? (totalAvg / avgCount).toFixed(1) : '0.0';
    const attendAvg = attCount ? (totalAtt / attCount).toFixed(1) : '0.0';
    const avgEl = document.getElementById('detail-rombel-class-avg');
    const attEl = document.getElementById('detail-rombel-attend-pct');
    const passEl = document.getElementById('detail-rombel-pass-count');
    if (avgEl) avgEl.innerText = classAvg;
    if (attEl) attEl.innerText = attendAvg + '%';
    if (passEl) passEl.innerText = passed + ' / ' + students.length + ' Tuntas';

    tbody.innerHTML = students.map(function (s, idx) {
      const isPassed = s.avg !== '-' && Number(s.avg) >= 75;
      return '<tr class="hover:bg-slate-50 transition-colors">' +
        '<td class="py-2.5 px-4 text-center font-bold text-slate-400">' + (idx + 1) + '</td>' +
        '<td class="py-2.5 px-4 font-bold text-slate-800">' + esc(s.name) + '</td>' +
        '<td class="py-2.5 px-4 font-mono text-slate-500">' + esc(s.nisn) + '</td>' +
        '<td class="py-2.5 px-4 text-center font-extrabold ' + (isPassed ? 'text-indigo-950' : 'text-rose-600') + '">' + (s.avg === '-' ? '-' : Number(s.avg).toFixed(1)) + '</td>' +
        '<td class="py-2.5 px-4 text-center font-semibold text-slate-700">' + (s.attend === null ? '-' : Number(s.attend).toFixed(1) + '%') + '</td>' +
        '<td class="py-2.5 px-4 text-center"><span class="px-2 py-0.5 rounded-full font-bold text-[10px] ' + (isPassed ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800') + '">' + (isPassed ? 'Tuntas' : 'Remedial') + '</span></td>' +
        '<td class="py-2.5 px-4 text-slate-500 text-[11px] truncate max-w-[180px]">' + esc(s.catatan) + '</td>' +
        '</tr>';
    }).join('');
    if (!students.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="py-6 text-center text-slate-400 text-xs">Belum ada siswa aktif di rombel ini.</td></tr>';
    }
  }

  function downloadRombelDetailCSV() {
    const detail = state.detailRombel;
    if (!detail) return;
    let csv = 'REKAP PEMANTAUAN KELAS ' + currentDetailRombel + '\nNo,Nama Siswa,NISN,Rata-Rata Nilai,Kehadiran,Catatan\n';
    (detail.students || []).forEach(function (s, i) {
      csv += [i + 1, csvCell(s.name), csvCell(s.nisn), s.avg === '-' ? '' : s.avg, s.attend === null ? '' : s.attend, csvCell(s.catatan)].join(',') + '\n';
    });
    downloadFile(csv, 'Pemantauan_Rombel_' + currentDetailRombel.replace(/\s+/g, '_') + '.csv');
    showToast('Download Berhasil', 'Rekap rombel berhasil diunduh.');
  }

  /* ============================================================
   * TAB MONITORING GURU
   * ============================================================ */
  function renderMonitoringGuruTable() {
    const tbody = document.getElementById('monitoring-guru-table-body');
    if (!tbody) return;
    const search = (document.getElementById('filter-mon-search')?.value || '').toLowerCase();
    const grade = document.getElementById('filter-mon-grade')?.value || 'ALL';
    const status = document.getElementById('filter-mon-status')?.value || 'ALL';

    const filtered = state.monitoring.filter(function (item) {
      const haystack = (item.teacherName + ' ' + item.subject + ' ' + item.className).toLowerCase();
      const matchSearch = !search || haystack.indexOf(search) !== -1;
      const matchGrade = grade === 'ALL' || String(item.grade) === String(grade);
      const matchStatus = status === 'ALL' || item.status === status;
      return matchSearch && matchGrade && matchStatus;
    });

    tbody.innerHTML = filtered.map(function (item, idx) {
      let badge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Aktif Mengisi</span>';
      if (item.status === 'Lengkap') badge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Lengkap</span>';
      if (item.status === 'Belum Input') badge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">Belum Input</span>';
      const avg = item.classAvg === '-' ? '-' : Number(item.classAvg).toFixed(1);
      return '<tr class="hover:bg-slate-50 transition-colors">' +
        '<td class="py-3 px-4 text-center font-bold text-slate-400">' + (idx + 1) + '</td>' +
        '<td class="py-3 px-4"><p class="font-bold text-slate-800 text-xs">' + esc(item.teacherName) + '</p><p class="text-[11px] text-slate-400">Guru Pengampu</p></td>' +
        '<td class="py-3 px-4"><p class="font-bold text-indigo-950">' + esc(item.subject) + '</p><span class="inline-block mt-0.5 px-2 py-0.2 bg-slate-100 text-slate-700 font-bold rounded text-[10px]">Kelas ' + esc(item.className) + '</span></td>' +
        '<td class="py-3 px-4 text-center font-semibold text-slate-700">' + (item.columnsCount > 0 ? '<strong>' + item.columnsCount + '</strong> Kolom Nilai' : '<span class="text-slate-400">-</span>') + '</td>' +
        '<td class="py-3 px-4 text-center font-black ' + (avg !== '-' && Number(avg) >= item.kkm ? 'text-indigo-950' : 'text-rose-600') + '">' + avg + '</td>' +
        '<td class="py-3 px-4 text-center text-slate-600 font-medium">' + item.journalCount + ' Jurnal · ' + item.attendanceCount + ' Sesi Presensi</td>' +
        '<td class="py-3 px-4 text-slate-500 text-[11px]">' + esc(item.lastUpdate) + '</td>' +
        '<td class="py-3 px-4 text-center">' + badge + '</td>' +
        '<td class="py-3 px-4 text-right"><button onclick="openCheckTeacherInputModal(' + item.id + ')" class="px-3 py-1.5 bg-indigo-950 hover:bg-indigo-900 text-white rounded-xl font-bold text-xs shadow-2xs">Cek Hasil Input</button></td>' +
        '</tr>';
    }).join('');
    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="py-8 text-center text-slate-400 text-xs">Belum ada penugasan guru yang dapat dipantau.</td></tr>';
    }
    const label = document.getElementById('mon-guru-count-label');
    if (label) label.innerText = 'Menampilkan ' + filtered.length + ' dari ' + state.monitoring.length + ' pemantauan guru';
  }

  async function openCheckTeacherInputModal(id) {
    try {
      const data = await api('monitoring_detail', { id: id });
      const p = data.pengampu || {};
      document.getElementById('cek-input-modal-subtitle').innerText = p.mapel + ' · Kelas ' + p.rombel + ' · ' + p.nama_guru;
      document.getElementById('cek-input-col-count').innerText = Object.keys(data.columns || {}).length + ' Kolom Penilaian';
      const students = data.students || [];
      let total = 0;
      let count = 0;
      students.forEach(function (s) { if (s.avg !== '-') { total += Number(s.avg); count++; } });
      document.getElementById('cek-input-avg').innerText = count ? (total / count).toFixed(1) : '-';
      document.getElementById('cek-input-journal-count').innerText = (data.journalCount || 0) + ' Pertemuan Terisi';
      document.getElementById('cek-input-time').innerText = 'Data langsung dari database';

      const columns = Object.keys(data.columns || {});
      const thead = document.getElementById('cek-input-table-head');
      const tbody = document.getElementById('cek-input-table-body');

      if (!columns.length) {
        thead.innerHTML = '<tr class="bg-slate-50 border-b border-slate-200"><th class="py-2.5 px-4">NISN</th><th class="py-2.5 px-4">Nama Siswa</th><th class="py-2.5 px-4 text-center">Status</th></tr>';
        tbody.innerHTML = '<tr><td colspan="3" class="py-6 text-center text-slate-400 text-xs">Guru belum membuat kolom nilai untuk kelas ini.</td></tr>';
      } else {
        thead.innerHTML = '<tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">' +
          '<th class="py-2.5 px-3 w-10 text-center">No</th><th class="py-2.5 px-3">NISN</th><th class="py-2.5 px-4">Nama Siswa</th>' +
          columns.map(function (c) { return '<th class="py-2.5 px-3 text-center bg-indigo-50/50">' + esc(c) + '</th>'; }).join('') +
          '<th class="py-2.5 px-3 text-center bg-amber-50 font-bold text-amber-950">Rata-Rata</th></tr>';

        tbody.innerHTML = students.map(function (s, i) {
          return '<tr class="hover:bg-slate-50 transition-colors">' +
            '<td class="py-2.5 px-3 text-center font-bold text-slate-400">' + (i + 1) + '</td>' +
            '<td class="py-2.5 px-3 font-mono text-slate-500">' + esc(s.nisn) + '</td>' +
            '<td class="py-2.5 px-4 font-bold text-slate-800">' + esc(s.name) + '</td>' +
            columns.map(function (c) {
              const value = s.scores && s.scores[c] !== undefined ? s.scores[c] : '-';
              return '<td class="py-2.5 px-3 text-center font-semibold text-slate-700">' + value + '</td>';
            }).join('') +
            '<td class="py-2.5 px-3 text-center font-black ' + (s.avg !== '-' && Number(s.avg) >= Number(p.kkm) ? 'text-indigo-950 bg-indigo-50/30' : 'text-rose-600 bg-rose-50/20') + '">' + s.avg + '</td></tr>';
        }).join('');
        if (!students.length) {
          tbody.innerHTML = '<tr><td colspan="' + (columns.length + 4) + '" class="py-6 text-center text-slate-400 text-xs">Belum ada siswa.</td></tr>';
        }
      }

      document.getElementById('modal-cek-input-guru').classList.remove('hidden');
    } catch (err) {
      alert('Gagal memuat hasil input: ' + err.message);
    }
  }

  function closeCheckTeacherInputModal() {
    document.getElementById('modal-cek-input-guru').classList.add('hidden');
  }

  /* ============================================================
   * ARSIP SISWA
   * ============================================================ */
  function renderArchivedStudentsTable() {
    const tbody = document.getElementById('arsip-siswa-table-body');
    if (!tbody) return;
    const search = (document.getElementById('filter-arsip-siswa-search')?.value || '').toLowerCase();
    const status = document.getElementById('filter-arsip-siswa-status')?.value || 'ALL';
    const year = document.getElementById('filter-arsip-siswa-year')?.value || 'ALL';

    const filtered = state.archivedStudents.filter(function (s) {
      const matchSearch = !search || String(s.name).toLowerCase().indexOf(search) !== -1 || String(s.nisn).toLowerCase().indexOf(search) !== -1;
      const matchStatus = status === 'ALL' || s.status === status;
      const matchYear = year === 'ALL' || String(s.year) === String(year);
      return matchSearch && matchStatus && matchYear;
    });

    tbody.innerHTML = filtered.map(function (s, idx) {
      const badge = s.status === 'Lulus'
        ? '<span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] bg-emerald-100 text-emerald-800 border border-emerald-200">Lulus / Alumni</span>'
        : '<span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] bg-amber-100 text-amber-800 border border-amber-200">' + esc(s.status) + '</span>';
      return '<tr class="hover:bg-slate-50 transition-colors">' +
        '<td class="py-3 px-4 text-center font-bold text-slate-400">' + (idx + 1) + '</td>' +
        '<td class="py-3 px-4"><p class="font-bold text-slate-800 text-xs">' + esc(s.name) + '</p><p class="text-[11px] font-mono text-slate-400">NISN: ' + esc(s.nisn) + '</p></td>' +
        '<td class="py-3 px-4 text-center font-bold text-slate-700">Kelas ' + esc(s.lastClass) + '</td>' +
        '<td class="py-3 px-4 text-center">' + badge + '</td>' +
        '<td class="py-3 px-4 font-semibold text-slate-700"><span class="inline-flex items-center gap-1">📅 ' + esc(s.time) + '</span></td>' +
        '<td class="py-3 px-4 text-slate-600 text-xs">' + esc(s.destination) + '</td>' +
        '<td class="py-3 px-4 text-right space-x-1.5 whitespace-nowrap">' +
        '<button onclick="openEditArchivedStudentModal(' + s.id + ')" class="px-2.5 py-1 text-indigo-700 hover:bg-indigo-50 border border-indigo-200 rounded-lg font-bold text-[11px]">Edit</button>' +
        '<button onclick="openDetailArsipModal(\'siswa\', ' + s.id + ')" class="px-2.5 py-1 text-slate-700 hover:bg-slate-100 border border-slate-200 rounded-lg font-bold text-[11px]">Detail</button>' +
        '<button onclick="restoreArchivedStudent(' + s.id + ')" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg font-bold text-[11px]">Pulihkan</button>' +
        '</td></tr>';
    }).join('');
    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-400 text-xs">Belum ada berkas arsip siswa.</td></tr>';
    }
    const label = document.getElementById('arsip-siswa-count-label');
    if (label) label.innerText = 'Menampilkan ' + filtered.length + ' dari ' + state.archivedStudents.length + ' berkas arsip siswa';
  }

  async function restoreArchivedStudent(id) {
    const s = state.archivedStudents.find(function (x) { return Number(x.id) === Number(id); });
    if (!s) return;
    if (!confirm('Pulihkan ' + s.name + ' ke data siswa aktif?')) return;
    try {
      const result = await api('restore_archive_student', { id: id });
      await loadBootstrap(false);
      showToast('Siswa Dipulihkan', result.message);
    } catch (err) {
      alert('Gagal memulihkan: ' + err.message);
    }
  }

  function downloadArchivedStudentsCSV() {
    let csv = 'No,NISN,Nama Siswa,Kelas Terakhir,Status Arsip,Waktu Kejadian,Keterangan,No Telepon,Tahun\n';
    state.archivedStudents.forEach(function (s, i) {
      csv += [i + 1, csvCell(s.nisn), csvCell(s.name), csvCell(s.lastClass), csvCell(s.status), csvCell(s.time), csvCell(s.destination), csvCell(s.phone), csvCell(s.year)].join(',') + '\n';
    });
    downloadFile(csv, 'Data_Arsip_Siswa_SMPN7_Purwakarta.csv');
    showToast('Download Berhasil', 'Data arsip siswa berhasil diunduh.');
  }

  function openEditArchivedStudentModal(id) {
    const s = state.archivedStudents.find(function (x) { return Number(x.id) === Number(id); });
    if (!s) return;
    document.getElementById('edit-arsip-siswa-id').value = s.id;
    document.getElementById('edit-arsip-siswa-name').value = s.name;
    document.getElementById('edit-arsip-siswa-nisn').value = s.nisn === '-' ? '' : s.nisn;
    document.getElementById('edit-arsip-siswa-lastclass').value = s.lastClass;
    document.getElementById('edit-arsip-siswa-status').value = s.status;
    document.getElementById('edit-arsip-siswa-time').value = s.time;
    document.getElementById('edit-arsip-siswa-year').value = s.year === '-' ? '' : s.year;
    document.getElementById('edit-arsip-siswa-destination').value = s.destination;
    document.getElementById('edit-arsip-siswa-phone').value = s.phone === '-' ? '' : s.phone;
    document.getElementById('modal-edit-arsip-siswa').classList.remove('hidden');
  }

  function closeEditArchivedStudentModal() {
    document.getElementById('modal-edit-arsip-siswa').classList.add('hidden');
  }

  async function handleSaveEditArchivedStudent(event) {
    event.preventDefault();
    const payload = {
      id: document.getElementById('edit-arsip-siswa-id').value,
      name: document.getElementById('edit-arsip-siswa-name').value.trim(),
      nisn: document.getElementById('edit-arsip-siswa-nisn').value.trim(),
      last_class: document.getElementById('edit-arsip-siswa-lastclass').value.trim(),
      status: document.getElementById('edit-arsip-siswa-status').value,
      time: document.getElementById('edit-arsip-siswa-time').value.trim(),
      year: document.getElementById('edit-arsip-siswa-year').value.trim(),
      destination: document.getElementById('edit-arsip-siswa-destination').value.trim(),
      phone: document.getElementById('edit-arsip-siswa-phone').value.trim()
    };
    try {
      const result = await api('save_archive_student', payload);
      closeEditArchivedStudentModal();
      await loadBootstrap(false);
      showToast('Arsip Diperbarui', result.message);
    } catch (err) {
      alert('Gagal menyimpan arsip: ' + err.message);
    }
  }

  function openModalUploadArsipSiswa() {
    document.getElementById('modal-upload-arsip-siswa').classList.remove('hidden');
  }

  function closeModalUploadArsipSiswa() {
    document.getElementById('modal-upload-arsip-siswa').classList.add('hidden');
  }

  function downloadArsipSiswaCSVTemplate() {
    const csv = 'NISN,Nama Siswa,Kelas Terakhir,Status Arsip,Waktu Kejadian,Keterangan / Sekolah Lanjutan,No Telepon,Tahun\n0098427999,Nama Siswa Contoh,IX A,Lulus,Juni 2026,SMAN 1 Purwakarta,0812-3344-5566,2026\n';
    downloadFile(csv, 'Template_Upload_Arsip_Siswa.csv');
    showToast('Template Diunduh', 'Template arsip siswa berhasil diunduh.');
  }

  function simulateUploadArsipSiswa() {
    const input = document.getElementById('file-input-upload-arsip-siswa');
    if (input) input.click();
  }

  function parseCSV(text) {
    const rows = [];
    let row = [];
    let cell = '';
    let quoted = false;
    for (let i = 0; i < text.length; i++) {
      const ch = text[i];
      if (quoted) {
        if (ch === '"' && text[i + 1] === '"') { cell += '"'; i++; }
        else if (ch === '"') { quoted = false; }
        else { cell += ch; }
      } else if (ch === '"') {
        quoted = true;
      } else if (ch === ',') {
        row.push(cell); cell = '';
      } else if (ch === '\n' || ch === '\r') {
        if (ch === '\r' && text[i + 1] === '\n') i++;
        row.push(cell);
        if (row.some(function (c) { return c.trim() !== ''; })) rows.push(row);
        row = []; cell = '';
      } else {
        cell += ch;
      }
    }
    if (cell !== '' || row.length) {
      row.push(cell);
      if (row.some(function (c) { return c.trim() !== ''; })) rows.push(row);
    }
    return rows;
  }

  async function handleArchiveStudentFile(input) {
    const file = input.files && input.files[0];
    if (!file) return;
    try {
      const text = await file.text();
      const rows = parseCSV(text);
      if (rows.length < 2) throw new Error('Berkas CSV kosong atau hanya berisi header.');
      let inserted = 0;
      for (let i = 1; i < rows.length; i++) {
        const r = rows[i];
        if (!r[1] || !r[1].trim()) continue;
        await api('save_archive_student', {
          nisn: (r[0] || '').trim(),
          name: (r[1] || '').trim(),
          last_class: (r[2] || '').trim(),
          status: (r[3] || 'Lulus').trim(),
          time: (r[4] || '').trim(),
          destination: (r[5] || '').trim(),
          phone: (r[6] || '').trim(),
          year: (r[7] || '').trim()
        });
        inserted++;
      }
      input.value = '';
      closeModalUploadArsipSiswa();
      await loadBootstrap(false);
      showToast('Upload Berhasil', inserted + ' berkas arsip siswa ditambahkan ke database.');
    } catch (err) {
      input.value = '';
      alert('Upload gagal: ' + err.message);
    }
  }

  /* ============================================================
   * ARSIP GURU
   * ============================================================ */
  function renderArchivedTeachersTable() {
    const tbody = document.getElementById('arsip-guru-table-body');
    if (!tbody) return;
    const search = (document.getElementById('filter-arsip-guru-search')?.value || '').toLowerCase();
    const status = document.getElementById('filter-arsip-guru-status')?.value || 'ALL';
    const year = document.getElementById('filter-arsip-guru-year')?.value || 'ALL';

    const filtered = state.archivedTeachers.filter(function (g) {
      const matchSearch = !search || String(g.name).toLowerCase().indexOf(search) !== -1 || String(g.nip).toLowerCase().indexOf(search) !== -1;
      const matchStatus = status === 'ALL' || g.status === status;
      const matchYear = year === 'ALL' || String(g.year) === String(year);
      return matchSearch && matchStatus && matchYear;
    });

    tbody.innerHTML = filtered.map(function (g, idx) {
      const badge = g.status === 'Pensiun'
        ? '<span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] bg-purple-100 text-purple-800 border border-purple-200">Pensiun (Purna Tugas)</span>'
        : '<span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] bg-blue-100 text-blue-800 border border-blue-200">' + esc(g.status) + '</span>';
      return '<tr class="hover:bg-slate-50 transition-colors">' +
        '<td class="py-3 px-4 text-center font-bold text-slate-400">' + (idx + 1) + '</td>' +
        '<td class="py-3 px-4"><p class="font-bold text-slate-800 text-xs">' + esc(g.name) + '</p><p class="text-[11px] font-mono text-slate-400">NIP: ' + esc(g.nip) + '</p></td>' +
        '<td class="py-3 px-4 font-medium text-slate-700">' + esc(g.lastSubject) + '</td>' +
        '<td class="py-3 px-4 text-center">' + badge + '</td>' +
        '<td class="py-3 px-4 font-semibold text-slate-700"><span class="inline-flex items-center gap-1">📅 ' + esc(g.time) + '</span></td>' +
        '<td class="py-3 px-4 text-slate-600 text-xs">' + esc(g.sk) + '</td>' +
        '<td class="py-3 px-4 text-right space-x-1.5 whitespace-nowrap">' +
        '<button onclick="openEditArchivedTeacherModal(' + g.id + ')" class="px-2.5 py-1 text-indigo-700 hover:bg-indigo-50 border border-indigo-200 rounded-lg font-bold text-[11px]">Edit</button>' +
        '<button onclick="openDetailArsipModal(\'guru\', ' + g.id + ')" class="px-2.5 py-1 text-slate-700 hover:bg-slate-100 border border-slate-200 rounded-lg font-bold text-[11px]">Detail</button>' +
        '<button onclick="restoreArchivedTeacher(' + g.id + ')" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg font-bold text-[11px]">Pulihkan</button>' +
        '</td></tr>';
    }).join('');
    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-400 text-xs">Belum ada berkas arsip guru.</td></tr>';
    }
    const label = document.getElementById('arsip-guru-count-label');
    if (label) label.innerText = 'Menampilkan ' + filtered.length + ' dari ' + state.archivedTeachers.length + ' berkas arsip pendidik';
  }

  async function restoreArchivedTeacher(id) {
    const g = state.archivedTeachers.find(function (x) { return Number(x.id) === Number(id); });
    if (!g) return;
    if (!confirm('Pulihkan ' + g.name + ' ke data guru aktif?')) return;
    try {
      const result = await api('restore_archive_teacher', { id: id });
      await loadBootstrap(false);
      showToast('Guru Dipulihkan', result.message);
    } catch (err) {
      alert('Gagal memulihkan: ' + err.message);
    }
  }

  function downloadArchivedTeachersCSV() {
    let csv = 'No,NIP,Nama Lengkap,Mapel/Posisi Terakhir,Status Arsip,Waktu Pensiun/Keluar,No SK / Catatan,Tahun\n';
    state.archivedTeachers.forEach(function (g, i) {
      csv += [i + 1, csvCell(g.nip), csvCell(g.name), csvCell(g.lastSubject), csvCell(g.status), csvCell(g.time), csvCell(g.sk), csvCell(g.year)].join(',') + '\n';
    });
    downloadFile(csv, 'Data_Arsip_Guru_SMPN7_Purwakarta.csv');
    showToast('Download Berhasil', 'Data arsip guru berhasil diunduh.');
  }

  function openEditArchivedTeacherModal(id) {
    const g = state.archivedTeachers.find(function (x) { return Number(x.id) === Number(id); });
    if (!g) return;
    document.getElementById('edit-arsip-guru-id').value = g.id;
    document.getElementById('edit-arsip-guru-name').value = g.name;
    document.getElementById('edit-arsip-guru-nip').value = g.nip === '-' ? '' : g.nip;
    document.getElementById('edit-arsip-guru-mapel').value = g.lastSubject;
    document.getElementById('edit-arsip-guru-status').value = g.status;
    document.getElementById('edit-arsip-guru-time').value = g.time;
    document.getElementById('edit-arsip-guru-year').value = g.year === '-' ? '' : g.year;
    document.getElementById('edit-arsip-guru-sk').value = g.sk;
    document.getElementById('modal-edit-arsip-guru').classList.remove('hidden');
  }

  function closeEditArchivedTeacherModal() {
    document.getElementById('modal-edit-arsip-guru').classList.add('hidden');
  }

  async function handleSaveEditArchivedTeacher(event) {
    event.preventDefault();
    const payload = {
      id: document.getElementById('edit-arsip-guru-id').value,
      name: document.getElementById('edit-arsip-guru-name').value.trim(),
      nip: document.getElementById('edit-arsip-guru-nip').value.trim(),
      last_subject: document.getElementById('edit-arsip-guru-mapel').value.trim(),
      status: document.getElementById('edit-arsip-guru-status').value,
      time: document.getElementById('edit-arsip-guru-time').value.trim(),
      year: document.getElementById('edit-arsip-guru-year').value.trim(),
      sk: document.getElementById('edit-arsip-guru-sk').value.trim()
    };
    try {
      const result = await api('save_archive_teacher', payload);
      closeEditArchivedTeacherModal();
      await loadBootstrap(false);
      showToast('Arsip Diperbarui', result.message);
    } catch (err) {
      alert('Gagal menyimpan arsip: ' + err.message);
    }
  }

  function openModalUploadArsipGuru() {
    document.getElementById('modal-upload-arsip-guru').classList.remove('hidden');
  }

  function closeModalUploadArsipGuru() {
    document.getElementById('modal-upload-arsip-guru').classList.add('hidden');
  }

  function downloadArsipGuruCSVTemplate() {
    const csv = 'NIP,Nama Lengkap,Mapel/Posisi Terakhir,Status Arsip,Waktu Kejadian,No SK / Catatan,Tahun\n"19670101 199003 1 001","Drs. Guru Contoh, M.Pd","Bahasa Indonesia","Pensiun","Agustus 2026","SK Pensiun BKN No. 821.2/099/2026","2026"\n';
    downloadFile(csv, 'Template_Upload_Arsip_Guru.csv');
    showToast('Template Diunduh', 'Template arsip guru berhasil diunduh.');
  }

  function simulateUploadArsipGuru() {
    const input = document.getElementById('file-input-upload-arsip-guru');
    if (input) input.click();
  }

  async function handleArchiveTeacherFile(input) {
    const file = input.files && input.files[0];
    if (!file) return;
    try {
      const text = await file.text();
      const rows = parseCSV(text);
      if (rows.length < 2) throw new Error('Berkas CSV kosong atau hanya berisi header.');
      let inserted = 0;
      for (let i = 1; i < rows.length; i++) {
        const r = rows[i];
        if (!r[1] || !r[1].trim()) continue;
        await api('save_archive_teacher', {
          nip: (r[0] || '').trim(),
          name: (r[1] || '').trim(),
          last_subject: (r[2] || '').trim(),
          status: (r[3] || 'Pensiun').trim(),
          time: (r[4] || '').trim(),
          sk: (r[5] || '').trim(),
          year: (r[6] || '').trim()
        });
        inserted++;
      }
      input.value = '';
      closeModalUploadArsipGuru();
      await loadBootstrap(false);
      showToast('Upload Berhasil', inserted + ' berkas arsip guru ditambahkan ke database.');
    } catch (err) {
      input.value = '';
      alert('Upload gagal: ' + err.message);
    }
  }

  function closeDetailArsipModal() {
    document.getElementById('modal-detail-arsip').classList.add('hidden');
  }

  function openDetailArsipModal(type, id) {
    const content = document.getElementById('detail-arsip-content');
    const title = document.getElementById('detail-arsip-title');
    const typeLabel = document.getElementById('detail-arsip-type');
    const restoreBtn = document.getElementById('btn-arsip-restore');

    if (type === 'siswa') {
      const s = state.archivedStudents.find(function (x) { return Number(x.id) === Number(id); });
      if (!s) return;
      title.innerText = 'Arsip Siswa: ' + s.name;
      typeLabel.innerText = 'Buku Induk Kategori Alumni / Mutasi Keluar';
      content.innerHTML = '<div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2">' +
        '<div class="flex justify-between py-1 border-b border-slate-100"><span class="text-slate-400">NISN</span><span class="font-bold text-slate-800 font-mono">' + esc(s.nisn) + '</span></div>' +
        '<div class="flex justify-between py-1 border-b border-slate-100"><span class="text-slate-400">Kelas Terakhir</span><span class="font-bold text-slate-800">Kelas ' + esc(s.lastClass) + '</span></div>' +
        '<div class="flex justify-between py-1 border-b border-slate-100"><span class="text-slate-400">Status Diarsipkan</span><span class="font-bold text-indigo-900">' + esc(s.status) + '</span></div>' +
        '<div class="flex justify-between py-1 border-b border-slate-100"><span class="text-slate-400">Waktu Lulus/Keluar</span><span class="font-bold text-emerald-700">📅 ' + esc(s.time) + '</span></div>' +
        '<div class="py-1"><span class="text-slate-400 block mb-1">Sekolah Lanjutan / Keterangan</span><p class="font-semibold text-slate-800">' + esc(s.destination) + '</p></div></div>';
      restoreBtn.onclick = function () { closeDetailArsipModal(); restoreArchivedStudent(s.id); };
    } else {
      const g = state.archivedTeachers.find(function (x) { return Number(x.id) === Number(id); });
      if (!g) return;
      title.innerText = 'Arsip Pendidik: ' + g.name;
      typeLabel.innerText = 'Master Data Pendidik Purna Tugas / Mutasi';
      content.innerHTML = '<div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2">' +
        '<div class="flex justify-between py-1 border-b border-slate-100"><span class="text-slate-400">NIP</span><span class="font-bold text-slate-800 font-mono">' + esc(g.nip) + '</span></div>' +
        '<div class="flex justify-between py-1 border-b border-slate-100"><span class="text-slate-400">Mapel / Posisi Terakhir</span><span class="font-bold text-slate-800">' + esc(g.lastSubject) + '</span></div>' +
        '<div class="flex justify-between py-1 border-b border-slate-100"><span class="text-slate-400">Status Diarsipkan</span><span class="font-bold text-purple-900">' + esc(g.status) + '</span></div>' +
        '<div class="flex justify-between py-1 border-b border-slate-100"><span class="text-slate-400">Waktu Pensiun/Keluar</span><span class="font-bold text-purple-700">📅 ' + esc(g.time) + '</span></div>' +
        '<div class="py-1"><span class="text-slate-400 block mb-1">No. SK / Catatan Resmi</span><p class="font-semibold text-slate-800">' + esc(g.sk) + '</p></div></div>';
      restoreBtn.onclick = function () { closeDetailArsipModal(); restoreArchivedTeacher(g.id); };
    }
    document.getElementById('modal-detail-arsip').classList.remove('hidden');
  }

  /* ============================================================
   * PENGATURAN
   * ============================================================ */
  async function handleSaveSettings(event) {
    event.preventDefault();
    const t = function (id) { const el = document.getElementById(id); return el ? el.value : ''; };
    try {
      const result = await api('save_settings', {
        school_name: t('set-school-name'),
        npsn: t('set-npsn'),
        address: t('set-address'),
        headmaster: t('set-headmaster'),
        headmaster_nip: t('set-headmaster-nip'),
        tahun_ajaran: t('set-tahun-ajaran'),
        semester: t('set-semester'),
        deadline_nilai: t('set-deadline')
      });
      await loadBootstrap(false);
      showToast('Pengaturan Disimpan', result.message);
    } catch (err) {
      alert('Gagal menyimpan pengaturan: ' + err.message);
    }
  }

  /* ============================================================
   * SIDEBAR, PORTAL SWITCHER, LOGOUT, EVENT GLOBAL
   * ============================================================ */
  function openMobileSidebar() {
    const sidebar = document.getElementById('admin-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (sidebar) sidebar.classList.remove('-translate-x-full');
    if (backdrop) backdrop.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  }

  function closeMobileSidebar() {
    const sidebar = document.getElementById('admin-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (sidebar) sidebar.classList.add('-translate-x-full');
    if (backdrop) backdrop.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  }

  function toggleMobileSidebar() {
    const sidebar = document.getElementById('admin-sidebar');
    if (sidebar && sidebar.classList.contains('-translate-x-full')) openMobileSidebar();
    else closeMobileSidebar();
  }

  function togglePortalSwitcher(event) {
    if (event) event.stopPropagation();
    const menu = document.getElementById('portal-switcher-menu');
    if (menu) menu.classList.toggle('hidden');
  }

  function logoutAdmin() {
    if (!confirm('Keluar dari Panel Administrator?')) return;
    window.location.href = 'logout.php';
  }

  document.addEventListener('click', function (e) {
    const container = document.getElementById('portal-switcher-container');
    const menu = document.getElementById('portal-switcher-menu');
    if (menu && container && !container.contains(e.target)) menu.classList.add('hidden');
    if (e.target && e.target.id && e.target.id.indexOf('modal-') === 0 && !e.target.classList.contains('hidden')) {
      e.target.classList.add('hidden');
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAllModals();
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth >= 768) {
      const backdrop = document.getElementById('sidebar-backdrop');
      if (backdrop) backdrop.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');
    }
  });

  window.addEventListener('popstate', function (e) {
    const tab = (e.state && e.state.tab) || (window.location.hash || '').replace(/^#\/?/, '') || 'overview';
    switchAdminTab(tab, false);
  });

  window.addEventListener('hashchange', function () {
    const tab = (window.location.hash || '').replace(/^#\/?/, '') || 'overview';
    switchAdminTab(tab, false);
  });

  /* ============================================================
   * EXPOSE FUNGSI UNTUK onclick / onchange DI MARKUP
   * ============================================================ */
  Object.assign(window, {
    switchAdminTab: switchAdminTab,
    toggleMobileSidebar: toggleMobileSidebar,
    closeMobileSidebar: closeMobileSidebar,
    togglePortalSwitcher: togglePortalSwitcher,
    logoutAdmin: logoutAdmin,
    showToast: showToast,

    renderApprovalTable: renderApprovalTable,
    approveUser: approveUser,
    rejectUser: rejectUser,
    reloadPendingUsers: reloadPendingUsers,

    renderTeachersTable: renderTeachersTable,
    openAddTeacherModal: openAddTeacherModal,
    editTeacher: editTeacher,
    deleteTeacher: deleteTeacher,
    closeModalGuru: closeModalGuru,
    handleSaveTeacher: handleSaveTeacher,
    exportTeachersCSV: exportTeachersCSV,
    openUploadTeacherModal: openUploadTeacherModal,
    closeModalUploadGuru: closeModalUploadGuru,
    downloadTeacherCSVTemplate: downloadTeacherCSVTemplate,
    handleImportTeachers: handleImportTeachers,

    renderStudentsTable: renderStudentsTable,
    handleJenjangChange: handleJenjangChange,
    toggleSelectAllStudents: toggleSelectAllStudents,
    handleStudentRowCheck: handleStudentRowCheck,
    clearAllStudentSelection: clearAllStudentSelection,
    applyBulkClassMove: applyBulkClassMove,
    applyBulkStatusChange: applyBulkStatusChange,
    openAddStudentModal: openAddStudentModal,
    editStudent: editStudent,
    deleteStudent: deleteStudent,
    closeModalSiswa: closeModalSiswa,
    handleSaveStudent: handleSaveStudent,
    openImportModal: openImportModal,
    closeModalImport: closeModalImport,
    downloadCSVTemplate: downloadCSVTemplate,
    exportStudentsCSV: exportStudentsCSV,
    handleImportStudents: handleImportStudents,

    renderMapelTable: renderMapelTable,
    openAddMapelModal: openAddMapelModal,
    editMapel: editMapel,
    deleteMapel: deleteMapel,
    closeModalMapel: closeModalMapel,
    handleSaveMapel: handleSaveMapel,

    renderRombelCards: renderRombelCards,
    filterRombelCards: filterRombelCards,
    changeWaliKelas: changeWaliKelas,
    openAddRombelModal: openAddRombelModal,
    openDetailRombelModal: openDetailRombelModal,
    closeDetailRombelModal: closeDetailRombelModal,
    switchRombelDetailTab: switchRombelDetailTab,
    downloadRombelDetailCSV: downloadRombelDetailCSV,

    renderMonitoringGuruTable: renderMonitoringGuruTable,
    openCheckTeacherInputModal: openCheckTeacherInputModal,
    closeCheckTeacherInputModal: closeCheckTeacherInputModal,

    renderArchivedStudentsTable: renderArchivedStudentsTable,
    restoreArchivedStudent: restoreArchivedStudent,
    downloadArchivedStudentsCSV: downloadArchivedStudentsCSV,
    openEditArchivedStudentModal: openEditArchivedStudentModal,
    closeEditArchivedStudentModal: closeEditArchivedStudentModal,
    handleSaveEditArchivedStudent: handleSaveEditArchivedStudent,
    openModalUploadArsipSiswa: openModalUploadArsipSiswa,
    closeModalUploadArsipSiswa: closeModalUploadArsipSiswa,
    downloadArsipSiswaCSVTemplate: downloadArsipSiswaCSVTemplate,
    simulateUploadArsipSiswa: simulateUploadArsipSiswa,
    handleArchiveStudentFile: handleArchiveStudentFile,

    renderArchivedTeachersTable: renderArchivedTeachersTable,
    restoreArchivedTeacher: restoreArchivedTeacher,
    downloadArchivedTeachersCSV: downloadArchivedTeachersCSV,
    openEditArchivedTeacherModal: openEditArchivedTeacherModal,
    closeEditArchivedTeacherModal: closeEditArchivedTeacherModal,
    handleSaveEditArchivedTeacher: handleSaveEditArchivedTeacher,
    openModalUploadArsipGuru: openModalUploadArsipGuru,
    closeModalUploadArsipGuru: closeModalUploadArsipGuru,
    downloadArsipGuruCSVTemplate: downloadArsipGuruCSVTemplate,
    simulateUploadArsipGuru: simulateUploadArsipGuru,
    handleArchiveTeacherFile: handleArchiveTeacherFile,
    openDetailArsipModal: openDetailArsipModal,
    closeDetailArsipModal: closeDetailArsipModal,

    handleSaveSettings: handleSaveSettings
  });

  /* ============================================================
   * START
   * ============================================================ */
  async function start() {
    try {
      await loadBootstrap(false);
      initRouting();
      if (CFG.flashSuccess) showToast('Berhasil', CFG.flashSuccess);
      if (CFG.userName) {
        const nameEls = document.querySelectorAll('[data-admin-name]');
        nameEls.forEach(function (el) { el.innerText = CFG.userName; });
      }
    } catch (err) {
      showToast('Gagal Memuat Data', err.message);
      console.error(err);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
