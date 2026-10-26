<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Dapil;
use App\Models\KnowledgeChunk;
use App\Models\Persona;

class AdminPersonaController extends Controller
{
    public function index(): string
    {
        $this->requireAdmin();
        $personas = Persona::all('status_aktif DESC, nama ASC');
        foreach ($personas as &$p) {
            $p['chunk_count'] = KnowledgeChunk::countForPersona((int)$p['id']);
        }
        unset($p);
        return $this->render('admin/persona/index', [
            'title'    => 'Kelola Persona — Admin',
            'personas' => $personas,
            'success'  => flash('success'),
        ], 'layouts/admin');
    }

    public function create(): string
    {
        $this->requireAdmin();
        return $this->render('admin/persona/form', [
            'title'   => 'Tambah Persona — Admin',
            'persona' => null,
            'dapils'  => Dapil::all(),
        ], 'layouts/admin');
    }

    public function edit(string $id): string
    {
        $this->requireAdmin();
        $persona = Persona::find((int)$id);
        if (!$persona) {
            return $this->redirect('/admin/persona');
        }
        return $this->render('admin/persona/form', [
            'title'   => 'Edit Persona — Admin',
            'persona' => $persona,
            'dapils'  => Dapil::all(),
        ], 'layouts/admin');
    }

    public function store(): string
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $id = Persona::create($this->payload());
        flash('success', 'Persona baru ditambahkan.');
        return $this->redirect('/admin/persona/' . $id . '/knowledge');
    }

    public function update(string $id): string
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        Persona::updateById((int)$id, $this->payload());
        flash('success', 'Persona diperbarui.');
        return $this->redirect('/admin/persona');
    }

    public function destroy(string $id): string
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        Persona::deleteById((int)$id);
        flash('success', 'Persona dihapus.');
        return $this->redirect('/admin/persona');
    }

    private function payload(): array
    {
        return [
            'nama'                => trim($_POST['nama'] ?? ''),
            'partai'              => trim($_POST['partai'] ?? ''),
            'jabatan'             => trim($_POST['jabatan'] ?? ''),
            'dapil_id'            => ($_POST['dapil_id'] ?? '') !== '' ? (int)$_POST['dapil_id'] : null,
            'foto_url'            => trim($_POST['foto_url'] ?? ''),
            'deskripsi_singkat'   => trim($_POST['deskripsi_singkat'] ?? ''),
            'pesan_pembuka'       => trim($_POST['pesan_pembuka'] ?? ''),
            'gaya_bicara'         => trim($_POST['gaya_bicara'] ?? ''),
            'system_prompt_extra' => trim($_POST['system_prompt_extra'] ?? ''),
            'perolehan_suara'     => ($_POST['perolehan_suara'] ?? '') !== '' ? (int)$_POST['perolehan_suara'] : null,
            'status_aktif'        => isset($_POST['status_aktif']) ? 1 : 0,
        ];
    }

    // --- Knowledge base -------------------------------------------------

    public function knowledge(string $id): string
    {
        $this->requireAdmin();
        $persona = Persona::find((int)$id);
        if (!$persona) {
            return $this->redirect('/admin/persona');
        }
        return $this->render('admin/persona/knowledge', [
            'title'   => 'Knowledge Base — ' . $persona['nama'],
            'persona' => $persona,
            'chunks'  => KnowledgeChunk::forPersona((int)$id, false),
            'success' => flash('success'),
        ], 'layouts/admin');
    }

    public function knowledgeStore(string $id): string
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $persona = Persona::find((int)$id);
        if (!$persona) {
            return $this->redirect('/admin/persona');
        }
        $teks = trim($_POST['teks_chunk'] ?? '');
        if ($teks !== '') {
            KnowledgeChunk::create([
                'persona_id' => (int)$id,
                'sumber'     => trim($_POST['sumber'] ?? '') ?: 'profil',
                'judul'      => trim($_POST['judul'] ?? ''),
                'teks_chunk' => $teks,
                'referensi'  => trim($_POST['referensi'] ?? ''),
                'is_aktif'   => isset($_POST['is_aktif']) ? 1 : 1,
            ]);
            flash('success', 'Potongan knowledge base ditambahkan.');
        }
        return $this->redirect('/admin/persona/' . $id . '/knowledge');
    }

    public function knowledgeUpdate(string $id): string
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $chunk = KnowledgeChunk::find((int)$id);
        if (!$chunk) {
            return $this->redirect('/admin/persona');
        }
        if (isset($_POST['delete'])) {
            KnowledgeChunk::deleteById((int)$id);
            flash('success', 'Potongan dihapus.');
        } else {
            KnowledgeChunk::updateById((int)$id, [
                'sumber'     => trim($_POST['sumber'] ?? '') ?: 'profil',
                'judul'      => trim($_POST['judul'] ?? ''),
                'teks_chunk' => trim($_POST['teks_chunk'] ?? ''),
                'referensi'  => trim($_POST['referensi'] ?? ''),
                'is_aktif'   => isset($_POST['is_aktif']) ? 1 : 0,
            ]);
            flash('success', 'Potongan diperbarui.');
        }
        return $this->redirect('/admin/persona/' . $chunk['persona_id'] . '/knowledge');
    }

    public function knowledgeDestroy(string $id): string
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $chunk = KnowledgeChunk::find((int)$id);
        if ($chunk) {
            KnowledgeChunk::deleteById((int)$id);
            flash('success', 'Potongan dihapus.');
            return $this->redirect('/admin/persona/' . $chunk['persona_id'] . '/knowledge');
        }
        return $this->redirect('/admin/persona');
    }
}
