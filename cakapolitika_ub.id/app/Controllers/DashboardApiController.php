<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Aspirasi;
use App\Models\Poll;

class DashboardApiController extends Controller
{
    public function summary(): string
    {
        return $this->ok([
            'total'          => Aspirasi::count("status_moderasi = 'lolos'"),
            'by_kategori'    => Aspirasi::summaryByKategori(),
            'by_persona'     => Aspirasi::summaryByPersona(),
            'recent'         => Aspirasi::recentPublic(8),
            'poll'           => Poll::results(),
            'poll_responden' => Poll::totalResponden(),
        ]);
    }
}
