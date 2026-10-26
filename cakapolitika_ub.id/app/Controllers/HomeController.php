<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Aspirasi;
use App\Models\Dapil;
use App\Models\Persona;

class HomeController extends Controller
{
    public function index(): string
    {
        $dapil = Dapil::demo();
        return $this->render('home/index', [
            'title'    => 'CAKAPOLITIK_UB.ID — Ruang Publik Digital',
            'dapil'    => $dapil,
            'personas' => $dapil ? Persona::activeByDapil((int)$dapil['id']) : [],
            'summary'  => Aspirasi::summaryByKategori(),
        ]);
    }

    public function about(): string
    {
        return $this->render('home/about', [
            'title' => 'Tentang — CAKAPOLITIK_UB.ID',
        ]);
    }
}
