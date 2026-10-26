<?php
/** @var \App\Core\Router $router */

// --- Public site --------------------------------------------------------
$router->get('/',           'HomeController@index');
$router->get('/tentang',    'HomeController@about');
$router->get('/chat',       'ChatController@index');
$router->get('/dashboard',  'DashboardController@index');

// --- Citizen API -------------------------------------------------------
$router->post('/api/otp/request',  'ChatApiController@otpRequest');
$router->post('/api/otp/verify',   'ChatApiController@otpVerify');
$router->get('/api/persona/list',  'ChatApiController@personaList');
$router->get('/api/wilayah',       'WilayahController@list');
$router->post('/api/chat/brone',   'ChatApiController@brone');
$router->post('/api/chat/persona', 'ChatApiController@persona');
$router->post('/api/aspirasi',     'ChatApiController@aspirasi');
$router->post('/api/poll/submit',  'ChatApiController@pollSubmit');
$router->post('/api/session/reset','ChatApiController@reset');
$router->get('/api/dashboard/summary', 'DashboardApiController@summary');

// --- Admin ------------------------------------------------------------
$router->get('/admin/login',   'AdminAuthController@showLogin');
$router->post('/admin/login',  'AdminAuthController@login');
$router->post('/admin/logout', 'AdminAuthController@logout');

$router->get('/admin',          'AdminController@dashboard');
$router->get('/admin/api-log',   'AdminController@apiLog');
$router->get('/admin/akun',      'AdminController@account');
$router->post('/admin/akun',     'AdminController@updateAccount');

$router->get('/admin/persona',              'AdminPersonaController@index');
$router->get('/admin/persona/create',       'AdminPersonaController@create');
$router->post('/admin/persona',             'AdminPersonaController@store');
$router->get('/admin/persona/{id}/edit',    'AdminPersonaController@edit');
$router->post('/admin/persona/{id}',        'AdminPersonaController@update');
$router->post('/admin/persona/{id}/delete', 'AdminPersonaController@destroy');

$router->get('/admin/persona/{id}/knowledge',  'AdminPersonaController@knowledge');
$router->post('/admin/persona/{id}/knowledge', 'AdminPersonaController@knowledgeStore');
$router->post('/admin/knowledge/{id}',         'AdminPersonaController@knowledgeUpdate');
$router->post('/admin/knowledge/{id}/delete',  'AdminPersonaController@knowledgeDestroy');

$router->get('/admin/aspirasi',               'AdminAspirasiController@index');
$router->post('/admin/aspirasi/{id}/moderasi','AdminAspirasiController@moderate');

$router->get('/admin/polling',            'AdminPollController@index');
$router->post('/admin/polling',           'AdminPollController@store');
$router->get('/admin/polling/{id}',       'AdminPollController@edit');
$router->post('/admin/polling/{id}',      'AdminPollController@update');
$router->post('/admin/polling/{id}/delete','AdminPollController@destroy');
$router->post('/admin/polling/{id}/opsi', 'AdminPollController@optionStore');
$router->post('/admin/poll-opsi/{id}',    'AdminPollController@optionUpdate');
