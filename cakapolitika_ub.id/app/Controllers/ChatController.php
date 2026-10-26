<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Dapil;

class ChatController extends Controller
{
    public function index(): string
    {
        return $this->render('chat/index', [
            'title' => 'Ngobrol dengan BRONE — CAKAPOLITIK_UB.ID',
            'dapil' => Dapil::demo(),
        ], 'layouts/chat');
    }
}
