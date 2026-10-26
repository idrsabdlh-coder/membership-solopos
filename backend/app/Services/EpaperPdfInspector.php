<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use Throwable;

class EpaperPdfInspector
{
    public function pageCount(string $path): int
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'master_pdf' => ['File PDF tidak dapat dibaca.'],
            ]);
        }

        $header = fread($handle, 5);
        fclose($handle);

        if ($header !== '%PDF-') {
            throw ValidationException::withMessages([
                'master_pdf' => ['File yang diunggah bukan dokumen PDF yang valid.'],
            ]);
        }

        try {
            $pdf = new Fpdi;
            $pages = $pdf->setSourceFile($path);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'master_pdf' => ['PDF tidak dapat dibaca. Pastikan file tidak rusak atau terenkripsi.'],
            ]);
        }

        if ($pages < 1 || $pages > 65535) {
            throw ValidationException::withMessages([
                'master_pdf' => ['Jumlah halaman PDF tidak valid atau melebihi batas.'],
            ]);
        }

        return $pages;
    }
}
