<div class="login-wrap">
  <div class="card-cr login-card">
    <div class="text-center mb-3">
      <img src="<?= asset('img/logo-cakapolitik.jpeg') ?>" alt="" height="52" class="mb-2">
      <h1 class="h4 mb-0">CAKA<span style="color:var(--gold-d)">PROOM</span></h1>
      <div class="mut small">Ruang debat digital berbasis kasus</div>
    </div>
    <div id="stepEmail">
      <label class="form-label">Email terdaftar</label>
      <input type="email" id="email" class="form-control" placeholder="nama@student.ub.ac.id" autocomplete="email" autofocus>
      <button id="btnReq" class="btn btn-navy w-100 mt-3">Kirim kode</button>
    </div>
    <div id="stepKode" class="d-none">
      <p class="small mut mb-2">Kode 6 digit dikirim ke <strong id="emailShow"></strong>.</p>
      <input type="text" id="kode" class="form-control form-control-lg text-center" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="••••••">
      <button id="btnVer" class="btn btn-navy w-100 mt-3">Masuk</button>
      <button id="btnBack" class="btn btn-link w-100 small">Ganti email / kirim ulang</button>
    </div>
    <div id="devCode" class="alert alert-warning small mt-3 d-none"></div>
    <div id="msg" class="small mt-2 text-danger"></div>
    <p class="small mut mt-3 mb-0">Akun dibuat oleh dosen/admin. Belum punya akses? Hubungi dosen pengampu.</p>
  </div>
</div>
<?php push_script('<script>
(function(){
  var csrf = ' . json_encode(csrf_token()) . ', $ = function(i){return document.getElementById(i)};
  function post(path, data){
    return fetch(' . json_encode(url('/api/auth/otp/')) . ' + path, {method:"POST", headers:{"Content-Type":"application/json","X-CSRF-Token":csrf}, body:JSON.stringify(data)})
      .then(function(r){return r.json().then(function(j){j._status=r.status; return j;});});
  }
  function msg(t){$("msg").textContent = t || "";}
  $("btnReq").onclick = function(){
    msg(""); var email = $("email").value.trim();
    if(!email){msg("Isi email Anda.");return;}
    $("btnReq").disabled = true;
    post("request", {email:email}).then(function(j){
      $("btnReq").disabled = false;
      if(!j.ok){msg(j.error||"Gagal mengirim kode.");return;}
      $("emailShow").textContent = email;
      $("stepEmail").classList.add("d-none"); $("stepKode").classList.remove("d-none"); $("kode").focus();
      if(j.dev_code){var d=$("devCode"); d.classList.remove("d-none"); d.textContent="Mode pengembangan — kode: "+j.dev_code;}
    }).catch(function(){$("btnReq").disabled=false;msg("Koneksi bermasalah.");});
  };
  $("btnVer").onclick = function(){
    msg(""); $("btnVer").disabled = true;
    post("verify", {email:$("email").value.trim(), kode:$("kode").value.trim()}).then(function(j){
      $("btnVer").disabled = false;
      if(!j.ok){msg(j.error||"Kode salah.");return;}
      location.href = j.next;
    }).catch(function(){$("btnVer").disabled=false;msg("Koneksi bermasalah.");});
  };
  $("btnBack").onclick = function(){ $("stepKode").classList.add("d-none"); $("stepEmail").classList.remove("d-none"); $("devCode").classList.add("d-none"); msg(""); };
  $("email").addEventListener("keydown", function(e){ if(e.key==="Enter") $("btnReq").click(); });
  $("kode").addEventListener("keydown", function(e){ if(e.key==="Enter") $("btnVer").click(); });
})();
</script>'); ?>
