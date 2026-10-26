<?php
/** @var \App\Core\Router $router */

// --- Auth ----------------------------------------------------------------
$router->get('/',                 'AuthController@home');
$router->get('/login',            'AuthController@showLogin');
$router->post('/api/auth/otp/request', 'AuthController@otpRequest');
$router->post('/api/auth/otp/verify',  'AuthController@otpVerify');
$router->get('/privasi',          'AuthController@privasi');
$router->post('/privasi',         'AuthController@privasiOk');
$router->post('/logout',          'AuthController@logout');

// --- Admin -----------------------------------------------------------------
$router->get('/admin',                       'AdminController@dashboard');
$router->get('/admin/pengguna',              'AdminController@pengguna');
$router->post('/admin/pengguna',             'AdminController@penggunaTambah');
$router->post('/admin/pengguna/{id}/toggle', 'AdminController@penggunaToggle');
$router->get('/admin/kelas',                 'AdminController@kelas');
$router->post('/admin/kelas',                'AdminController@kelasBuat');
$router->get('/admin/kelas/{id}',            'AdminController@kelasDetail');
$router->post('/admin/kelas/{id}/import',    'AdminController@kelasImport');
$router->get('/admin/kasus',                 'AdminController@kasusIndex');
$router->get('/admin/kasus/baru',            'AdminController@kasusBaru');
$router->post('/admin/kasus',                'AdminController@kasusSimpan');
$router->get('/admin/kasus/{id}/edit',       'AdminController@kasusEdit');
$router->post('/admin/kasus/{id}',           'AdminController@kasusSimpan');
$router->post('/admin/kasus/{id}/hapus',     'AdminController@kasusHapus');

// --- Dosen (halaman) ---------------------------------------------------------
$router->get('/dosen',                        'DosenController@index');
$router->get('/dosen/sesi/baru',              'DosenController@formSesi');
$router->post('/dosen/sesi',                  'DosenController@buatSesi');
$router->get('/dosen/sesi/{id}',              'DosenController@sesi');
$router->get('/dosen/sesi/{id}/transkrip',    'DosenController@transkrip');
$router->get('/dosen/sesi/{id}/export.csv',   'DosenController@exportCsv');

// --- Dosen (API) ----------------------------------------------------------------
$router->get('/api/dosen/sesi/{id}/state',          'DosenController@state');
$router->post('/api/dosen/sesi/{id}/bagi-peran',    'DosenController@bagiPeran');
$router->post('/api/dosen/sesi/{id}/fase',          'DosenController@fase');
$router->post('/api/dosen/sesi/{id}/delegasi',      'DosenController@delegasi');
$router->post('/api/dosen/sesi/{id}/snapshot',      'DosenController@snapshot');
$router->post('/api/dosen/sesi/{id}/skor-ai',       'DosenController@hitungSkor');
$router->get('/api/dosen/sesi/{id}/penilaian',      'DosenController@penilaian');
$router->post('/api/dosen/sesi/{id}/nilai-final',   'DosenController@nilaiFinal');
$router->post('/api/dosen/sesi/{id}/publikasi',     'DosenController@publikasi');
$router->get('/api/dosen/sesi/{id}/individu',       'DosenController@individu');
$router->post('/api/dosen/sesi/{id}/individu/{uid}','DosenController@setIndividu');

// --- Mahasiswa ----------------------------------------------------------------------
$router->get('/mhs',                          'MahasiswaController@index');
$router->get('/sesi/{id}',                    'MahasiswaController@room');
$router->post('/api/sesi/{id}/join',          'MahasiswaController@join');
$router->get('/api/sesi/{id}/state',          'MahasiswaController@state');
$router->post('/api/sesi/{id}/post',          'MahasiswaController@post');
$router->post('/api/sesi/{id}/resolusi/kunci',  'MahasiswaController@kunci');
$router->post('/api/sesi/{id}/resolusi/simpan', 'MahasiswaController@simpan');
$router->post('/api/sesi/{id}/resolusi/sikap',  'MahasiswaController@sikap');
