/**
 * ==============================================================
 * PORTAL GURU — APPLICATION CONTROLLER
 * --------------------------------------------------------------
 * Semua data (penugasan, siswa, nilai, presensi, jurnal) berasal
 * dari database MySQL melalui api.php. Tidak ada array dummy.
 * ==============================================================
 */
(function () {
  'use strict';

  const CFG = window.GURU_CONFIG || {};
  const API_URL = CFG.apiUrl || 'api.php';
  const CSRF = CFG.csrf || '';

  const G = {
    teacher: {},
    assignments: [],
    allowedClasses: [],
    allowedSubjects: [],
    waliRombel: null,
    allMapel: [],
    stats: {},
    students: [],
    columns: [],
    journals: [],
    attendanceColumns: [],
    ledger: {},
    recap: null,
    modules: [],
    kkm: 75,
    currentTeacherRole: 'guru_mapel',
    selectedGradeLevel: 8,
    selectedRombelLetter: 'A',
    activeSubject: '',
    activeClass: '',
    activeWaliPovSubject: '',
    canEdit: false,
    currentTab: 'nilai',
    gradeSubTab: 'sheet'
  };

  const ROMAN = { 7: 'VII', 8: 'VIII', 9: 'IX' };
  const TAB_IDS = ['nilai', 'presensi', 'jurnal', 'materi', 'rekap', 'faq'];

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

  function initials(name) {
    return String(name || 'G').trim().split(/\s+/).slice(0, 2).map(function (p) { return p.charAt(0).toUpperCase(); }).join('');
  }

  function rombelName(level, letter) {
    return ROMAN[level] + ' ' + letter;
  }

  function classCode(rombel) {
    const parts = String(rombel || '').trim().split(/\s+/);
    if (parts.length < 2) return parts[0] || '';
    const grade = { VII: '7', VIII: '8', IX: '9' }[parts[0]] || parts[0];
    return grade + parts[1];
  }

  function fmt(value, digits) {
    if (value === '-' || value === null || value === undefined || value === '') return '-';
    const n = Number(value);
    return Number.isFinite(n) ? n.toFixed(digits === undefined ? 1 : digits) : '-';
  }

  function showToast(title, message) {
    const box = document.getElementById('toast');
    const titleEl = document.getElementById('toast-title');
    const msgEl = document.getElementById('toast-msg');
    if (!box || !titleEl || !msgEl) return;
    titleEl.innerText = title;
    msgEl.innerText = message;
    box.classList.remove('translate-y-20', 'opacity-0', 'pointer-events-none');
    window.clearTimeout(showToast._timer);
    showToast._timer = window.setTimeout(function () {
      box.classList.add('translate-y-20', 'opacity-0', 'pointer-events-none');
    }, 3600);
  }

  function downloadFile(content, fileName) {
    const blob = new Blob(['\ufeff' + content], { type: 'text/csv;charset=utf-8' });
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
      window.location.href = 'login.php?next=guru.php';
      throw new Error('Sesi berakhir.');
    }
    if (!response.ok || !json.success) {
      throw new Error(json.message || ('Permintaan gagal (HTTP ' + response.status + ').'));
    }
    return json.data || {};
  }

  /* ============================================================
   * BOOTSTRAP & LAYAR
   * ============================================================ */
  async function boot() {
    try {
      const data = await api('guru_bootstrap');
      G.teacher = data.teacher || {};
      G.assignments = data.assignments || [];
      G.allowedClasses = data.allowedClasses || [];
      G.allowedSubjects = data.allowedSubjects || [];
      G.waliRombel = data.waliRombel || null;
      G.allMapel = data.allMapel || [];
      G.stats = data.stats || {};
      G.currentTeacherRole = 'guru_mapel';

      applyTeacherIdentity();
      populateSubjectDropdown();
      setupRoleButtons();
      selectTeacherRole(G.currentTeacherRole, true);
      chooseDefaultGrade();
      renderRombelButtons();
      showScreen('screen-select-class');
    } catch (err) {
      showToast('Gagal Memuat Portal', err.message);
      console.error(err);
    }
  }

  function applyTeacherIdentity() {
    const t = G.teacher;
    const name = t.name || CFG.userName || 'Guru';
    const nipText = 'NIP ' + (t.nip || '-');
    ['step2-teacher-name', 'header-teacher-name', 'mob-drawer-teacher-name'].forEach(function (id) {
      const el = document.getElementById(id);
      if (el) el.innerText = name;
    });
    const nip = document.getElementById('header-teacher-role');
    if (nip) nip.innerText = nipText;
    const nipDrawer = document.getElementById('mob-drawer-teacher-nip');
    if (nipDrawer) nipDrawer.innerText = nipText;
    const av = document.getElementById('header-teacher-initials');
    if (av) av.innerText = initials(name);
    const avDrawer = document.getElementById('mob-drawer-teacher-initials');
    if (avDrawer) avDrawer.innerText = initials(name);
    const step = document.querySelector('#screen-select-class .bg-amber-400');
    if (step) step.innerText = initials(name);

    const statusEl = document.getElementById('step2-auto-status');
    const badgeWalas = document.getElementById('badge-walas-status');
    if (statusEl) {
      if (G.waliRombel) {
        statusEl.innerText = name + ' · ' + (G.waliRombel.name ? 'Wali Kelas ' + G.waliRombel.name : 'Wali Kelas');
        if (badgeWalas) badgeWalas.classList.remove('hidden');
      } else {
        statusEl.innerText = name + ' · Guru Pengampu Mata Pelajaran';
        if (badgeWalas) badgeWalas.classList.add('hidden');
      }
    }
  }

  function populateSubjectDropdown() {
    const select = document.getElementById('select-subject-dropdown');
    if (!select) return;
    const subjects = G.allowedSubjects.length ? G.allowedSubjects : G.assignments.map(function (a) { return a.mapel; });
    const unique = Array.from(new Set(subjects));
    if (!unique.length) {
      select.innerHTML = '<option value="">Belum ada penugasan mapel</option>';
      return;
    }
    select.innerHTML = unique.map(function (name) {
      return '<option value="' + esc(name) + '">' + esc(name) + '</option>';
    }).join('');
    select.value = unique[0];
  }

  function setupRoleButtons() {
    const btnWali = document.getElementById('btn-role-wali-kelas');
    if (!btnWali) return;
    if (!G.waliRombel) {
      btnWali.classList.add('opacity-50', 'cursor-not-allowed');
      btnWali.setAttribute('title', 'Anda tidak tercatat sebagai wali kelas.');
      const p = btnWali.querySelector('p');
      if (p) p.innerText = 'Anda tidak tercatat sebagai wali kelas pada tahun ajaran ini.';
    } else {
      btnWali.classList.remove('opacity-50', 'cursor-not-allowed');
      const p = btnWali.querySelector('p');
      if (p) p.innerText = 'Kelas binaan: ' + G.waliRombel.name + '. Pantau seluruh mata pelajaran.';
    }
  }

  function selectTeacherRole(role, silent) {
    if (role === 'wali_kelas' && !G.waliRombel) {
      if (!silent) showToast('Akses Ditolak', 'Anda tidak tercatat sebagai wali kelas.');
      role = 'guru_mapel';
    }
    G.currentTeacherRole = role;
    const btnMapel = document.getElementById('btn-role-guru-mapel');
    const btnWali = document.getElementById('btn-role-wali-kelas');
    const containerMapel = document.getElementById('container-select-mapel');

    // Dropdown mapel SELALU aktif agar guru dapat memilih mata pelajaran yang diampu
    if (containerMapel) containerMapel.classList.remove('hidden');

    if (btnMapel) btnMapel.className = role === 'guru_mapel' ? 'p-3.5 sm:p-4 rounded-2xl border-2 border-indigo-600 bg-indigo-50/70 text-left' : 'p-3.5 sm:p-4 rounded-2xl border-2 border-slate-200 text-left';
    if (btnWali) btnWali.className = role === 'wali_kelas' ? 'p-3.5 sm:p-4 rounded-2xl border-2 border-indigo-600 bg-indigo-50/70 text-left' : 'p-3.5 sm:p-4 rounded-2xl border-2 border-slate-200 text-left';

    chooseDefaultGrade();
    renderRombelButtons();
    updateClassSelectionPreview();
  }

  function chooseDefaultGrade() {
    let codes = G.waliRombel ? [G.waliRombel.code] : G.allowedClasses.slice();
    if (!codes.length) codes = ['8A'];
    const first = codes[0];
    G.selectedGradeLevel = Number(first.charAt(0)) || 8;
    G.selectedRombelLetter = first.charAt(1) || 'A';
    updateClassSelectionPreview();
  }

  function updateClassSelectionPreview() {
    const code = G.selectedGradeLevel + G.selectedRombelLetter;
    const currentClassName = rombelName(G.selectedGradeLevel, G.selectedRombelLetter);
    const isWali = G.waliRombel && (
      G.waliRombel.code === code || 
      (G.waliRombel.name && G.waliRombel.name.trim().toLowerCase() === currentClassName.trim().toLowerCase())
    );

    const containerNote = document.getElementById('container-wali-note');
    const selectSubject = document.getElementById('select-subject-dropdown');
    const subjectName = selectSubject && selectSubject.value ? selectSubject.value : (G.allowedSubjects[0] || 'Mata Pelajaran');

    if (containerNote) {
      containerNote.classList.remove('hidden');
      if (isWali) {
        containerNote.className = 'bg-amber-50 border-2 border-amber-300 rounded-2xl p-3.5 sm:p-4 text-xs text-amber-950 flex items-start gap-3 shadow-xs';
        containerNote.innerHTML = '<span class="text-xl flex-shrink-0">🎓</span>' +
          '<div>' +
            '<p class="font-bold text-xs sm:text-sm text-indigo-950">Akses Penuh Wali Kelas Terintegrasi (' + esc(currentClassName) + ')</p>' +
            '<p class="text-slate-600 text-[11px] mt-0.5 leading-relaxed">' +
              'Kelas ' + esc(currentClassName) + ' adalah <strong>kelas binaan Anda</strong>. Anda memiliki akses penuh ke Buku Induk & Ledger Rapor seluruh mata pelajaran dan rekap presensi semester.' +
            '</p>' +
          '</div>';
      } else {
        containerNote.className = 'bg-indigo-50 border-2 border-indigo-200 rounded-2xl p-3.5 sm:p-4 text-xs text-indigo-950 flex items-start gap-3 shadow-xs';
        containerNote.innerHTML = '<span class="text-xl flex-shrink-0">📘</span>' +
          '<div>' +
            '<p class="font-bold text-xs sm:text-sm text-indigo-950">Mode Guru Mata Pelajaran (' + esc(currentClassName) + ')</p>' +
            '<p class="text-slate-600 text-[11px] mt-0.5 leading-relaxed">' +
              'Anda membuka kelas ini sebagai Guru Pengampu mata pelajaran <strong>' + esc(subjectName) + '</strong>. Anda hanya dapat melihat dan menginput nilai mapel yang Anda ampu.' +
            '</p>' +
          '</div>';
      }
    }
  }

  function selectGradeLevel(level) {
    G.selectedGradeLevel = Number(level);
    const inGrade = G.allowedClasses.filter(function (c) { return Number(c.charAt(0)) === G.selectedGradeLevel; });
    if (inGrade.length && !inGrade.some(function (c) { return c.charAt(1) === G.selectedRombelLetter; })) {
      G.selectedRombelLetter = inGrade[0].charAt(1);
    }
    for (let l = 7; l <= 9; l++) {
      const btn = document.getElementById('btn-grade-' + l);
      if (!btn) continue;
      if (l === G.selectedGradeLevel) {
        btn.className = 'py-2.5 sm:py-3 px-2 sm:px-4 rounded-2xl border-2 border-indigo-600 bg-indigo-50/70 text-indigo-950 font-extrabold text-xs sm:text-sm transition-all text-center shadow-sm';
      } else {
        btn.className = 'py-2.5 sm:py-3 px-2 sm:px-4 rounded-2xl border-2 border-slate-200 text-slate-700 hover:border-indigo-400 font-extrabold text-xs sm:text-sm transition-all text-center';
      }
    }
    const labelGrade = document.getElementById('label-rombel-grade');
    if (labelGrade) labelGrade.innerText = 'Rombel Kelas ' + G.selectedGradeLevel;
    renderRombelButtons();
    updateClassSelectionPreview();
  }

  function renderRombelButtons() {
    const container = document.getElementById('rombel-selector-container');
    if (!container) return;
    const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
    const allowedSet = new Set(G.allowedClasses);
    container.innerHTML = letters.map(function (letter) {
      const code = G.selectedGradeLevel + letter;
      const allowed = allowedSet.has(code);
      const active = letter === G.selectedRombelLetter;
      let cls = 'py-2.5 rounded-xl font-bold text-xs transition-all border-2 ';
      if (!allowed) cls += 'bg-slate-100 text-slate-300 border-slate-100 cursor-not-allowed';
      else if (active) cls += 'bg-indigo-950 text-amber-300 border-indigo-950 shadow-sm';
      else cls += 'bg-white text-slate-700 border-slate-200 hover:border-indigo-400';
      return '<button type="button" ' + (allowed ? 'onclick="selectRombel(\'' + letter + '\')"' : 'disabled') +
        ' class="' + cls + '" title="' + (allowed ? 'Buka kelas ' + esc(rombelName(G.selectedGradeLevel, letter)) : 'Tidak ada penugasan di kelas ini') + '">' +
        letter + '</button>';
    }).join('');
  }

  function selectRombel(letter) {
    G.selectedRombelLetter = letter;
    renderRombelButtons();
    updateClassSelectionPreview();
  }

  function showScreen(id) {
    ['screen-login', 'screen-select-class', 'screen-dashboard'].forEach(function (screenId) {
      const el = document.getElementById(screenId);
      if (!el) return;
      if (screenId === 'screen-login') {
        el.style.display = 'none';
        return;
      }
      el.classList.toggle('hidden', screenId !== id);
    });
    if (id !== 'screen-dashboard') closeAllModals();
    window.scrollTo(0, 0);
  }

  function closeAllModals() {
    ['modal-add-column', 'modal-add-attendance-column', 'modal-jurnal'].forEach(function (id) {
      const el = document.getElementById(id);
      if (el) el.classList.add('hidden');
    });
  }

  function goBack() {
    closeAllModals();
    showScreen('screen-select-class');
  }

  function goToLogin() {
    if (confirm('Keluar dari Portal Guru?')) {
      window.location.href = window.GURU_CONFIG.logoutUrl;
    }
  }

  async function confirmWorkspaceAndProceed() {
    const code = G.selectedGradeLevel + G.selectedRombelLetter;
    if (G.allowedClasses.indexOf(code) === -1) {
      showToast('Kelas Tidak Tersedia', 'Anda tidak memiliki penugasan mengajar di kelas ' + rombelName(G.selectedGradeLevel, G.selectedRombelLetter) + '.');
      return;
    }

    G.activeClass = rombelName(G.selectedGradeLevel, G.selectedRombelLetter);
    const select = document.getElementById('select-subject-dropdown');

    // IDENTIFIKASI DINAMIS OTOMATIS:
    // HANYA menjadi wali_kelas jika kelas yang dipilih SAMA DENGAN kelas binaannya
    const isWaliDiKelasIni = G.waliRombel && (
      G.waliRombel.code === code || 
      (G.waliRombel.name && G.waliRombel.name.trim().toLowerCase() === G.activeClass.trim().toLowerCase())
    );
    G.currentTeacherRole = isWaliDiKelasIni ? 'wali_kelas' : 'guru_mapel';

    G.activeSubject = select && select.value ? select.value : '';
    if (!G.activeSubject && G.currentTeacherRole === 'wali_kelas') {
      const assignment = G.assignments.find(function (a) { return a.rombel === G.activeClass; });
      G.activeSubject = assignment ? assignment.mapel : (G.allMapel[0] ? G.allMapel[0].nama : '');
    }

    if (!G.activeSubject) {
      showToast('Mapel Belum Dipilih', 'Pilih mata pelajaran terlebih dahulu.');
      return;
    }

    G.activeWaliPovSubject = G.activeSubject;
    showScreen('screen-dashboard');
    switchTab('nilai');
    await loadWorkspace();
  }

  /* ============================================================
   * MUAT DATA KELAS
   * ============================================================ */
  async function loadWorkspace() {
    try {
      const data = await api('get_academic_data', { rombel: G.activeClass, mapel: G.activeSubject });
      G.students = data.students || [];
      G.columns = data.columns || [];
      G.journals = data.journals || [];
      G.attendanceColumns = data.attendanceColumns || [];
      G.kkm = Number(data.kkm) || 75;

      const assignment = G.assignments.find(function (a) { return a.rombel === G.activeClass && a.mapel === G.activeSubject; });
      G.canEdit = !!assignment;

      renderWorkspace();
      if (G.currentTeacherRole === 'wali_kelas' && G.waliRombel) {
        await loadWaliData();
      }
    } catch (err) {
      showToast('Gagal Memuat Kelas', err.message);
    }
  }

  function renderWorkspace() {
    const classLabel = 'Kelas ' + G.activeClass;
    const setText = function (id, value) {
      const el = document.getElementById(id);
      if (!el) return;
      if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') {
        el.value = value;      // input: isi lewat .value
      } else {
        el.innerText = value;  // elemen teks biasa
      }
    };
    setText('active-subject-text', G.activeSubject);
    setText('active-class-text', classLabel);
    setText('mob-active-subject-text', G.activeSubject);
    setText('mob-active-class-text', classLabel);
    setText('mob-drawer-class-label', classLabel);
    setText('mob-sidebar-class-name', classLabel);
    setText('sidebar-class-name', classLabel);
    setText('title-grade-workspace', G.canEdit ? 'Input Nilai Siswa' : 'Pemantauan Nilai Siswa (Read-Only)');
    setText('tag-current-view', G.activeSubject + ' - ' + classLabel);
    setText('desc-grade-workspace', G.canEdit
      ? 'Nilai tersimpan otomatis ke database saat kotak nilai diubah. KKM dapat disesuaikan sebagai acuan.'
      : 'Anda membuka lembar ini sebagai wali kelas. Nilai hanya dapat dilihat, bukan diubah.');
    setText('filter-mapel-locked', G.activeSubject);
    setText('filter-kelas-display', classLabel);
    setText('label-column-count', G.columns.length + ' Kolom Penilaian');
    setText('jurnal-tag-class', G.activeSubject + ' - ' + classLabel);
    setText('sidebar-pass-percent', G.students.length + ' Siswa Terdaftar');
    setText('attend-column-count-badge', G.attendanceColumns.length + ' Pertemuan Tercatat');
    setText('mob-sidebar-kkm-display', G.kkm);
    setText('sidebar-kkm-display', G.kkm);

    document.querySelectorAll('.label-kkm-legend').forEach(function (el) { el.innerText = G.kkm; });
    const kkmInput = document.getElementById('input-kkm-target');
    if (kkmInput) kkmInput.value = G.kkm;
    const modulMapel = document.getElementById('modul-subject-locked');
    if (modulMapel) modulMapel.value = G.activeSubject;
    const modulClass = document.getElementById('modul-class-locked');
    if (modulClass) modulClass.value = classLabel;

    const addBtn = document.getElementById('btn-add-grade-col');
    if (addBtn) addBtn.classList.toggle('hidden', !G.canEdit);
    const saveBtn = document.getElementById('btn-save-grades');
    if (saveBtn) saveBtn.classList.toggle('hidden', !G.canEdit);
    const walasBanner = document.getElementById('walas-readonly-banner');
    if (walasBanner) walasBanner.classList.toggle('hidden', G.canEdit);
    const waliBar = document.getElementById('wali-pov-control-bar');
    if (waliBar) waliBar.classList.toggle('hidden', G.currentTeacherRole !== 'wali_kelas');
    const waliTitle = document.getElementById('wali-pov-class-title');
    if (waliTitle) waliTitle.innerText = 'Pemantauan Seluruh Mapel ' + classLabel;

    const subtabSwitcher = document.getElementById('subtab-nilai-switcher');
    if (subtabSwitcher) subtabSwitcher.classList.toggle('hidden', G.currentTeacherRole !== 'wali_kelas');

    if (G.currentTeacherRole !== 'wali_kelas') {
      switchGradeSubTab('sheet');
    }

    renderGradeTable();
    renderAttendanceTable();
    renderJournalTable();
    renderModuleList();
    updateSidebarProgress();
    if (G.currentTeacherRole === 'wali_kelas') renderAllWaliViews();
  }

  function updateSidebarProgress() {
    let passed = 0;
    G.students.forEach(function (s) {
      const values = Object.keys(s.scores || {}).map(function (k) { return Number(s.scores[k]); }).filter(function (n) { return Number.isFinite(n); });
      if (values.length && (values.reduce(function (a, b) { return a + b; }, 0) / values.length) >= G.kkm) passed++;
    });
    const pct = G.students.length ? Math.round((passed / G.students.length) * 100) : 0;
    const bar = document.getElementById('sidebar-progress-bar');
    if (bar) bar.style.width = pct + '%';
    const text = document.getElementById('sidebar-pass-percent');
    if (text) text.innerText = passed + ' / ' + G.students.length + ' Siswa Tuntas (' + pct + '%)';
  }

  /* ============================================================
   * TABEL NILAI
   * ============================================================ */
  function studentAverage(student) {
    const values = Object.keys(student.scores || {}).map(function (k) { return Number(student.scores[k]); }).filter(function (n) { return Number.isFinite(n); });
    if (!values.length) return null;
    return values.reduce(function (a, b) { return a + b; }, 0) / values.length;
  }

  function renderGradeTable() {
    const head = document.getElementById('grade-table-header-row');
    const body = document.getElementById('grade-table-body');
    if (!head || !body) return;

    head.innerHTML = '<th class="py-3 px-3 w-10 text-center">No</th>' +
      '<th class="py-3 px-3 w-28">NISN</th>' +
      '<th class="py-3 px-4 min-w-[170px]">Nama Siswa</th>' +
      G.columns.map(function (c) {
        return '<th class="py-3 px-2 text-center min-w-[110px] bg-indigo-50/60">' +
          '<div class="font-bold text-indigo-950">' + esc(c.name) + '</div>' +
          '<div class="text-[9px] text-slate-500 font-medium">' + esc(c.category) + '</div>' +
          (G.canEdit ? '<button type="button" onclick="deleteGradeColumn(\'' + esc(c.name).replace(/'/g, "\\'") + '\')" class="mt-1 text-[9px] text-rose-600 hover:underline font-bold">Hapus</button>' : '') +
          '</th>';
      }).join('') +
      '<th class="py-3 px-3 text-center bg-amber-50 text-amber-950 font-black">Rata-Rata</th>';

    if (!G.students.length) {
      body.innerHTML = '<tr><td colspan="' + (G.columns.length + 4) + '" class="py-8 text-center text-slate-400 text-xs">Belum ada siswa aktif pada rombel ini. Data siswa dikelola oleh Admin.</td></tr>';
      return;
    }
    if (!G.columns.length) {
      body.innerHTML = '<tr><td colspan="4" class="py-8 text-center text-slate-400 text-xs">Belum ada kolom nilai. ' + (G.canEdit ? 'Klik "Tambah Kolom Nilai" untuk memulai.' : 'Guru pengampu belum mengisi nilai.') + '</td></tr>';
      return;
    }

    body.innerHTML = G.students.map(function (s, idx) {
      const avg = studentAverage(s);
      const avgText = avg === null ? '-' : avg.toFixed(1);
      const avgClass = avg === null ? 'text-slate-400' : (avg >= G.kkm ? 'text-indigo-950' : 'text-rose-600');
      const cells = G.columns.map(function (c) {
        const value = s.scores && s.scores[c.name] !== undefined ? s.scores[c.name] : '';
        if (!G.canEdit) {
          return '<td class="py-2.5 px-2 text-center font-semibold text-slate-700">' + (value === '' ? '-' : Number(value).toFixed(1)) + '</td>';
        }
        return '<td class="py-2.5 px-2 text-center"><input type="number" min="0" max="100" step="0.1" value="' + esc(value) + '" ' +
          'data-student="' + s.id + '" data-col="' + esc(c.name) + '" ' +
          'onchange="updateGradeCell(' + s.id + ', this.dataset.col, this.value)" ' +
          'class="grade-input w-16 text-center border border-slate-300 rounded-lg px-1.5 py-1 font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none"></td>';
      }).join('');
      return '<tr class="hover:bg-slate-50/70 transition-colors">' +
        '<td class="py-2.5 px-3 text-center font-bold text-slate-400">' + (idx + 1) + '</td>' +
        '<td class="py-2.5 px-3 font-mono text-slate-500">' + esc(s.nisn) + '</td>' +
        '<td class="py-2.5 px-4 font-bold text-slate-800">' + esc(s.name) + '</td>' +
        cells +
        '<td class="py-2.5 px-3 text-center font-black ' + avgClass + '">' + avgText + '</td>' +
        '</tr>';
    }).join('');
  }

  async function updateGradeCell(studentId, colName, value) {
    if (!G.canEdit) return;
    const student = G.students.find(function (s) { return Number(s.id) === Number(studentId); });
    const column = G.columns.find(function (c) { return c.name === colName; });
    if (!student || !column) return;

    try {
      await api('save_grade_cell', {
        rombel: G.activeClass,
        mapel: G.activeSubject,
        student_id: student.id,
        nisn: student.nisn === '-' ? '' : student.nisn,
        jenis_penilaian: colName,
        kategori: column.category || 'Formatif',
        nilai: value,
        kkm: G.kkm
      });
      student.scores[colName] = value === '' ? undefined : Number(value);
      renderGradeTable();
      updateSidebarProgress();
      showToast('Nilai Tersimpan', student.name + ' · ' + colName + ': ' + (value === '' ? 'dikosongkan' : value));
    } catch (err) {
      showToast('Gagal Menyimpan Nilai', err.message);
      renderGradeTable();
    }
  }

  function handleKKMChange(value) {
    const kkm = Math.max(0, Math.min(100, Number(value) || 0));
    G.kkm = kkm;
    document.querySelectorAll('.label-kkm-legend').forEach(function (el) { el.innerText = kkm; });
    const mob = document.getElementById('mob-sidebar-kkm-display');
    if (mob) mob.innerText = kkm;
    const side = document.getElementById('sidebar-kkm-display');
    if (side) side.innerText = kkm;
    renderGradeTable();
    updateSidebarProgress();
  }

  async function saveAllGrades() {
    if (!G.canEdit) return;
    // Nilai sudah tersimpan otomatis per sel; muat ulang untuk memastikan konsistensi database.
    await loadWorkspace();
    showToast('Tersimpan', 'Seluruh perubahan nilai sudah tersimpan di database.');
  }

  function openAddColumnModal() {
    document.getElementById('new-col-name').value = '';
    document.getElementById('new-col-date').value = new Date().toISOString().slice(0, 10);
    document.getElementById('new-col-category').value = 'Formatif';
    document.getElementById('modal-add-column').classList.remove('hidden');
  }

  function closeAddColumnModal() {
    document.getElementById('modal-add-column').classList.add('hidden');
  }

  function handleConfirmAddColumn(event) {
    event.preventDefault();
    const name = document.getElementById('new-col-name').value.trim();
    const date = document.getElementById('new-col-date').value;
    const category = document.getElementById('new-col-category').value;
    if (!name) return;
    if (G.columns.some(function (c) { return c.name.toLowerCase() === name.toLowerCase(); })) {
      showToast('Nama Duplikat', 'Kolom dengan nama tersebut sudah ada.');
      return;
    }
    G.columns.push({
      id: name,
      name: name,
      category: category,
      date: date ? new Date(date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : ''
    });
    closeAddColumnModal();
    renderGradeTable();
    showToast('Kolom Ditambahkan', 'Isi nilai siswa untuk menyimpan kolom ini ke database.');
  }

  async function deleteGradeColumn(colName) {
    if (!G.canEdit) return;
    if (!confirm('Hapus kolom nilai "' + colName + '" beserta seluruh nilainya?')) return;
    try {
      await api('delete_grade_column', { rombel: G.activeClass, mapel: G.activeSubject, jenis_penilaian: colName });
      G.columns = G.columns.filter(function (c) { return c.name !== colName; });
      G.students.forEach(function (s) { if (s.scores) delete s.scores[colName]; });
      renderGradeTable();
      updateSidebarProgress();
      showToast('Kolom Dihapus', 'Kolom nilai berhasil dihapus dari database.');
    } catch (err) {
      showToast('Gagal Menghapus', err.message);
    }
  }

  function exportGradesToCSV() {
    const lines = [['No', 'NISN', 'Nama Siswa'].concat(G.columns.map(function (c) { return c.name; })).concat(['Rata-Rata']).map(csvCell).join(',')];
    G.students.forEach(function (s, idx) {
      const row = [idx + 1, s.nisn, s.name];
      G.columns.forEach(function (c) { row.push(s.scores && s.scores[c.name] !== undefined ? s.scores[c.name] : ''); });
      const avg = studentAverage(s);
      row.push(avg === null ? '' : avg.toFixed(1));
      lines.push(row.map(csvCell).join(','));
    });
    downloadFile(lines.join('\n'), 'Nilai_' + G.activeSubject.replace(/\s+/g, '_') + '_' + G.activeClass.replace(/\s+/g, '_') + '.csv');
    showToast('Download Berhasil', 'Lembar nilai berhasil diunduh dari data database.');
  }

  /* ============================================================
   * PRESENSI
   * ============================================================ */
  function attendancePct(student) {
    const cols = G.attendanceColumns;
    if (!cols.length) return null;
    let hadir = 0;
    cols.forEach(function (c) {
      if (student.attendances && student.attendances[c.id] === 'H') hadir++;
    });
    return (hadir / cols.length) * 100;
  }

  function renderAttendanceTable() {
    const head = document.getElementById('attendance-table-header-row');
    const body = document.getElementById('attendance-table-body');
    if (!head || !body) return;

    head.innerHTML = '<th class="py-3 px-3 w-10 text-center">No</th>' +
      '<th class="py-3 px-3 w-28">NISN</th>' +
      '<th class="py-3 px-4 min-w-[160px]">Nama Siswa</th>' +
      G.attendanceColumns.map(function (c) {
        return '<th class="py-3 px-2 text-center min-w-[110px] bg-emerald-50/60">' +
          '<div class="font-bold text-emerald-950">' + esc(c.name) + '</div>' +
          '<div class="text-[9px] text-slate-500">' + esc(c.date) + '</div>' +
          (G.canEdit ? '<button type="button" onclick="removeAttendanceColumn(\'' + esc(c.id).replace(/'/g, "\\'") + '\')" class="mt-1 text-[9px] text-rose-600 hover:underline font-bold">Hapus</button>' : '') +
          '</th>';
      }).join('') +
      '<th class="py-3 px-3 text-center bg-slate-100">% Hadir</th>';

    if (!G.attendanceColumns.length) {
      body.innerHTML = '<tr><td colspan="4" class="py-8 text-center text-slate-400 text-xs">Belum ada pertemuan presensi. ' + (G.canEdit ? 'Klik "Tambah Hari Masuk / Pertemuan".' : '') + '</td></tr>';
      return;
    }
    body.innerHTML = G.students.map(function (s, idx) {
      const pct = attendancePct(s);
      return '<tr class="hover:bg-slate-50 transition-colors">' +
        '<td class="py-2.5 px-3 text-center font-bold text-slate-400">' + (idx + 1) + '</td>' +
        '<td class="py-2.5 px-3 font-mono text-slate-500">' + esc(s.nisn) + '</td>' +
        '<td class="py-2.5 px-4 font-bold text-slate-800">' + esc(s.name) + '</td>' +
        G.attendanceColumns.map(function (c) {
          const status = (s.attendances && s.attendances[c.id]) || 'H';
          const cls = { H: 'badge-h', I: 'badge-i', S: 'badge-s', A: 'badge-a' }[status] || 'badge-h';
          if (!G.canEdit) {
            return '<td class="py-2.5 px-2 text-center"><span class="inline-block w-7 py-1 rounded-lg text-[10px] font-black ' + cls + '">' + status + '</span></td>';
          }
          return '<td class="py-2.5 px-2 text-center"><button type="button" onclick="cycleAttendanceStatus(' + s.id + ', \'' + esc(c.id).replace(/'/g, "\\'") + '\')" class="inline-block w-7 py-1 rounded-lg text-[10px] font-black ' + cls + ' active:scale-95 transition-transform">' + status + '</button></td>';
        }).join('') +
        '<td class="py-2.5 px-3 text-center font-bold text-slate-700">' + (pct === null ? '-' : pct.toFixed(1) + '%') + '</td>' +
        '</tr>';
    }).join('');
  }

  async function cycleAttendanceStatus(studentId, columnId) {
    if (!G.canEdit) return;
    const student = G.students.find(function (s) { return Number(s.id) === Number(studentId); });
    const col = G.attendanceColumns.find(function (c) { return c.id === columnId; });
    if (!student || !col) return;
    const current = (student.attendances && student.attendances[columnId]) || 'H';
    const next = { H: 'I', I: 'S', S: 'A', A: 'H' }[current] || 'H';
    const parts = columnId.split('|');
    try {
      await api('save_attendance_cell', {
        rombel: G.activeClass,
        mapel: G.activeSubject,
        student_id: student.id,
        nisn: student.nisn === '-' ? '' : student.nisn,
        tanggal: parts[0],
        pertemuan: parts.slice(1).join('|') || col.name,
        status: next
      });
      if (!student.attendances) student.attendances = {};
      student.attendances[columnId] = next;
      renderAttendanceTable();
    } catch (err) {
      showToast('Gagal Menyimpan Presensi', err.message);
    }
  }

  async function markAllCurrentMeetingPresent() {
    if (!G.canEdit) return;
    if (!G.attendanceColumns.length) {
      showToast('Belum Ada Pertemuan', 'Tambahkan pertemuan presensi terlebih dahulu.');
      return;
    }
    const col = G.attendanceColumns[G.attendanceColumns.length - 1];
    const parts = col.id.split('|');
    try {
      for (const s of G.students) {
        await api('save_attendance_cell', {
          rombel: G.activeClass,
          mapel: G.activeSubject,
          student_id: s.id,
          nisn: s.nisn === '-' ? '' : s.nisn,
          tanggal: parts[0],
          pertemuan: parts.slice(1).join('|') || col.name,
          status: 'H'
        });
        if (!s.attendances) s.attendances = {};
        s.attendances[col.id] = 'H';
      }
      renderAttendanceTable();
      showToast('Presensi Diperbarui', 'Seluruh siswa ditandai hadir pada ' + col.name + '.');
    } catch (err) {
      showToast('Gagal Memperbarui', err.message);
    }
  }

  function openAddAttendanceColumnModal() {
    document.getElementById('new-att-name').value = 'Pertemuan ' + (G.attendanceColumns.length + 1);
    document.getElementById('new-att-date').value = new Date().toISOString().slice(0, 10);
    document.getElementById('new-att-default-h').checked = true;
    document.getElementById('modal-add-attendance-column').classList.remove('hidden');
  }

  function closeAddAttendanceColumnModal() {
    document.getElementById('modal-add-attendance-column').classList.add('hidden');
  }

  async function handleConfirmAddAttendanceColumn(event) {
    event.preventDefault();
    const name = document.getElementById('new-att-name').value.trim();
    const date = document.getElementById('new-att-date').value;
    if (!name || !date) return;
    try {
      await api('add_attendance_session', { rombel: G.activeClass, mapel: G.activeSubject, name: name, tanggal: date });
      closeAddAttendanceColumnModal();
      await loadWorkspace();
      switchTab('presensi');
      showToast('Pertemuan Ditambahkan', 'Presensi ' + name + ' tersimpan untuk seluruh siswa.');
    } catch (err) {
      showToast('Gagal Menambah Pertemuan', err.message);
    }
  }

  async function removeAttendanceColumn(columnId) {
    if (!G.canEdit) return;
    const col = G.attendanceColumns.find(function (c) { return c.id === columnId; });
    if (!col) return;
    if (!confirm('Hapus kolom presensi "' + col.name + '"? Seluruh status kehadiran pada pertemuan ini akan dihapus.')) return;
    const parts = columnId.split('|');
    try {
      await api('delete_attendance_session', {
        rombel: G.activeClass,
        mapel: G.activeSubject,
        tanggal: parts[0],
        pertemuan: parts.slice(1).join('|') || col.name
      });
      await loadWorkspace();
      switchTab('presensi');
      showToast('Pertemuan Dihapus', 'Kolom presensi berhasil dihapus dari database.');
    } catch (err) {
      showToast('Gagal Menghapus', err.message);
    }
  }

  async function saveAttendance() {
    await loadWorkspace();
    switchTab('presensi');
    showToast('Presensi Tersimpan', 'Seluruh perubahan presensi sudah tersimpan di database.');
  }

  function exportDailyAttendanceCSV() {
    const header = ['No', 'NISN', 'Nama Siswa'].concat(G.attendanceColumns.map(function (c) { return c.name; })).concat(['% Hadir']);
    const lines = [header.map(csvCell).join(',')];
    G.students.forEach(function (s, idx) {
      const row = [idx + 1, s.nisn, s.name];
      G.attendanceColumns.forEach(function (c) { row.push((s.attendances && s.attendances[c.id]) || 'H'); });
      const pct = attendancePct(s);
      row.push(pct === null ? '' : pct.toFixed(1));
      lines.push(row.map(csvCell).join(','));
    });
    downloadFile(lines.join('\n'), 'Presensi_' + G.activeClass.replace(/\s+/g, '_') + '.csv');
    showToast('Download Berhasil', 'Presensi berhasil diunduh dari database.');
  }

  /* ============================================================
   * JURNAL
   * ============================================================ */
  function renderJournalTable() {
    const tbody = document.getElementById('journal-table-body');
    if (!tbody) return;
    tbody.innerHTML = G.journals.map(function (j, idx) {
      const a = j.attendance || {};
      return '<tr class="hover:bg-slate-50 transition-colors">' +
        '<td class="py-3 px-3 text-center font-bold text-slate-400">' + (idx + 1) + '</td>' +
        '<td class="py-3 px-3 font-semibold text-slate-700">' + esc(j.day) + '<div class="text-[10px] text-slate-400">' + esc(j.date) + '</div></td>' +
        '<td class="py-3 px-3 text-indigo-950 font-bold">' + esc(j.meeting) + '</td>' +
        '<td class="py-3 px-4 font-bold text-slate-800">' + esc(j.topic) + '</td>' +
        '<td class="py-3 px-4 text-slate-600">' + esc(j.activities) + '</td>' +
        '<td class="py-3 px-3 text-center text-[10px] font-bold">' +
        '<span class="text-emerald-700">H:' + (a.hadir || 0) + '</span> · ' +
        '<span class="text-amber-700">I:' + (a.izin || 0) + '</span> · ' +
        '<span class="text-purple-700">S:' + (a.sakit || 0) + '</span> · ' +
        '<span class="text-rose-700">A:' + (a.alpa || 0) + '</span></td>' +
        '<td class="py-3 px-3 text-right whitespace-nowrap">' +
        (G.canEdit
          ? '<button onclick="openAddJournalModal(' + j.id + ')" class="px-2 py-1 text-indigo-600 hover:bg-indigo-50 rounded font-bold text-[11px]">Edit</button>' +
            '<button onclick="deleteJournal(' + j.id + ')" class="px-2 py-1 text-rose-600 hover:bg-rose-50 rounded font-bold text-[11px]">Hapus</button>'
          : '<span class="text-[10px] text-slate-400 font-semibold">Read-only</span>') +
        '</td></tr>';
    }).join('');
    if (!G.journals.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-400 text-xs">Belum ada jurnal mengajar untuk kelas dan mapel ini.</td></tr>';
    }
    const label = document.getElementById('journal-count-label');
    if (label) label.innerText = 'Menampilkan ' + G.journals.length + ' entri jurnal mengajar';
  }

  function openAddJournalModal(id) {
    const journal = id ? G.journals.find(function (j) { return Number(j.id) === Number(id); }) : null;
    document.getElementById('input-jurnal-id').value = journal ? journal.id : '';
    document.getElementById('modal-jurnal-title').innerText = journal ? 'Edit Jurnal Mengajar' : 'Tambah Jurnal Mengajar Harian';
    const today = new Date().toISOString().slice(0, 10);
    document.getElementById('input-jurnal-tanggal').value = journal ? journal.rawDate : today;
    document.getElementById('input-jurnal-hari').value = journal ? journal.day : ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][new Date().getDay()];
    document.getElementById('input-jurnal-pertemuan').value = journal ? journal.meeting : 'Pertemuan ' + (G.journals.length + 1);
    document.getElementById('input-jurnal-materi').value = journal ? journal.topic : '';
    document.getElementById('input-jurnal-kegiatan').value = journal ? journal.activities : '';
    document.getElementById('input-jurnal-h').value = journal ? journal.attendance.hadir : G.students.length;
    document.getElementById('input-jurnal-i').value = journal ? journal.attendance.izin : 0;
    document.getElementById('input-jurnal-s').value = journal ? journal.attendance.sakit : 0;
    document.getElementById('input-jurnal-a').value = journal ? journal.attendance.alpa : 0;
    document.getElementById('modal-jurnal').classList.remove('hidden');
  }

  function closeAddJournalModal() {
    document.getElementById('modal-jurnal').classList.add('hidden');
  }

  async function handleSaveJournal(event) {
    event.preventDefault();
    const id = document.getElementById('input-jurnal-id').value;
    const payload = {
      id: id || 0,
      rombel: G.activeClass,
      mapel: G.activeSubject,
      hari: document.getElementById('input-jurnal-hari').value,
      tanggal: document.getElementById('input-jurnal-tanggal').value,
      pertemuan: document.getElementById('input-jurnal-pertemuan').value.trim(),
      materi: document.getElementById('input-jurnal-materi').value.trim(),
      kegiatan: document.getElementById('input-jurnal-kegiatan').value.trim(),
      hadir: document.getElementById('input-jurnal-h').value || 0,
      izin: document.getElementById('input-jurnal-i').value || 0,
      sakit: document.getElementById('input-jurnal-s').value || 0,
      alpa: document.getElementById('input-jurnal-a').value || 0
    };
    if (!payload.materi || !payload.pertemuan) {
      showToast('Data Belum Lengkap', 'Pertemuan dan materi wajib diisi.');
      return;
    }
    try {
      const result = await api('save_journal', payload);
      closeAddJournalModal();
      const data = await api('get_academic_data', { rombel: G.activeClass, mapel: G.activeSubject });
      G.journals = data.journals || [];
      renderJournalTable();
      switchTab('jurnal');
      showToast('Jurnal Disimpan', result.message);
    } catch (err) {
      showToast('Gagal Menyimpan Jurnal', err.message);
    }
  }

  async function deleteJournal(id) {
    if (!confirm('Hapus entri jurnal ini?')) return;
    try {
      const result = await api('delete_journal', { id: id });
      G.journals = G.journals.filter(function (j) { return Number(j.id) !== Number(id); });
      renderJournalTable();
      showToast('Jurnal Dihapus', result.message);
    } catch (err) {
      showToast('Gagal Menghapus', err.message);
    }
  }

  function exportJournalCSV() {
    const lines = [['No', 'Hari', 'Tanggal', 'Pertemuan', 'Materi', 'Kegiatan', 'Hadir', 'Izin', 'Sakit', 'Alpa'].map(csvCell).join(',')];
    G.journals.forEach(function (j, i) {
      const a = j.attendance || {};
      lines.push([i + 1, j.day, j.date, j.meeting, j.topic, j.activities, a.hadir || 0, a.izin || 0, a.sakit || 0, a.alpa || 0].map(csvCell).join(','));
    });
    downloadFile(lines.join('\n'), 'Jurnal_' + G.activeClass.replace(/\s+/g, '_') + '.csv');
    showToast('Download Berhasil', 'Jurnal mengajar berhasil diunduh.');
  }

  /* ============================================================
   * WALI KELAS: LEDGER & REKAP
   * ============================================================ */
  async function loadWaliData() {
    await loadRecapData();
  }

  async function loadRecapData() {
    try {
      const isWali = G.currentTeacherRole === 'wali_kelas';
      if (isWali) {
        const results = await Promise.all([
          api('get_wali_ledger', { rombel: G.activeClass }),
          api('get_semester_recap', { rombel: G.activeClass, mapel: G.activeSubject })
        ]);
        G.ledger = results[0].ledger || {};
        G.recap = results[1];
        renderAllWaliViews();
      } else {
        const res = await api('get_semester_recap', { rombel: G.activeClass, mapel: G.activeSubject });
        G.ledger = {};
        G.recap = res;
        renderSemesterRecap();
      }
    } catch (err) {
      console.warn('Gagal memuat rekap:', err.message);
    }
  }

  function subjectAverageForStudent(studentId, subjectName) {
    const data = G.ledger[subjectName];
    if (!data || !data.scores) return null;
    const scores = data.scores[studentId] || data.scores[String(studentId)];
    if (!scores) return null;
    const values = Object.keys(scores).map(function (k) { return Number(scores[k]); }).filter(function (n) { return Number.isFinite(n); });
    if (!values.length) return null;
    return values.reduce(function (a, b) { return a + b; }, 0) / values.length;
  }

  function renderAllWaliViews() {
    renderWaliSubjectOptions();
    renderLedgerTable();
    renderSemesterRecap();
  }

  function renderWaliSubjectOptions() {
    const select = document.getElementById('select-wali-pov-subject');
    const selectMateri = document.getElementById('modul-pov-subject');
    if (!select && !selectMateri) return;
    const names = Object.keys(G.ledger).length ? Object.keys(G.ledger) : G.allMapel.map(function (m) { return m.nama; });
    const html = names.map(function (name) {
      const teacher = G.ledger[name] ? G.ledger[name].teacher : '';
      return '<option value="' + esc(name) + '">' + esc(name) + (teacher ? ' (' + esc(teacher) + ')' : '') + '</option>';
    }).join('');
    if (select) {
      select.innerHTML = html;
      if (names.indexOf(G.activeWaliPovSubject) !== -1) select.value = G.activeWaliPovSubject;
      else if (names.length) G.activeWaliPovSubject = names[0];
    }
    if (selectMateri) {
      selectMateri.innerHTML = html;
      if (names.indexOf(G.activeWaliPovSubject) !== -1) selectMateri.value = G.activeWaliPovSubject;
    }
  }

  function ledgerSubjects() {
    return G.allMapel.map(function (m) { return m.nama; }).filter(function (name) {
      return G.ledger[name] !== undefined;
    });
  }

  function renderLedgerTable() {
    const head = document.getElementById('ledger-table-head');
    const tbody = document.getElementById('subview-ledger-tbody');
    if (!head || !tbody) return;
    const subjects = ledgerSubjects();
    const mapelInfo = {};
    G.allMapel.forEach(function (m) { mapelInfo[m.nama] = m; });

    head.innerHTML = '<tr class="bg-slate-100 text-slate-700 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">' +
      '<th class="py-3 px-3 w-10 text-center">No</th><th class="py-3 px-3 w-28">NISN</th><th class="py-3 px-4 min-w-[150px]">Nama Siswa</th>' +
      subjects.map(function (name) {
        const kkm = mapelInfo[name] ? mapelInfo[name].kkm : G.kkm;
        const short = (mapelInfo[name] ? mapelInfo[name].kode : name).replace('MP-', '');
        return '<th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="' + esc(name) + ' (KKM ' + kkm + ')">' + esc(short) + '</th>';
      }).join('') +
      '<th class="py-3 px-3 text-center bg-amber-100 text-amber-950 font-black text-xs">Rata-Rata</th><th class="py-3 px-3 text-center">Status</th></tr>';

    if (!subjects.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-slate-400 text-xs">Belum ada nilai dari guru pengampu di kelas ini.</td></tr>';
      return;
    }

    let totalGeneral = 0;
    let totalStudents = 0;
    let passedCount = 0;
    const rows = G.students.map(function (s, idx) {
      let sum = 0;
      let count = 0;
      const cells = subjects.map(function (name) {
        const avg = subjectAverageForStudent(s.id, name);
        const kkm = mapelInfo[name] ? mapelInfo[name].kkm : G.kkm;
        if (avg === null) return '<td class="py-2.5 px-2 text-center text-slate-300">-</td>';
        sum += avg;
        count++;
        return '<td class="py-2.5 px-2 text-center font-bold ' + (avg >= kkm ? 'text-emerald-700' : 'text-rose-600') + '">' + avg.toFixed(1) + '</td>';
      }).join('');
      const overall = count ? sum / count : null;
      if (overall !== null) { totalGeneral += overall; totalStudents++; }
      const passed = overall !== null && overall >= 75;
      if (passed) passedCount++;
      return '<tr class="hover:bg-slate-50 transition-colors">' +
        '<td class="py-2.5 px-3 text-center font-bold text-slate-400">' + (idx + 1) + '</td>' +
        '<td class="py-2.5 px-3 font-mono text-slate-500">' + esc(s.nisn) + '</td>' +
        '<td class="py-2.5 px-4 font-bold text-slate-800">' + esc(s.name) + '</td>' +
        cells +
        '<td class="py-2.5 px-3 text-center font-black ' + (passed ? 'text-indigo-950' : 'text-rose-600') + '">' + (overall === null ? '-' : overall.toFixed(1)) + '</td>' +
        '<td class="py-2.5 px-3 text-center"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold ' + (passed ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-700') + '">' + (passed ? 'Tuntas' : 'Remedial') + '</span></td>' +
        '</tr>';
    }).join('');
    tbody.innerHTML = rows;

    const avg = totalStudents ? totalGeneral / totalStudents : 0;
    const avgEl = document.getElementById('metric-ledger-all-avg');
    if (avgEl) avgEl.innerText = avg.toFixed(1);
    const passEl = document.getElementById('metric-ledger-pass-count');
    if (passEl) passEl.innerText = passedCount + ' / ' + G.students.length + ' Siswa';
    const remEl = document.getElementById('metric-ledger-remedial-count');
    if (remEl) remEl.innerText = (G.students.length - passedCount) + ' Siswa';
  }

  function renderSemesterRecap() {
    const recap = G.recap;
    const attendBody = document.getElementById('attendance-semester-table-body');
    const ledgerBody = document.getElementById('ledger-table-body');
    const crosstabHeader = document.getElementById('ledger-crosstab-header-row');
    const sectionBukuInduk = document.getElementById('section-buku-induk');
    const labelMapelPresensi = document.getElementById('label-rekap-presensi-mapel');

    if (!recap || !ledgerBody) return;
    const students = recap.students || [];
    const isWali = !!recap.isWali;
    const subjects = recap.subjects || [];
    const mapelAktif = recap.mapel || G.activeSubject || '—';

    // === CONDITIONAL RENDERING: Sembunyikan Buku Induk untuk Guru Mapel biasa ===
    if (sectionBukuInduk) {
      sectionBukuInduk.classList.toggle('hidden', !isWali);
    }

    // === Tampilkan Nama Mapel Aktif pada Header Tabel 1 (Presensi) ===
    if (labelMapelPresensi) {
      labelMapelPresensi.textContent = mapelAktif;
    }

    // === RENDER TABLE HEADER (HANYA UNTUK TABEL 2 BUKU INDUK CROSSTAB) ===
    // Tabel 1 (Presensi) header tetap statis dan murni presensi mapel aktif
    if (crosstabHeader && isWali && subjects.length > 0) {
      let th = '<th class="py-3 px-3 w-10 text-center sticky left-0 bg-slate-100 z-10">No</th>' +
        '<th class="py-3 px-3 w-28">NISN</th>' +
        '<th class="py-3 px-4 min-w-[170px] sticky left-10 bg-slate-100 z-10">Nama Siswa</th>';
      subjects.forEach(function (m) {
        var shortName = m.nama.length > 10 ? m.nama.substring(0, 9) + '…' : m.nama;
        th += '<th class="py-2.5 px-2 text-center border-l border-slate-200 min-w-[70px] whitespace-nowrap" title="' + esc(m.nama) + ' (KKM: ' + (m.kkm || 75) + ')">' +
          esc(shortName) + '<br><span class="text-[9px] font-normal text-slate-400">KKM ' + (m.kkm || 75) + '</span></th>';
      });
      th += '<th class="py-3 px-3 text-center font-extrabold bg-amber-50 text-amber-950 border-l-2 border-amber-200 whitespace-nowrap">Rata-Rata<br><span class="font-normal text-[9px]">Rapor</span></th>' +
        '<th class="py-3 px-2 text-center text-emerald-700 bg-emerald-50 border-l border-slate-200 w-10">H</th>' +
        '<th class="py-3 px-2 text-center text-amber-700 bg-amber-50 w-10">I</th>' +
        '<th class="py-3 px-2 text-center text-purple-700 bg-purple-50 w-10">S</th>' +
        '<th class="py-3 px-2 text-center text-rose-700 bg-rose-50 w-10">A</th>' +
        '<th class="py-3 px-3 text-center whitespace-nowrap">% Hadir</th>' +
        '<th class="py-3 px-3 min-w-[240px]">Catatan Evaluasi Pembelajaran <span class="text-[9px] font-normal">(Dapat Diedit)</span></th>';
      crosstabHeader.innerHTML = th;
    }

    if (!students.length) {
      ledgerBody.innerHTML = '<tr><td colspan="' + (isWali ? (subjects.length + 9) : 7) + '" class="py-8 text-center text-slate-400 text-xs">Belum ada data siswa di kelas ini.</td></tr>';
      if (attendBody) attendBody.innerHTML = '<tr><td colspan="10" class="py-8 text-center text-slate-400 text-xs">Belum ada siswa aktif.</td></tr>';
      return;
    }

    // === TABEL 1: RENDER REKAP PRESENSI (HANYA MAPEL AKTIF YANG DIAMPU) ===
    if (attendBody) {
      attendBody.innerHTML = students.map(function (s, idx) {
        const a = s.attendance || {};
        const pct = a.pct;
        return '<tr class="hover:bg-slate-50 transition-colors">' +
          '<td class="py-2.5 px-3 text-center font-bold text-slate-400">' + (idx + 1) + '</td>' +
          '<td class="py-2.5 px-3 font-mono text-slate-500">' + esc(s.nisn) + '</td>' +
          '<td class="py-2.5 px-4 font-bold text-slate-800">' + esc(s.name) + '</td>' +
          '<td class="py-2.5 px-2 text-center font-bold text-emerald-700">' + (a.hadir || 0) + '</td>' +
          '<td class="py-2.5 px-2 text-center font-bold text-amber-700">' + (a.izin || 0) + '</td>' +
          '<td class="py-2.5 px-2 text-center font-bold text-purple-700">' + (a.sakit || 0) + '</td>' +
          '<td class="py-2.5 px-2 text-center font-bold text-rose-700">' + (a.alpa || 0) + '</td>' +
          '<td class="py-2.5 px-3 text-center font-semibold">' + (a.total || 0) + '</td>' +
          '<td class="py-2.5 px-3 text-center font-black ' + (pct === null || pct === undefined || pct >= 75 ? 'text-emerald-700' : 'text-rose-600') + '">' +
          (pct === null || pct === undefined ? '-' : pct.toFixed(1) + '%') + '</td>' +
          '<td class="py-2.5 px-3"><input type="text" value="' + esc(s.catatan || '') + '" data-student-note="' + s.id + '" class="w-full min-w-[240px] border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="Tulis catatan evaluasi..."></td>' +
          '</tr>';
      }).join('');
    }

    // === TABEL 2: RENDER BUKU INDUK LEDGER CROSSTAB (HANYA MUNCUL DI WALI KELAS) ===
    if (isWali && subjects.length > 0) {
      ledgerBody.innerHTML = students.map(function (s, idx) {
        const a = s.ledgerAttendance || s.attendance || {};
        const avgNum = s.avg !== '-' ? Number(s.avg) : null;
        const passed = avgNum !== null && avgNum >= 75;
        let trHtml = '<tr class="hover:bg-slate-50 transition-colors">' +
          '<td class="py-2.5 px-3 text-center font-bold text-slate-400 sticky left-0 bg-white">' + (idx + 1) + '</td>' +
          '<td class="py-2.5 px-3 font-mono text-slate-500 whitespace-nowrap">' + esc(s.nisn) + '</td>' +
          '<td class="py-2.5 px-4 font-bold text-slate-800 sticky left-10 bg-white whitespace-nowrap">' + esc(s.name) + '</td>';

        // Kolom nilai per mapel (crosstab)
        subjects.forEach(function (m) {
          const score = (s.scores && s.scores[m.id] !== undefined) ? s.scores[m.id] : null;
          const kkm = Number(m.kkm) || 75;
          const underKkm = score !== null && Number(score) < kkm;
          trHtml += '<td class="py-2 px-2 text-center border-l border-slate-100 ' +
            (underKkm ? 'bg-rose-50 text-rose-700 font-bold' : 'text-slate-700') + '">' +
            (score !== null ? Number(score).toFixed(1) : '<span class="text-slate-300">-</span>') + '</td>';
        });

        // Rata-Rata Rapor
        trHtml += '<td class="py-2.5 px-3 text-center font-black border-l-2 border-amber-200 bg-amber-50/30 ' +
          (passed ? 'text-indigo-950' : (avgNum !== null ? 'text-rose-600' : 'text-slate-400')) + '">' +
          (avgNum !== null ? avgNum.toFixed(1) : '-') + '</td>';

        // Presensi Akumulasi Semua Mapel: H, I, S, A, %
        const pct = a.pct;
        trHtml +=
          '<td class="py-2.5 px-2 text-center font-bold text-emerald-700 bg-emerald-50/20 border-l border-slate-100">' + (a.hadir || 0) + '</td>' +
          '<td class="py-2.5 px-2 text-center font-bold text-amber-700 bg-amber-50/20">' + (a.izin || 0) + '</td>' +
          '<td class="py-2.5 px-2 text-center font-bold text-purple-700 bg-purple-50/20">' + (a.sakit || 0) + '</td>' +
          '<td class="py-2.5 px-2 text-center font-bold text-rose-700 bg-rose-50/20">' + (a.alpa || 0) + '</td>' +
          '<td class="py-2.5 px-3 text-center font-bold ' + (pct === null || pct === undefined || pct >= 75 ? 'text-emerald-700' : 'text-rose-600') + '">' +
          (pct === null || pct === undefined ? '-' : pct.toFixed(1) + '%') + '</td>';

        // Catatan Evaluasi (editable)
        trHtml += '<td class="py-2.5 px-3"><input type="text" value="' + esc(s.catatan || '') +
          '" data-student-note="' + s.id +
          '" class="w-full min-w-[240px] border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="Tulis catatan evaluasi..."></td>' +
          '</tr>';
        return trHtml;
      }).join('');
    }

    // Update metric cards
    const classAvg = document.getElementById('metric-class-avg');
    if (classAvg) classAvg.innerText = fmt(recap.classAvg);
    const kkmCmp = document.getElementById('metric-kkm-comparison');
    if (kkmCmp) kkmCmp.innerText = 'Target KKM: ' + G.kkm;
    const attendAvg = document.getElementById('metric-attend-avg');
    if (attendAvg) attendAvg.innerText = fmt(recap.attendanceAvg) + '%';
    const notesCount = document.getElementById('metric-notes-count');
    if (notesCount) notesCount.innerText = students.filter(function (s) { return (s.catatan || '').trim() !== ''; }).length + ' Siswa';
  }

  async function saveAllEvaluations() {
    if (G.currentTeacherRole !== 'wali_kelas') {
      showToast('Akses Ditolak', 'Hanya wali kelas yang dapat menyimpan catatan evaluasi.');
      return;
    }
    const inputs = Array.from(document.querySelectorAll('[data-student-note]'));
    try {
      for (const input of inputs) {
        await api('save_student_note', {
          rombel: G.activeClass,
          student_id: input.dataset.studentNote,
          note: input.value
        });
      }
      await loadWaliData();
      showToast('Catatan Tersimpan', inputs.length + ' catatan evaluasi disimpan ke database.');
    } catch (err) {
      showToast('Gagal Menyimpan Catatan', err.message);
    }
  }

  function exportAttendanceSemesterCSV() {
    if (!G.recap) return;
    const lines = [['No', 'NISN', 'Nama Siswa', 'Hadir', 'Izin', 'Sakit', 'Alpa', 'Total Pertemuan', '% Kehadiran', 'Catatan'].map(csvCell).join(',')];
    (G.recap.students || []).forEach(function (s, i) {
      const a = s.attendance || {};
      lines.push([i + 1, s.nisn, s.name, a.hadir || 0, a.izin || 0, a.sakit || 0, a.alpa || 0, a.total || 0, a.pct === null ? '' : a.pct, s.catatan || ''].map(csvCell).join(','));
    });
    downloadFile(lines.join('\n'), 'Rekap_Presensi_Semester_' + G.activeClass.replace(/\s+/g, '_') + '.csv');
    showToast('Download Berhasil', 'Rekap presensi semester berhasil diunduh.');
  }

  async function handleWaliPovSubjectChange(subject) {
    if (!subject) return;
    G.activeWaliPovSubject = subject;
    G.activeSubject = subject;
    await loadWorkspace();
    switchGradeSubTab('sheet');
  }

  function switchGradeSubTab(tab) {
    G.gradeSubTab = tab;
    const isSheet = tab === 'sheet';
    document.getElementById('subview-grade-sheet').classList.toggle('hidden', !isSheet);
    document.getElementById('subview-grade-ledger').classList.toggle('hidden', isSheet);
    const btnSheet = document.getElementById('subtab-btn-sheet');
    const btnLedger = document.getElementById('subtab-btn-ledger');
    if (btnSheet) btnSheet.className = 'w-full sm:w-auto px-3.5 sm:px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2 ' + (isSheet ? 'bg-indigo-950 text-white shadow-xs' : 'text-slate-600 hover:text-indigo-950');
    if (btnLedger) btnLedger.className = 'w-full sm:w-auto px-3.5 sm:px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2 ' + (!isSheet ? 'bg-indigo-950 text-white shadow-xs' : 'text-slate-600 hover:text-indigo-950');
    const hint = document.getElementById('subtab-context-hint');
    if (hint) hint.innerText = 'Mode aktif: ' + (isSheet ? 'Lembar Penilaian Mapel' : 'Rekap Seluruh Mapel');
    if (!isSheet && G.currentTeacherRole === 'wali_kelas') loadWaliData();
  }

  function downloadLedgerCSV() {
    // Hanya wali kelas yang boleh mengunduh Buku Induk
    if (G.currentTeacherRole !== 'wali_kelas') {
      showToast('Akses Ditolak', 'Unduhan Buku Induk hanya tersedia untuk Wali Kelas.');
      return;
    }
    if (!G.activeClass) {
      showToast('Peringatan', 'Pilih kelas terlebih dahulu.');
      return;
    }
    showToast('Memproses...', 'Menyiapkan file CSV Buku Induk kelas ' + G.activeClass + '...');
    // Arahkan ke file export mandiri (export_ledger_csv.php)
    window.location.href = 'export_ledger_csv.php?rombel=' + encodeURIComponent(G.activeClass);
  }
  window.downloadLedgerCSV = downloadLedgerCSV;

  function exportSemesterRecapCSV() {
    if (!G.recap) {
      showToast('Peringatan', 'Data rekap belum dimuat.');
      return;
    }
    if (G.recap.isWali) {
      window.location.href = 'guru.php?action=export_ledger_csv&rombel=' + encodeURIComponent(G.activeClass);
      showToast('Download Berhasil', 'Mengunduh Buku Induk & Ledger Rapor seluruh mata pelajaran.');
      return;
    }
    const students = G.recap.students || [];
    const mapelName = G.recap.mapel || G.activeSubject || 'Mapel';
    const lines = [['No', 'NISN', 'Nama Siswa', 'Mata Pelajaran', 'Total Tugas Dinilai', 'Rata-Rata Akhir', 'Hadir (H)', 'Izin (I)', 'Sakit (S)', 'Alpa (A)', '% Kehadiran', 'Catatan Evaluasi'].map(csvCell).join(',')];
    students.forEach(function (s, idx) {
      const a = s.attendance || {};
      const row = [
        idx + 1,
        s.nisn,
        s.name,
        mapelName,
        s.total_nilai,
        s.avg,
        a.hadir || 0,
        a.izin || 0,
        a.sakit || 0,
        a.alpa || 0,
        (a.pct !== null ? a.pct + '%' : '-'),
        s.catatan || ''
      ];
      lines.push(row.map(csvCell).join(','));
    });
    downloadFile(lines.join('\n'), 'Rekap_' + mapelName.replace(/\s+/g, '_') + '_' + G.activeClass.replace(/\s+/g, '_') + '.csv');
    showToast('Download Berhasil', 'Rekap nilai mapel berhasil diunduh.');
  }
  window.exportSemesterRecapCSV = exportSemesterRecapCSV;

  function downloadSubviewLedgerCSV() {
    const subjects = ledgerSubjects();
    const head = ['No', 'NISN', 'Nama Siswa'].concat(subjects).concat(['Rata-Rata', 'Status']);
    const lines = [head.map(csvCell).join(',')];
    G.students.forEach(function (s, i) {
      let sum = 0;
      let count = 0;
      const row = [i + 1, s.nisn, s.name];
      subjects.forEach(function (name) {
        const avg = subjectAverageForStudent(s.id, name);
        row.push(avg === null ? '' : avg.toFixed(1));
        if (avg !== null) { sum += avg; count++; }
      });
      const overall = count ? sum / count : null;
      row.push(overall === null ? '' : overall.toFixed(1));
      row.push(overall === null ? '' : (overall >= 75 ? 'Tuntas' : 'Remedial'));
      lines.push(row.map(csvCell).join(','));
    });
    downloadFile(lines.join('\n'), 'Ledger_' + G.activeClass.replace(/\s+/g, '_') + '.csv');
    showToast('Download Berhasil', 'Ledger nilai berhasil diunduh.');
  }

  /* ============================================================
   * MODUL / MATERI PEMBELAJARAN
   * ============================================================ */
  let selectedModulFile = null;

  function onModulFileChosen(input) {
    if (input.files && input.files[0]) {
      const file = input.files[0];
      if (file.size > 15 * 1024 * 1024) {
        showToast('File Terlalu Besar', 'Ukuran maksimal file modul adalah 15 MB.');
        input.value = '';
        selectedModulFile = null;
        const label = document.getElementById('modul-file-label');
        if (label) label.innerText = 'Belum ada file dipilih';
        return;
      }
      selectedModulFile = file;
      const label = document.getElementById('modul-file-label');
      if (label) label.innerText = file.name + ' (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)';
    }
  }

  function onModulFileDrop(event) {
    event.preventDefault();
    if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0]) {
      const file = event.dataTransfer.files[0];
      const input = document.getElementById('input-modul-file');
      if (file.size > 15 * 1024 * 1024) {
        showToast('File Terlalu Besar', 'Ukuran maksimal file modul adalah 15 MB.');
        return;
      }
      selectedModulFile = file;
      if (input) input.files = event.dataTransfer.files;
      const label = document.getElementById('modul-file-label');
      if (label) label.innerText = file.name + ' (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)';
    }
  }

  async function submitModule(status) {
    const titleInput = document.getElementById('input-modul-title');
    const descInput = document.getElementById('input-modul-desc');
    const title = titleInput ? titleInput.value.trim() : '';
    const desc = descInput ? descInput.value.trim() : '';
    const file = selectedModulFile || (document.getElementById('input-modul-file') ? document.getElementById('input-modul-file').files[0] : null);

    if (!title) {
      showToast('Validasi', 'Judul materi / modul wajib diisi.');
      if (titleInput) titleInput.focus();
      return;
    }
    if (!file) {
      showToast('Validasi', 'Pilih file modul terlebih dahulu (PDF, DOCX, dll).');
      return;
    }
    if (file.size > 15 * 1024 * 1024) {
      showToast('Validasi', 'Ukuran file modul maksimal 15 MB.');
      return;
    }

    const allowedExts = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
    const ext = file.name.split('.').pop().toLowerCase();
    if (allowedExts.indexOf(ext) === -1) {
      showToast('Format Tidak Didukung', 'Format file tidak didukung. Gunakan: ' + allowedExts.join(', '));
      return;
    }

    const btnTerbit = document.getElementById('btn-modul-terbit');
    const btnDraf = document.getElementById('btn-modul-draf');
    if (btnTerbit) btnTerbit.disabled = true;
    if (btnDraf) btnDraf.disabled = true;

    try {
      showToast('Mengunggah...', 'File sedang dikirim ke server...');
      const fd = new FormData();
      fd.append('rombel', G.activeClass);
      fd.append('mapel', G.activeSubject);
      fd.append('judul', title);
      fd.append('deskripsi', desc);
      fd.append('status', status || 'Terbit');
      fd.append('file', file);

      await api('upload_materi', {}, fd);
      showToast('Berhasil', 'Modul pembelajaran berhasil ' + (status === 'Draf' ? 'disimpan sebagai draf' : 'diterbitkan') + '.');

      // Reset form
      if (titleInput) titleInput.value = '';
      if (descInput) descInput.value = '';
      const fileInput = document.getElementById('input-modul-file');
      if (fileInput) fileInput.value = '';
      selectedModulFile = null;
      const label = document.getElementById('modul-file-label');
      if (label) label.innerText = 'Belum ada file dipilih';

      await renderModuleList();
    } catch (err) {
      showToast('Gagal Unggah', err.message);
    } finally {
      if (btnTerbit) btnTerbit.disabled = false;
      if (btnDraf) btnDraf.disabled = false;
    }
  }

  async function renderModuleList() {
    const container = document.getElementById('module-list-container');
    if (!container) return;

    // Kunci nilai mapel dan kelas pada form
    const subjectLocked = document.getElementById('modul-subject-locked');
    const classLocked = document.getElementById('modul-class-locked');
    if (subjectLocked) subjectLocked.value = G.activeSubject || '-';
    if (classLocked) classLocked.value = G.activeClass ? ('Kelas ' + G.activeClass) : '-';

    const title = document.getElementById('module-header-title');
    if (title) title.innerText = 'Modul Terbit — ' + (G.activeClass || '');

    container.innerHTML = '<div class="p-6 text-center text-slate-400 text-[11px]">Memuat modul pembelajaran...</div>';

    try {
      const data = await api('get_materi', { rombel: G.activeClass, mapel: G.activeSubject });
      G.modules = data.modules || [];

      // Sembunyikan form upload jika bukan pengampu dan bukan kepsek
      const uploadForm = document.getElementById('form-upload-modul') || document.getElementById('modul-upload-form');
      const readonlyBanner = document.getElementById('modul-readonly-banner');
      if (data.canUpload === false && G.currentTeacherRole === 'wali_kelas') {
        if (uploadForm) uploadForm.classList.add('hidden');
        if (readonlyBanner) readonlyBanner.classList.remove('hidden');
      } else {
        if (uploadForm) uploadForm.classList.remove('hidden');
        if (readonlyBanner) readonlyBanner.classList.add('hidden');
      }

      if (!G.modules.length) {
        container.innerHTML = '<div class="p-6 text-center text-slate-400 text-[11px]">Belum ada modul yang diunggah untuk kelas dan mata pelajaran ini.</div>';
        return;
      }

      container.innerHTML = G.modules.map(function (m) {
        const isPdf = m.ext === 'PDF';
        const iconBg = isPdf ? 'bg-rose-100 text-rose-700' : 'bg-indigo-100 text-indigo-700';
        const iconText = isPdf ? '📕' : '📄';
        return '<div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50 transition-colors">' +
          '<div class="flex items-start gap-3 min-w-0">' +
            '<div class="w-10 h-10 rounded-xl ' + iconBg + ' flex items-center justify-center shrink-0 text-base font-bold">' + iconText + '</div>' +
            '<div class="min-w-0">' +
              '<div class="flex items-center gap-2 flex-wrap">' +
                '<p class="font-bold text-slate-800 text-xs sm:text-sm truncate">' + esc(m.title) + '</p>' +
                (m.status === 'Draf' ? '<span class="text-[9px] bg-slate-200 text-slate-700 font-bold px-2 py-0.5 rounded">DRAF</span>' : '<span class="text-[9px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded">TERBIT</span>') +
              '</div>' +
              (m.desc ? '<p class="text-[11px] text-slate-500 mt-0.5">' + esc(m.desc) + '</p>' : '') +
              '<p class="text-[10px] text-slate-400 mt-1">' + esc(m.subject) + ' · ' + esc(m.size) + ' · Pengunggah: ' + esc(m.teacher) + ' · ' + esc(m.date) + '</p>' +
            '</div>' +
          '</div>' +
          '<div class="flex items-center gap-2 self-end sm:self-center shrink-0">' +
            '<a href="' + esc(m.url) + '" target="_blank" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-900 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5">' +
              '<span>Unduh</span>' +
              '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>' +
            '</a>' +
            (m.mine ? '<button type="button" onclick="deleteModule(' + m.id + ')" class="px-2.5 py-1.5 text-rose-600 hover:bg-rose-50 rounded-lg text-xs font-semibold cursor-pointer" title="Hapus Modul">Hapus</button>' : '') +
          '</div>' +
        '</div>';
      }).join('');
    } catch (err) {
      container.innerHTML = '<div class="p-6 text-center text-rose-500 text-xs">Gagal memuat modul: ' + esc(err.message) + '</div>';
    }
  }

  async function deleteModule(id) {
    if (!confirm('Yakin ingin menghapus modul pembelajaran ini?')) return;
    try {
      await api('delete_materi', { id: id });
      showToast('Modul Dihapus', 'File modul berhasil dihapus.');
      await renderModuleList();
    } catch (err) {
      showToast('Gagal Menghapus', err.message);
    }
  }

  /* ============================================================
   * TAB & DRAWER
   * ============================================================ */
  function switchTab(tab) {
    if (TAB_IDS.indexOf(tab) === -1) tab = 'nilai';
    G.currentTab = tab;
    TAB_IDS.forEach(function (t) {
      const section = document.getElementById('tab-' + t);
      if (section) section.classList.toggle('hidden', t !== tab);
    });

    ['btn-tab-', 'btn-mob-tab-', 'btn-drawer-tab-'].forEach(function (prefix) {
      TAB_IDS.forEach(function (t) {
        const btn = document.getElementById(prefix + t);
        if (!btn) return;
        const active = t === tab;
        if (prefix === 'btn-tab-') {
          btn.className = 'w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all ' + (active ? 'bg-indigo-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100');
        } else {
          btn.className = (prefix === 'btn-mob-tab-'
            ? 'flex-shrink-0 px-3 py-1.5 rounded-xl text-xs transition-all flex items-center gap-1.5 '
            : 'w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs transition-all ') +
            (active ? 'bg-indigo-900 text-white font-bold shadow-xs' : 'text-slate-600 font-medium hover:bg-slate-100');
        }
      });
    });

    if (tab === 'jurnal') renderJournalTable();
    if (tab === 'presensi') renderAttendanceTable();
    if (tab === 'nilai') renderGradeTable();
    if (tab === 'materi') renderModuleList();
    if (tab === 'rekap') loadRecapData();
    toggleMobileDrawer(false);
    window.scrollTo(0, 0);
  }

  function toggleMobileDrawer(open) {
    const overlay = document.getElementById('mobile-drawer-overlay');
    if (!overlay) return;
    overlay.classList.toggle('hidden', !open);
  }

  function handleLoginSubmit(event) {
    event.preventDefault();
    window.location.href = 'login.php?next=guru.php';
  }

  /* ============================================================
   * EXPOSE
   * ============================================================ */
  Object.assign(window, {
    selectTeacherRole: selectTeacherRole,
    selectGradeLevel: selectGradeLevel,
    selectRombel: selectRombel,
    confirmWorkspaceAndProceed: confirmWorkspaceAndProceed,
    goBack: goBack,
    goToLogin: goToLogin,
    handleLoginSubmit: handleLoginSubmit,
    switchTab: switchTab,
    toggleMobileDrawer: toggleMobileDrawer,
    switchGradeSubTab: switchGradeSubTab,
    handleWaliPovSubjectChange: handleWaliPovSubjectChange,
    handleKKMChange: handleKKMChange,
    openAddColumnModal: openAddColumnModal,
    closeAddColumnModal: closeAddColumnModal,
    handleConfirmAddColumn: handleConfirmAddColumn,
    updateGradeCell: updateGradeCell,
    deleteGradeColumn: deleteGradeColumn,
    saveAllGrades: saveAllGrades,
    exportGradesToCSV: exportGradesToCSV,
    openAddAttendanceColumnModal: openAddAttendanceColumnModal,
    closeAddAttendanceColumnModal: closeAddAttendanceColumnModal,
    handleConfirmAddAttendanceColumn: handleConfirmAddAttendanceColumn,
    removeAttendanceColumn: removeAttendanceColumn,
    cycleAttendanceStatus: cycleAttendanceStatus,
    markAllCurrentMeetingPresent: markAllCurrentMeetingPresent,
    saveAttendance: saveAttendance,
    exportDailyAttendanceCSV: exportDailyAttendanceCSV,
    openAddJournalModal: openAddJournalModal,
    closeAddJournalModal: closeAddJournalModal,
    handleSaveJournal: handleSaveJournal,
    deleteJournal: deleteJournal,
    exportJournalCSV: exportJournalCSV,
    exportAttendanceSemesterCSV: exportAttendanceSemesterCSV,
    exportSemesterRecapCSV: exportSemesterRecapCSV,
    saveAllEvaluations: saveAllEvaluations,
    downloadSubviewLedgerCSV: downloadSubviewLedgerCSV,
    downloadLedgerCSV: downloadLedgerCSV,
    onModulFileChosen: onModulFileChosen,
    onModulFileDrop: onModulFileDrop,
    submitModule: submitModule,
    deleteModule: deleteModule,
    renderModuleList: renderModuleList,
    showToast: showToast
  });

  /* ============================================================
   * START
   * ============================================================ */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
