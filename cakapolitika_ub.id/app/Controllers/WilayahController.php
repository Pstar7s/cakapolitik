<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Wilayah;

class WilayahController extends Controller
{
    /**
     * GET /api/wilayah?level=kabkota
     * GET /api/wilayah?level=kecamatan&parent=35.07
     * GET /api/wilayah?level=kelurahan&parent=35.07.01
     */
    public function list(): string
    {
        $level  = $this->str('level', 'kabkota');
        $parent = preg_replace('/[^0-9.]/', '', (string)($_GET['parent'] ?? ''));

        if (!Wilayah::available()) {
            return $this->fail('Data wilayah belum dimuat di server.', 503);
        }

        $items = match ($level) {
            'kabkota'   => Wilayah::kabKota(),
            'kecamatan' => $this->childrenOf($parent, 2),
            'kelurahan' => $this->childrenOf($parent, 3),
            default     => null,
        };

        if ($items === null) {
            return $this->fail('Parameter tidak valid.', 422);
        }
        return $this->ok(['level' => $level, 'parent' => $parent, 'items' => $items]);
    }

    private function childrenOf(string $parent, int $expectedSegments): array
    {
        if ($parent === '' || substr_count($parent, '.') + 1 !== $expectedSegments
            || !str_starts_with($parent, Wilayah::PROVINSI_KODE . '.')) {
            return [];
        }
        return Wilayah::children($parent);
    }
}
