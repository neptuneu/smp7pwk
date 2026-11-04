/**
 * ==============================================================
 * PORTAL SISWA — CONTROLLER
 * Data 100% dari database melalui api.php (student_bootstrap).
 * ==============================================================
 */
(function () {
  'use strict';

  const CFG = window.STUDENT_CONFIG || {};
  const API_URL = CFG.apiUrl || 'api.php';
  const CSRF = CFG.csrf || '';

  function esc(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function initials(name) {
    return String(name || 'S').trim().split(/\s+/).slice(0, 2).map(function (p) { return p.charAt(0).toUpperCase(); }).join('');
  }

  async function api(action, data) {
    const params = new URLSearchParams();
    params.append('action', action);
    Object.keys(data || {}).forEach(function (key) { params.append(key, data[key]); });

    const response = await fetch(API_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        'X-CSRF-Token': CSRF,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: params.toString(),
      credentials: 'same-origin'
    });
    const json = await response.json().catch(function () {
      throw new Error('Respons server tidak valid (HTTP ' + response.status + ').');
    });
    if (response.status === 401) {
      window.location.href = 'login.php?next=murid.php';
      throw new Error('Sesi berakhir.');
    }
    if (!response.ok || !json.success) throw new Error(json.message || 'Gagal memuat data.');
    return json.data || {};
  }

  function renderHeader(student) {
    const setText = function (id, value) {
      const el = document.getElementById(id);
      if (el) el.innerText = value;
    };
    setText('header-student-name', student.name);
    setText('header-student-class', (student.class !== '-' ? 'Kelas ' + student.class : 'Kelas -') + ' · NISN ' + student.nisn);
    setText('header-student-initials', initials(student.name));
    setText('welcome-name', 'Halo, ' + student.name + ' 👋');
    setText('profile-nisn', student.nisn);
    setText('profile-class', student.class);
    setText('profile-parent', student.parent);
    setText('student-note', (student.catatan || '').trim() || 'Belum ada catatan dari wali kelas.');
  }

  function renderStats(student, subjects, attendance) {
    const setText = function (id, value) {
      const el = document.getElementById(id);
      if (el) el.innerText = value;
    };
    const subjectNames = Object.keys(subjects);
    setText('stat-average', student.overallAverage || '-');
    setText('stat-subjects', subjectNames.length);

    const total = Number(attendance.total || 0);
    const hadir = Number(attendance.hadir || 0);
    const pct = total > 0 ? (hadir / total) * 100 : 0;
    setText('stat-attendance', total > 0 ? pct.toFixed(1) + '%' : '-');
    setText('attendance-pct-badge', total > 0 ? pct.toFixed(1) + '%' : 'Belum ada data');
    setText('att-hadir', attendance.hadir || 0);
    setText('att-izin', attendance.izin || 0);
    setText('att-sakit', attendance.sakit || 0);
    setText('att-alpa', attendance.alpa || 0);
    setText('att-total', total);
  }

  function renderSubjects(subjects) {
    const container = document.getElementById('subjects-container');
    if (!container) return;
    const names = Object.keys(subjects);

    if (!names.length) {
      container.innerHTML = '<div class="bg-white rounded-2xl border border-slate-200 p-8 text-center text-slate-400 text-xs">Belum ada nilai yang diinput oleh guru.</div>';
      return;
    }

    container.innerHTML = names.map(function (name) {
      const subject = subjects[name] || {};
      const grades = subject.grades || [];
      const avg = subject.average !== undefined ? subject.average : '-';
      const passed = avg !== '-' && Number(avg) >= Number(subject.kkm || 75);
      const badge = passed
        ? '<span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">Tuntas</span>'
        : '<span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-rose-100 text-rose-700 border border-rose-200">Perlu Remedial</span>';

      const rows = grades.map(function (g, idx) {
        return '<tr class="hover:bg-slate-50 transition-colors">' +
          '<td class="py-2 px-3 text-center font-bold text-slate-400">' + (idx + 1) + '</td>' +
          '<td class="py-2 px-3 font-semibold text-slate-800">' + esc(g.title) + '</td>' +
          '<td class="py-2 px-3 text-slate-500">' + esc(g.date) + '</td>' +
          '<td class="py-2 px-3 text-center font-mono text-slate-500">' + g.kkm + '</td>' +
          '<td class="py-2 px-3 text-center font-black ' + (g.passed ? 'text-indigo-950' : 'text-rose-600') + '">' + Number(g.score).toFixed(1) + '</td>' +
          '<td class="py-2 px-3 text-center"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold ' + (g.passed ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700') + '">' + (g.passed ? 'Tuntas' : 'Remedial') + '</span></td>' +
          '</tr>';
      }).join('');

      return '<details class="group bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden" open>' +
        '<summary class="flex flex-wrap items-center justify-between gap-3 p-4 cursor-pointer select-none hover:bg-slate-50/70 transition-colors">' +
        '<div class="flex items-center gap-3 min-w-0">' +
        '<span class="w-10 h-10 rounded-xl bg-indigo-950 text-amber-300 font-black flex items-center justify-center text-xs shrink-0">' + esc(name.substring(0, 2).toUpperCase()) + '</span>' +
        '<div class="min-w-0"><p class="font-extrabold text-sm text-indigo-950 truncate">' + esc(name) + '</p>' +
        '<p class="text-[11px] text-slate-500 truncate">Guru: ' + esc(subject.teacher || '-') + ' · KKM ' + (subject.kkm || 75) + '</p></div></div>' +
        '<div class="flex items-center gap-2"><span class="text-lg font-black ' + (passed ? 'text-indigo-950' : 'text-rose-600') + '">' + esc(avg) + '</span>' + badge +
        '<span class="text-slate-400 group-open:rotate-180 transition-transform text-xs">▼</span></div>' +
        '</summary>' +
        '<div class="border-t border-slate-100 overflow-x-auto custom-scrollbar">' +
        '<table class="w-full min-w-[520px] text-left text-xs border-collapse">' +
        '<thead><tr class="bg-slate-50 text-slate-500 uppercase tracking-wider text-[10px] border-b border-slate-200">' +
        '<th class="py-2 px-3 w-10 text-center">No</th><th class="py-2 px-3">Jenis Penilaian</th><th class="py-2 px-3">Tanggal</th>' +
        '<th class="py-2 px-3 text-center">KKM</th><th class="py-2 px-3 text-center">Nilai</th><th class="py-2 px-3 text-center">Status</th></tr></thead>' +
        '<tbody class="divide-y divide-slate-100">' + (rows || '<tr><td colspan="6" class="py-4 text-center text-slate-400">Belum ada nilai.</td></tr>') + '</tbody>' +
        '</table></div></details>';
    }).join('');
  }

  function renderModules(modules) {
    const container = document.getElementById('modules-container');
    const filter = document.getElementById('modules-filter');
    if (!container) return;

    if (filter) {
      const previous = filter.value || 'ALL';
      const subjects = Array.from(new Set(modules.map(function (m) { return m.subject; })));
      filter.innerHTML = '<option value="ALL">Semua Mata Pelajaran</option>' +
        subjects.map(function (s) { return '<option value="' + esc(s) + '">' + esc(s) + '</option>'; }).join('');
      filter.value = subjects.indexOf(previous) !== -1 ? previous : 'ALL';
      filter.onchange = function () { renderModules(modules); };
    }

    const chosen = filter ? filter.value : 'ALL';
    const shown = modules.filter(function (m) { return chosen === 'ALL' || m.subject === chosen; });

    if (!shown.length) {
      container.innerHTML = '<p class="text-center text-slate-400 text-xs py-4">Belum ada materi yang dibagikan guru untuk kelasmu.</p>';
      return;
    }

    container.innerHTML = shown.map(function (m) {
      return '<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl border border-slate-200 hover:border-indigo-300 transition-colors">' +
        '<div class="flex items-center gap-3 min-w-0">' +
        '<div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-[10px] shrink-0">' + esc(m.ext || 'FILE') + '</div>' +
        '<div class="min-w-0"><p class="font-bold text-xs sm:text-sm text-slate-800 truncate">' + esc(m.title) + '</p>' +
        (m.desc ? '<p class="text-[11px] text-slate-500">' + esc(m.desc) + '</p>' : '') +
        '<p class="text-[10px] text-slate-400">' + esc(m.subject) + ' · ' + esc(m.teacher || 'Guru Pengampu') + ' · ' + esc(m.size) + ' · ' + esc(m.date) + '</p></div></div>' +
        '<div class="flex items-center gap-2 shrink-0">' +
        '<a href="' + esc(m.url) + '&mode=view" target="_blank" rel="noopener" class="px-3 py-1.5 rounded-lg bg-indigo-950 hover:bg-indigo-900 text-white text-[11px] font-bold">Baca</a>' +
        '<a href="' + esc(m.url) + '" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50 text-slate-700 text-[11px] font-bold">Unduh</a>' +
        '</div></div>';
    }).join('');
  }

  async function start() {
    const loading = document.getElementById('portal-loading');
    const content = document.getElementById('portal-content');
    const errorBox = document.getElementById('portal-error');
    try {
      const data = await api('student_bootstrap');
      renderHeader(data.student || {});
      renderStats(data.student || {}, data.subjects || {}, data.attendance || {});
      renderSubjects(data.subjects || {});
      renderModules(data.modules || []);
      if (loading) loading.classList.add('hidden');
      if (content) content.classList.remove('hidden');
    } catch (err) {
      if (loading) loading.classList.add('hidden');
      if (errorBox) {
        errorBox.innerText = 'Gagal memuat data: ' + err.message;
        errorBox.classList.remove('hidden');
      }
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();