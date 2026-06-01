{{--
    Khmer web fonts for printable documents / PDFs.

    - Body text:        Khmer OS Siemreap  (full Khmer + Latin glyph coverage, incl. the ៛ riel sign)
    - Headings / titles: Khmer OS Muol Light (traditional Khmer titling face)

    Two render paths consume these views:
      * Browser print (window.print)  → needs a web URL  → asset()
      * Dompdf (\PDF::loadView)        → needs a local path → public_path() (within chroot=base_path)
    The controller passes $forPdf=true on the Dompdf path; it defaults to false.
--}}
@php
    $forPdf = $forPdf ?? false;
    $khmerFont = fn ($file) => $forPdf
        ? public_path('fonts/khmer/'.$file)
        : asset('fonts/khmer/'.$file);
@endphp
<style>
    @font-face {
        font-family: 'Khmer OS Siemreap';
        font-style: normal;
        font-weight: normal;
        src: url("{{ $khmerFont('KhmerOSSiemreap.ttf') }}") format('truetype');
    }
    @font-face {
        font-family: 'Khmer OS Muol Light';
        font-style: normal;
        font-weight: normal;
        src: url("{{ $khmerFont('KhmerOSMuolLight.ttf') }}") format('truetype');
    }
    body {
        font-family: 'Khmer OS Siemreap', 'DejaVu Sans', Arial, Helvetica, sans-serif !important;
    }
    h1, h2, h3,
    .bill-header h1, .header h1 {
        font-family: 'Khmer OS Muol Light', 'Khmer OS Siemreap', 'DejaVu Sans', sans-serif !important;
    }
</style>
