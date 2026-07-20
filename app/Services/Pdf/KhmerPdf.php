<?php

namespace App\Services\Pdf;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/**
 * Builds an mPDF instance that can actually render Khmer.
 *
 * Why mPDF and not the app-wide Dompdf: Khmer is a complex script — subscript
 * consonants (coeng) stack below the base glyph and some vowels reorder ahead
 * of the consonant they follow in the code-point stream. Both are OpenType GSUB
 * features. Dompdf has no shaping engine and ignores GSUB entirely, so every
 * Khmer PDF it produced came out with the subscripts inline and the vowels in
 * the wrong order. mPDF ships an OTL shaper; `useOTL => 0xFF` turns it on.
 *
 * Dompdf remains the engine for every other document in the app — this class is
 * only for Khmer-bearing documents (currently the rental contract).
 */
class KhmerPdf
{
    /** Body face — full Khmer + Latin coverage, including the riel sign. */
    public const BODY = 'khmerossiemreap';

    /** Traditional Khmer titling face, used for the letterhead and title. */
    public const TITLE = 'khmerosmuol';

    /**
     * Render a Blade view to raw PDF bytes.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data, string $orientation = 'P'): string
    {
        $mpdf = $this->make($orientation);
        $mpdf->WriteHTML(view($view, $data)->render());

        return $mpdf->Output('', 'S');
    }

    /** A configured mPDF instance with the Khmer fonts registered. */
    public function make(string $orientation = 'P'): Mpdf
    {
        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new FontVariables)->getDefaults()['fontdata'];

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => $orientation,
            'tempDir' => $this->tempDir(),

            // Page box — the old Dompdf template set this with @page, which mPDF
            // does not read; margins belong in the constructor here.
            'margin_left' => 18,
            'margin_right' => 18,
            'margin_top' => 16,
            'margin_bottom' => 18,
            'margin_footer' => 8,

            'fontDir' => array_merge($fontDirs, [public_path('fonts/khmer')]),
            'fontdata' => $fontData + [
                self::BODY => ['R' => 'KhmerOSSiemreap.ttf', 'useOTL' => 0xFF],
                self::TITLE => ['R' => 'KhmerOSMuolLight.ttf', 'useOTL' => 0xFF],
            ],
            'default_font' => self::BODY,
            'default_font_size' => 11,
        ]);
    }

    /**
     * mPDF needs a writable scratch dir for its font metric cache. Kept out of
     * the public disk; created on demand so a fresh clone / CI checkout works
     * without a manual mkdir.
     */
    private function tempDir(): string
    {
        $dir = storage_path('app/mpdf');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, recursive: true);
        }

        return $dir;
    }
}
