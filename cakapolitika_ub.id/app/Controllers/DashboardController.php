<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Aspirasi;
use App\Models\Poll;

class DashboardController extends Controller
{
    public function index(): string
    {
        return $this->render('dashboard/index', [
            'title'         => 'Dashboard Aspirasi — CAKAPOLITIK_UB.ID',
            'poll'          => Poll::results(),
            'pollResponden' => Poll::totalResponden(),
            'byKategori'    => Aspirasi::summaryByKategori(),
            'byPersona'     => Aspirasi::summaryByPersona(),
            'recent'        => Aspirasi::recentPublic(10),
            'total'         => Aspirasi::count("status_moderasi = 'lolos'"),
        ]);
    }
}
