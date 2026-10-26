<?php /** @var array|null $dapil */ ?>
<div class="cp-chat-shell">

  <header class="cp-chat-head">
    <img src="<?= asset('img/brone-maskot.png') ?>" alt="BRONE">
    <div>
      <div class="fw-bold" style="line-height:1.1">BRONE</div>
      <div class="small" style="opacity:.75;line-height:1">Host CAKAPOLITIK_UB.ID</div>
    </div>
    <a href="<?= url('/') ?>" class="cp-status text-white text-decoration-none">✕ Keluar</a>
  </header>

  <div class="cp-chat-log" id="chatLog" aria-live="polite"></div>

  <div id="chatPanel" class="px-3"></div>

  <footer class="cp-chat-foot">
    <form id="composer" class="d-flex gap-2 align-items-end" autocomplete="off">
      <textarea id="composerInput" class="form-control" rows="1" maxlength="2000"
                placeholder="Tulis pesan untuk BRONE…" disabled></textarea>
      <button class="btn btn-cp-navy px-3" id="composerSend" type="submit" disabled>Kirim</button>
    </form>
    <div class="small text-muted mt-1" id="composerHint">Mulai dengan melengkapi data diri di atas.</div>
  </footer>
</div>

<!-- ===== Templates ===== -->
<template id="tpl-identity">
  <div class="cp-panel my-2">
    <div class="fw-bold mb-2">Data diri (sesuai KTP)</div>
    <form id="identityForm" class="row g-2">
      <div class="col-md-6">
        <label class="form-label small mb-0">Nama lengkap</label>
        <input name="nama" class="form-control" placeholder="Nama sesuai KTP" required>
      </div>
      <div class="col-md-6">
        <label class="form-label small mb-0">Email aktif</label>
        <input name="email" type="email" class="form-control" placeholder="nama@email.com" required>
      </div>
      <div class="col-md-6">
        <label class="form-label small mb-0">Provinsi</label>
        <input class="form-control" value="Jawa Timur" readonly>
      </div>
      <div class="col-md-6">
        <label class="form-label small mb-0">Kabupaten/Kota</label>
        <select name="kode_kabkota" id="wKabkota" class="form-select" required>
          <option value="">— pilih —</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label small mb-0">Kecamatan</label>
        <select name="kode_kecamatan" id="wKecamatan" class="form-select" required disabled>
          <option value="">— pilih kabupaten/kota dulu —</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label small mb-0">Kelurahan/Desa</label>
        <select name="kode_kelurahan" id="wKelurahan" class="form-select" required disabled>
          <option value="">— pilih kecamatan dulu —</option>
        </select>
      </div>
      <div class="col-4 col-md-2">
        <label class="form-label small mb-0">RT</label>
        <input name="alamat_rt" class="form-control" inputmode="numeric" maxlength="4" placeholder="000">
      </div>
      <div class="col-4 col-md-2">
        <label class="form-label small mb-0">RW</label>
        <input name="alamat_rw" class="form-control" inputmode="numeric" maxlength="4" placeholder="000">
      </div>
      <div class="col-4 col-md-8">
        <label class="form-label small mb-0">Bahasa</label>
        <select name="bahasa" class="form-select">
          <option value="Indonesia">Bahasa Indonesia</option>
          <option value="Jawa">Bahasa Jawa</option>
          <option value="Inggris">English</option>
        </select>
      </div>
      <div class="col-12 form-check ms-2 mt-2">
        <input class="form-check-input" type="checkbox" name="anonim" id="anonimChk">
        <label class="form-check-label small" for="anonimChk">Kirim aspirasi secara anonim (nama tidak ditampilkan di dashboard publik)</label>
      </div>
      <div class="col-12 d-flex align-items-center gap-2 mt-2">
        <button class="btn btn-cp-gold" type="submit">Kirim kode verifikasi</button>
        <span class="text-danger small" data-err></span>
      </div>
    </form>
  </div>
</template>

<template id="tpl-otp">
  <div class="cp-panel my-2">
    <div class="fw-bold mb-2">Masukkan kode verifikasi</div>
    <form id="otpForm" class="d-flex flex-wrap gap-2 align-items-center">
      <input name="code" class="form-control" style="max-width:160px" inputmode="numeric" maxlength="6" placeholder="6 digit" required>
      <button class="btn btn-cp-gold" type="submit">Verifikasi</button>
      <button class="btn btn-outline-secondary" type="button" id="otpResend" disabled>Kirim ulang</button>
      <span class="text-danger small" data-err></span>
    </form>
    <div class="cp-dev-badge mt-2 d-none" id="devCode"></div>
  </div>
</template>

<template id="tpl-personas">
  <div class="cp-panel my-2">
    <div class="fw-bold mb-2">Pilih pemimpin politik</div>
    <div class="row g-2" id="personaList"></div>
  </div>
</template>

<template id="tpl-persona-actions">
  <div class="d-flex gap-2 my-2 flex-wrap">
    <button class="btn btn-sm btn-outline-secondary" data-act="back">← Pilih tokoh lain</button>
    <button class="btn btn-sm btn-outline-danger" data-act="close">Akhiri sesi</button>
  </div>
</template>

<template id="tpl-poll">
  <div class="cp-panel cp-poll my-2">
    <div class="fw-bold mb-2">Jajak Pendapat Aspirasi Warga</div>
    <div id="pollBox" class="cp-poll-box"></div>
    <div class="d-flex align-items-center gap-3 mt-2">
      <button class="btn btn-cp-gold" type="button" id="pollSubmit">Kirim jawaban</button>
      <a href="#" class="small text-muted" id="pollSkip">Lewati</a>
    </div>
  </div>
</template>
