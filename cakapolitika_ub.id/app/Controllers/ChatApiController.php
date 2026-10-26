<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Aspirasi;
use App\Models\Dapil;
use App\Models\Persona;
use App\Models\Poll;
use App\Models\Warga;
use App\Services\AspirationClassifier;
use App\Services\BroneService;
use App\Services\ClaudeException;
use App\Services\KategoriGuess;
use App\Services\ModerationService;
use App\Services\OtpService;
use App\Services\PersonaChatService;

class ChatApiController extends Controller
{
    private function flow(): array
    {
        return $_SESSION['flow'] ?? ['step' => 'identity'];
    }

    private function setFlow(array $patch): void
    {
        $_SESSION['flow'] = array_merge($this->flow(), $patch);
    }

    // -- POST /api/otp/request -------------------------------------------------
    public function otpRequest(): string
    {
        $this->verifyCsrf();

        // Resend path: warga already created this session.
        $resend = filter_var($this->input('resend', false), FILTER_VALIDATE_BOOLEAN);
        if ($resend && !empty($_SESSION['flow']['warga_id'])) {
            $warga = Warga::find((int)$_SESSION['flow']['warga_id']);
            $res = (new OtpService())->request((int)$warga['id'], $warga['email']);
            return $res['ok']
                ? $this->ok(['message' => 'Kode baru sudah dikirim.'] + $this->devCode($res))
                : $this->fail($res['error'], 429, ['resend_in' => $res['resend_in'] ?? null]);
        }

        $nama   = $this->str('nama');
        $email  = mb_strtolower($this->str('email'));
        $rt     = $this->str('alamat_rt');
        $rw     = $this->str('alamat_rw');
        $bahasa = $this->str('bahasa', 'Indonesia') ?: 'Indonesia';
        $anonim = filter_var($this->input('anonim', false), FILTER_VALIDATE_BOOLEAN);

        // Cascading region selects send the leaf kode (kelurahan/desa, level 4).
        $kodeKel = preg_replace('/[^0-9.]/', '', $this->str('kode_kelurahan'));
        $kel = $kec = $kab = '';
        $kabKode = null;

        if ($kodeKel && substr_count($kodeKel, '.') === 3 && \App\Models\Wilayah::available()) {
            $seg = explode('.', $kodeKel);
            $kel = \App\Models\Wilayah::nama($kodeKel) ?? '';
            $kec = \App\Models\Wilayah::nama("$seg[0].$seg[1].$seg[2]") ?? '';
            $kab = \App\Models\Wilayah::nama("$seg[0].$seg[1]") ?? '';
            $kabKode = "$seg[0].$seg[1]";
        } else {
            // Fallback: accept free-text fields (older client / no region data).
            $kel = $this->str('kelurahan');
            $kec = $this->str('kecamatan');
            $kab = $this->str('kab_kota');
        }

        $errs = [];
        if (mb_strlen($nama) < 2)                       $errs['nama'] = 'Nama wajib diisi.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs['email'] = 'Email tidak valid.';
        if ($kel === '' || $kec === '' || $kab === '')  $errs['alamat'] = 'Pilih kabupaten/kota, kecamatan, dan kelurahan/desa Anda.';
        if ($errs) {
            return $this->fail('Mohon lengkapi data Anda.', 422, ['fields' => $errs]);
        }

        $dapil = Dapil::demo();
        $inDapil = Dapil::matchesDemo($kab, $kabKode);

        $warga = Warga::upsert([
            'nama'          => $nama,
            'email'         => $email,
            'alamat_rt'     => $rt,
            'alamat_rw'     => $rw,
            'provinsi'      => 'Jawa Timur',
            'kelurahan'     => $kel,
            'kecamatan'     => $kec,
            'kab_kota'      => $kab,
            'kode_wilayah'  => $kodeKel ?: null,
            'dapil_id'      => $inDapil ? (int)$dapil['id'] : null,
            'bahasa_pilihan'=> $bahasa,
        ]);

        $this->setFlow([
            'step'      => 'otp',
            'warga_id'  => (int)$warga['id'],
            'dapil_id'  => $inDapil ? (int)$dapil['id'] : null,
            'in_dapil'  => $inDapil,
            'anonim'    => $anonim,
            'personas_talked' => [],
        ]);
        unset($_SESSION['warga_verified']);
        $_SESSION['warga_id'] = (int)$warga['id'];

        $res = (new OtpService())->request((int)$warga['id'], $email);
        if (!$res['ok']) {
            return $this->fail($res['error'], 429, ['resend_in' => $res['resend_in'] ?? null]);
        }

        return $this->ok([
            'brone'     => BroneService::otpSent($email),
            'resend_in' => $res['resend_in'] ?? 60,
        ] + $this->devCode($res));
    }

    private function devCode(array $res): array
    {
        return isset($res['dev_code']) ? ['dev_code' => $res['dev_code']] : [];
    }

    // -- POST /api/otp/verify -----------------------------------------------
    public function otpVerify(): string
    {
        $this->verifyCsrf();
        $flow = $this->flow();
        if (empty($flow['warga_id'])) {
            return $this->fail('Sesi tidak ditemukan. Mulai ulang dari awal.', 400);
        }
        $code = $this->str('code');
        $res = (new OtpService())->verify((int)$flow['warga_id'], $code);
        if (!$res['ok']) {
            return $this->fail($res['error'], 422);
        }

        $_SESSION['warga_verified'] = true;
        $this->setFlow(['step' => empty($flow['in_dapil']) ? 'menu' : 'menu']);

        $warga = Warga::find((int)$flow['warga_id']);
        $dapil = Dapil::demo();

        $brone = empty($flow['in_dapil'])
            ? BroneService::outsideDapil() . "\n\n" . BroneService::verified($warga, $dapil)
            : BroneService::verified($warga, $dapil);

        return $this->ok([
            'brone'    => $brone,
            'in_dapil' => (bool)($flow['in_dapil'] ?? false),
            'personas' => BroneService::personaCards((int)$dapil['id']),
        ]);
    }

    // -- GET /api/persona/list --------------------------------------------
    public function personaList(): string
    {
        $dapilId = (int)($_GET['dapil_id'] ?? ($this->flow()['dapil_id'] ?? 0));
        if (!$dapilId) {
            $d = Dapil::demo();
            $dapilId = $d ? (int)$d['id'] : 0;
        }
        return $this->ok(['personas' => BroneService::personaCards($dapilId)]);
    }

    // -- POST /api/chat/brone --------------------------------------------
    public function brone(): string
    {
        $this->verifyCsrf();
        $action = $this->str('action');
        $flow = $this->flow();

        if (in_array($action, ['choose_persona', 'switch_persona'], true)) {
            if (empty($_SESSION['warga_verified'])) {
                return $this->fail('Verifikasi identitas dulu ya.', 401);
            }
            $pid = (int)$this->input('persona_id');
            $persona = Persona::find($pid);
            if (!$persona) {
                return $this->fail('Persona tidak ditemukan.', 404);
            }
            if ((int)$persona['status_aktif'] !== 1) {
                return $this->ok(['brone' => BroneService::personaInactive(), 'handoff' => false]);
            }
            $firstTime = !in_array($pid, $flow['personas_talked'] ?? [], true);
            $talked = array_values(array_unique(array_merge($flow['personas_talked'] ?? [], [$pid])));
            $this->setFlow(['step' => 'persona', 'current_persona_id' => $pid, 'personas_talked' => $talked]);

            // Sapaan pembuka scripted (bukan AI). Hanya saat pertama kali ke persona ini.
            $opening = $firstTime ? trim((string)($persona['pesan_pembuka'] ?? '')) : '';
            if ($opening !== '') {
                $wargaId = (int)($_SESSION['warga_id'] ?? $flow['warga_id'] ?? 0);
                if ($wargaId) {
                    $pc = \App\Models\Percakapan::findOrCreate(session_id(), $wargaId, $pid, 'persona');
                    \App\Models\Percakapan::addMessage((int)$pc['id'], 'ai', $opening, ['scripted' => true]);
                }
            }

            return $this->ok([
                'brone'   => BroneService::handoff($persona),
                'handoff' => true,
                'persona' => [
                    'id' => $pid, 'nama' => $persona['nama'], 'partai' => $persona['partai'],
                    'jabatan' => $persona['jabatan'], 'foto_url' => $persona['foto_url'],
                ],
                'opening'       => $opening,
                'opening_label' => $opening !== '' ? 'Sapaan pembuka — teks resmi, bukan AI' : null,
            ]);
        }

        if ($action === 'back_to_menu') {
            $this->setFlow(['step' => 'menu', 'current_persona_id' => null]);
            $dapil = Dapil::demo();
            return $this->ok([
                'brone'    => BroneService::backToMenu(),
                'personas' => BroneService::personaCards((int)$dapil['id']),
            ]);
        }

        if ($action === 'close') {
            // Tutup sesi dengan jajak pendapat aspirasi (bila belum diisi & ada pertanyaan aktif).
            if (empty($flow['poll_done']) && !empty($_SESSION['warga_verified'])) {
                $questions = Poll::pendingQuestions((int)($_SESSION['warga_id'] ?? 0));
                if ($questions) {
                    return $this->ok([
                        'brone'  => BroneService::pollIntro($this->currentWarga() ?? []),
                        'poll'   => $questions,
                        'closed' => false,
                    ]);
                }
            }
            return $this->ok(['brone' => BroneService::closing(), 'closed' => true]);
        }

        return $this->fail('Aksi tidak dikenal.', 400);
    }

    // -- POST /api/chat/persona -----------------------------------------
    public function persona(): string
    {
        $this->verifyCsrf();
        $warga = $this->requireWargaJson();
        $flow = $this->flow();

        $personaId = (int)($this->input('persona_id') ?: ($flow['current_persona_id'] ?? 0));
        $message = $this->str('message');
        if ($personaId <= 0) {
            return $this->fail('Pilih pemimpin politik dulu.', 400);
        }
        if (mb_strlen($message) < 2) {
            return $this->fail('Pesan terlalu pendek.', 422);
        }
        if (mb_strlen($message) > 2000) {
            return $this->fail('Pesan maksimal 2000 karakter.', 422);
        }

        try {
            $result = (new PersonaChatService())->handle(
                (int)$warga['id'], $personaId, session_id(), $message
            );
        } catch (ClaudeException $e) {
            return $this->fail('Layanan AI sedang bermasalah. Coba lagi sebentar lagi.', 502, [
                'debug' => is_local() ? $e->getMessage() : null,
            ]);
        }

        return $this->ok([
            'reply'    => $result['reply'],
            'label'    => 'Jawaban dihasilkan AI berdasarkan data publik',
            'aspirasi' => $result['aspirasi'],
            'retrieval'=> is_local() ? $result['retrieval'] : null,
            'stub'     => $result['stub'],
        ]);
    }

    // -- POST /api/aspirasi (explicit submit) ---------------------------
    public function aspirasi(): string
    {
        $this->verifyCsrf();
        $warga = $this->requireWargaJson();
        $flow = $this->flow();

        $text = $this->str('isi_aspirasi');
        $personaId = (int)($this->input('persona_id') ?: ($flow['current_persona_id'] ?? 0)) ?: null;
        if (mb_strlen($text) < 10) {
            return $this->fail('Aspirasi terlalu pendek (minimal 10 karakter).', 422);
        }

        $mod = (new ModerationService())->check($text);
        $kategori = $mod['status'] === 'lolos'
            ? (new AspirationClassifier())->classify($text)
            : KategoriGuess::fromText($text);

        $id = Aspirasi::create([
            'warga_id'        => (int)$warga['id'],
            'persona_id'      => $personaId,
            'isi_aspirasi'    => $text,
            'kategori'        => $kategori,
            'is_anonim'       => !empty($flow['anonim']) ? 1 : 0,
            'status_moderasi' => $mod['status'],
            'alasan_moderasi' => $mod['alasan'],
        ]);

        return $this->ok([
            'id'       => $id,
            'kategori' => $kategori,
            'status'   => $mod['status'],
            'message'  => $mod['status'] === 'lolos'
                ? 'Aspirasi Anda tercatat pada kategori "' . $kategori . '".'
                : 'Aspirasi Anda ditahan sistem moderasi untuk ditinjau.',
        ]);
    }

    // -- POST /api/poll/submit ---------------------------------------
    public function pollSubmit(): string
    {
        $this->verifyCsrf();
        $warga = $this->requireWargaJson();
        $flow = $this->flow();

        $skip = filter_var($this->input('skip', false), FILTER_VALIDATE_BOOLEAN);
        $saved = 0;
        if (!$skip) {
            $answers = $this->input('answers', []);
            if (is_array($answers) && $answers) {
                $saved = Poll::record(
                    (int)$warga['id'],
                    session_id(),
                    $answers,
                    $warga,
                    !empty($flow['dapil_id']) ? (int)$flow['dapil_id'] : null
                );
            }
        }

        $this->setFlow(['poll_done' => true]);

        return $this->ok([
            'brone'  => BroneService::pollThanks($skip || $saved === 0),
            'closed' => true,
            'saved'  => $saved,
        ]);
    }

    // -- POST /api/session/reset --------------------------------------
    public function reset(): string
    {
        $this->verifyCsrf();
        unset($_SESSION['flow'], $_SESSION['warga_id'], $_SESSION['warga_verified']);
        return $this->ok(['brone' => BroneService::greeting()]);
    }
}
