{{--
    Rental contract — A4 portrait, Khmer.

    The wording, article numbering, layout and signature block reproduce the
    landlord's own printed form (កិច្ចសន្យាជួលបន្ទប់) verbatim. It is a legal
    document TEMPLATE, so the Khmer text lives inline here on purpose — it is
    document content, not translatable app chrome, and must not drift.

    NOTE ON NUMBERING: the source form jumps ប្រការ២ → ប្រការ៤ (there is no
    article 3). That is reproduced as-is deliberately so the generated contract
    matches the paper one article-for-article. Do not "fix" the sequence.

    Rendered two ways from ContractController / ContractGenerator:
      * mPDF    (stored PDF, preview, download)  → $forPdf = true  (public_path fonts)
      * Browser (the Print action)               → $forPdf = false (asset() fonts),
                                                   $autoPrint = true opens the dialog

    Vars: $rental $tenant $apartment $floor $property $landlord[] $rates[]
          $contractNumber $generatedAt $forPdf $autoPrint

    $landlord and $rates are both resolved in ContractGenerator, not here: the
    owner block comes from Settings (falling back to the company block) and each
    rate is the lease's own price if it has one, else the account default from
    Settings, else null → a dotted fill-in line.
--}}
@php
    use Illuminate\Support\Carbon;

    $forPdf = $forPdf ?? true;
    $autoPrint = $autoPrint ?? false;

    // The paper form is filled in by hand where data is missing, so every value
    // falls back to a dotted rule of roughly the width the original leaves.
    // A filled value is padded with literal spaces, not CSS — mPDF ignores
    // horizontal padding on inline elements, so a span just gives you
    // "ឈ្មោះចាន់ សុភាភេទ" with the value welded to the Khmer either side.
    //
    // The pad is an ordinary space, NOT &nbsp;. Khmer is written without word
    // spaces, so a Khmer label plus an &nbsp;-welded value is one unbreakable
    // run to the line breaker: with a long owner name or address the whole
    // "label + value + next label" run overflowed the A4 text column, and
    // justification blew the remaining spaces open to compensate. An ordinary
    // space gives the breaker a legal break either side of every value and
    // collapses at a line end, so nothing is left dangling.
    //
    // Dotted fills are nowrap for the same reason in reverse: a bare run of
    // dots is breakable anywhere, so an empty field used to split across two
    // lines ("......" alone at the start of the next line). Kept whole, the
    // line simply wraps before the fill.
    // These emit markup, so call sites use {!! !!} and escape here.
    $dots = fn (int $n = 20) => '<span style="white-space: nowrap">'.str_repeat('.', $n).'</span>';
    $val = fn ($v, int $n = 20) => filled($v)
        ? ' '.e($v).' '
        : $dots($n);
    // nowrap because mPDF will otherwise break a line after the decimal point
    // and print "$2." at the end of one line and "00" at the start of the next.
    $price = fn ($v, int $n = 12) => ($v !== null && (float) $v > 0)
        ? ' <span style="white-space: nowrap">'.e(money($v)).'</span> '
        : $dots($n);

    $genderLabels = ['male' => 'ប្រុស', 'female' => 'ស្រី', 'other' => 'ផ្សេងៗ'];
    $genderVal = fn (?string $g, int $n = 8) => $g
        ? ' '.e($genderLabels[$g] ?? $g).' '
        : $dots($n);

    // Khmer has no inter-word space, so mPDF's line breaker will split a Khmer
    // run at any cluster boundary when it needs room — which chopped labels
    // mid-word ("កាន់អត្តសញ្ញាណ / ប័ណ្ណលេខ", "តម្លៃសំ / រាម"). Wrapping the
    // short fixed labels keeps each one whole; they are far narrower than the
    // text column, so this can never push a line past the margin.
    $kw = fn (string $s) => '<span style="white-space: nowrap">'.$s.'</span>';

    $start = $rental->start_date ? Carbon::parse($rental->start_date) : null;
    $genDate = Carbon::parse($generatedAt);
@endphp
<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <title>កិច្ចសន្យាជួលបន្ទប់ — {{ $contractNumber }}</title>
    <style>
        /* Page margins live in KhmerPdf::make() — mPDF ignores @page. */
        body {
            font-family: khmerossiemreap, 'Khmer OS Siemreap', sans-serif;
            font-size: 11pt;
            line-height: 1.55;
            color: #000;
        }

        .header { text-align: center; }
        .header .country,
        .header .motto {
            font-family: khmerosmuol, 'Khmer OS Muol Light', serif;
            font-size: 13pt;
            line-height: 1.35;
        }
        .rule { width: 70px; border-top: 1px solid #000; margin: 6px auto 14px; }

        .title {
            font-family: khmerosmuol, 'Khmer OS Muol Light', serif;
            font-size: 13pt;
            text-align: center;
            text-decoration: underline;
            margin-bottom: 16px;
        }

        .parties { text-align: justify; }
        .parties p { margin: 0 0 4px; }
        .and { text-align: center; font-weight: bold; text-decoration: underline; margin: 8px 0; }

        .agreed {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            margin: 16px 0 12px;
        }

        .article { text-align: justify; margin-bottom: 7px; }
        .article .n { font-weight: bold; }

        /* ប្រការ៧ carries this emphasis in the source form. */
        .stress { font-weight: bold; font-style: italic; text-decoration: underline; }

        .made-on { text-align: right; margin-top: 22px; }

        table.signatures { width: 100%; margin-top: 26px; text-align: center; }
        table.signatures td { width: 33.33%; vertical-align: top; }

        .footer { font-size: 7pt; color: #666; border-top: .5px solid #999; padding-top: 2px; }
        .footer td { width: 33.33%; }
        .footer .c { text-align: center; }
        .footer .r { text-align: right; }
    </style>
    {{-- Browser path only: mPDF registers its Khmer faces in KhmerPdf::make(),
         and the partial's `!important` body rule would fight that. --}}
    @unless($forPdf)
        @include('partials.khmer_fonts')
    @endunless
</head>
<body>

@if($forPdf)
    {{-- mPDF page footer (repeats on every page). --}}
    <htmlpagefooter name="contractfooter">
        <table class="footer" width="100%"><tr>
            <td>{{ $contractNumber }}</td>
            <td class="c">{{ $genDate->format('d/m/Y') }}</td>
            <td class="r">{PAGENO} / {nbpg}</td>
        </tr></table>
    </htmlpagefooter>
    <sethtmlpagefooter name="contractfooter" value="on" />
@endif

<div class="header">
    <div class="country">ព្រះរាជាណាចក្រកម្ពុជា</div>
    <div class="motto">ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
    <div class="rule"></div>
</div>

<div class="title">កិច្ចសន្យាជួលបន្ទប់</div>

{{-- Party A — the owner, from Settings → Owner Information --}}
<div class="parties">
    <p>
        {!! $kw('ឈ្មោះ') !!}{!! $val($landlord['name'] ?? null, 26) !!}{!! $kw('ភេទ') !!}{!! $genderVal($landlord['gender'] ?? null, 10) !!}{!! $kw('កាន់អត្តសញ្ញាណប័ណ្ណលេខ') !!}{!! $val($landlord['id_card'] ?? null, 28) !!}
    </p>
    <p>
        {!! $kw('លេខទូរស័ព្ទ') !!}{!! $val($landlord['phone'] ?? null, 16) !!}{!! $kw('ជាម្ចាស់ផ្ទះជួលនៅអាស័យដ្ឋាន') !!} {!! $val($landlord['address'] ?? null, 46) !!}
        {!! $kw('ហៅកាត់ថា') !!} {!! $kw('ភាគី “ក”។') !!}
    </p>

    <div class="and">និង</div>

    <p>
        {!! $kw('អ្នកជួលបន្ទប់ឈ្មោះ') !!}{!! $val($tenant?->name, 22) !!}{!! $kw('ភេទ') !!}{!! $genderVal($tenant?->gender) !!}{!! $kw('កាន់អត្តសញ្ញាណប័ណ្ណលេខ') !!}{!! $val($tenant?->id_card_number, 20) !!}
    </p>
    <p>
        {!! $kw('លេខទូរស័ព្ទ') !!}{!! $val($tenant?->phone, 20) !!} {!! $kw('ហៅកាត់ថា') !!} {!! $kw('ភាគី “ខ” ។') !!}
    </p>
</div>

<div class="agreed">ភាគីទាំងពីរបានព្រមព្រៀងគ្នាដូចខាងក្រោម</div>

<div class="article">
    <span class="n">ប្រការ១៖</span>
    ភាគី“ក” បានយល់ព្រមជួលបន្ទប់ដែលមានលេខ{!! $val($apartment?->apartment_number, 6) !!}ស្ថិតនៅអាស័យដ្ឋានផ្ទះជួលខាងលើទៅឱ្យ ភាគី“ខ”
    {!! $kw('ក្នុងតម្លៃ') !!}{!! $price($rates['rent']) !!}{!! $kw('តម្លៃទឹក') !!}{!! $price($rates['water']) !!}{!! $kw('តម្លៃភ្លើង') !!}{!! $price($rates['electricity']) !!}{!! $kw('តម្លៃចំណតរថយន្ត') !!}{!! $price($rates['parking']) !!}{!! $kw('តម្លៃអុីនធីណេត') !!}{!! $price($rates['internet']) !!}
    {!! $kw('និងតម្លៃសំរាម') !!}{!! $price($rates['garbage']) !!}។
</div>

<div class="article">
    <span class="n">ប្រការ២៖</span>
    ភាគី“ខ” យល់ព្រមជួលបន្ទប់ដែលមានលេខ និងតម្លៃយល់ព្រមក្នុងប្រការ១ {!! $kw('ដោយគិតពីថ្ងៃទី') !!}{!! $val($start?->format('d'), 6) !!}{!! $kw('ខែ') !!}{!! $val($start?->format('m'), 8) !!}{!! $kw('ឆ្នាំ') !!}{!! $val($start?->format('Y'), 8) !!}
    ដល់ថ្ងៃដែលភាគីទាំងពីរយល់ព្រមបញ្ចប់កិច្ចសន្យា និងតម្រូវឱ្យបង់ប្រាក់កក់ថ្លៃបន្ទប់ចំនួន ០១ខែ
    ដើម្បីជាការធានាលើការខូតខាតផ្សេងៗរបស់ម្ចាស់ផ្ទះ។
</div>

<div class="article">
    <span class="n">ប្រការ៤៖</span>
    ភាគី“ខ” យល់ព្រមបង់ថ្លៃបន្ទប់ស្នាក់នៅប្រចាំខែក្នុងតម្លៃយល់ព្រម ថ្លៃចំណត ថ្លៃទឹក អគ្គិសនី សំរាម នៅខែបន្តបន្ទប់
    {!! $kw('រៀងរាល់ថ្ងៃទី') !!}{!! $val($rental->payment_due_day, 6) !!} {!! $kw('នៃខែនីមួយៗ') !!}អំឡុងពេលស្នាក់នៅរហូតដល់កិច្ចសន្យាត្រូវបានបញ្ចប់។
</div>

<div class="article">
    <span class="n">ប្រការ៥៖</span>
    ភាគី“ខ” យល់ព្រមទទួលការផាកពិន័យចំនួន {!! $price($rates['late']) !!} ក្នុងករណីភាគី“ខ” ខកខានបង់ប្រាក់ថ្លៃបន្ទប់ ចំណត និងថ្លៃផ្សេងៗ
    ក្រោយរយៈពេល ០៣ថ្ងៃដែលបានព្រមព្រៀងនៅក្នុងកិច្ចសន្យានេះ។
</div>

<div class="article">
    <span class="n">ប្រការ ៦៖</span>
    ភាគី“ខ” យល់ព្រម ប្រើប្រាស់បន្ទប់ខាងលើ សម្រាប់តែស្នាក់នៅប៉ុណ្ណោះ។ ភាគី“ខ” មិនអាចប្រើប្រាស់បន្ទប់ធ្វើជា
    កន្លែងរកស៊ី-លក់ដូរ ការិយាល័យ ឃ្លាំងដាក់ទំនិញ។ល។ និងមិនអាចប្រើប្រាស់សម្រាប់ប្រព្រឹត្តអំពើខុសច្បាប់ មាន
    ដូចជាប្រើប្រាស់ ផលិត ឬរក្សាទុកគ្រឿងញៀន ទំនិញឬរបស់ខុសច្បាប់ ការជួញដូរមនុស្ស ការធ្វើអាជីវកម្មផ្លូវភេទ
    ល្បែងស៊ីសងជាដើម។ ក្នុងករណីដែល ភាគី“ខ” បំពានលើអ្វីដែលបានរៀបរាប់ក្នុងកិច្ចសន្យា ភាគី“ក” មានសិទ្ធិ
    បញ្ឈប់ការជួលបន្ទប់ទៅឲ្យ ភាគី“ខ” ភ្លាមៗ និងជូនដំណឹងទៅអាជ្ញាធរមានសមត្ថកិច្ចដោយមិនចាំបាច់ជូនដំណឹង
    ដល់ ភាគី“ខ” ជាមុន ហើយ ភាគី“ខ” ក៏មិនអាចទាមទារសំណង ឬប្រាក់កក់អ្វីឡើយ។
</div>

<div class="article">
    <span class="n">ប្រការ៧៖</span>
    ក្នុងអំឡុងស្នាក់នៅ ភាគី “ខ” យល់ព្រមគ្រប់គ្រង និងថែរក្សាបន្ទប់ ឧបករណ៍ និងសម្ភារៈដែលមាននៅក្នុងបន្ទប់
    ដោយសុចរិតភាព <span class="stress">និងមិនត្រូវស្វានជញ្ជាំង វាយដែក លាបពណ៌ ផ្លាស់ប្តូរប្រព័ន្ធទឹកភ្លើង បិទស្ទីកគ័រ អុជធុប ឬបិទ
    រូបភាពផ្សេងៗលើជញ្ជាំង</span>។ ក្នុងករណីមានការខូចខាត ភាគី“ខ” ត្រូវជូនដំណឹងដល់ ភាគី“ក” និងត្រូវចេញថ្លៃជួស
    ជុល ឬសងការខូចខាតទាំងស្រុងដល់ ភាគី“ក” ឬត្រូវយកប្រាក់កក់ដើម្បីជាសំណងទូទាត់។
</div>

<div class="article">
    <span class="n">ប្រការ៨៖</span>
    ភាគី“ខ” យល់ព្រម មិនជួលបន្ទប់បន្តទៅឲ្យអ្នកផ្សេងឡើយ ក្នុងករណីមិនគោរពភាគី“ក” មានសិទ្ធិបញ្ចប់ការស្នាក់
    នៅភ្លាមៗ និងមិនផ្តល់សំណង ឬប្រាក់កក់អ្វីទាំងអស់។
</div>

<div class="article">
    <span class="n">ប្រការ៩៖</span>
    ភាគី“ខ” យល់ព្រម មិនអនុញ្ញាត្តិឱ្យអ្នកផ្សេង ក្រៅពីសមាជិកដូចដែលបានរៀបរាប់ឈ្មោះក្នុងកិច្ចសន្យា មកស្នាក់នៅឬ
    ចេញចូលបរិវេណផ្ទះឡើយ។ ក្នុងករណីភាគី“ខ” មានបំណងចង់ឲ្យអ្នកផ្សេង ក្រៅពីសមាជិកដែលបានរៀបរាប់ឈ្មោះ
    ក្នុងកិច្ចសន្យា មកស្នាក់នៅ ភាគី“ខ” ត្រូវជូនដំណឹងទៅ ភាគី“ក” ជាមុនសិន ហើយលុះណាតែ ភាគី“ក” យល់ព្រម
    ទើប ភាគី“ខ” អាចឲ្យអ្នកក្រៅសមាជិកដែលបានរៀបរាប់ឈ្មោះក្នុងកិច្ចសន្យា ស្នាក់នៅបាន។ ក្នុងករណីនេះ ភាគី“ក”
    សូមរក្សាសិទ្ធិក្នុងការផ្តល់ការអនុញ្ញាតឬមិនអនុញ្ញាត តាមសំណូមពររបស់ ភាគី“ខ”។
</div>

<div class="article">
    <span class="n">ប្រការ១០៖</span>
    ក្នុងអំឡុងការស្នាក់នៅ ភាគី“ខ” យល់ព្រមមើលថែរក្សាទ្រព្យសម្បត្តិផ្ទាល់ខ្លួន និងទទួលការខុសត្រូវលើការបាត់បង់
    ដោយប្រការណាមួយដោយខ្លួនឯង។ ក្នុងករណីខូចខាត ឬបាត់បង់ដោយប្រការណាមួយ ភាគី“ក” មិនទទួលខុស
    ត្រូវឡើយ។
</div>

<div class="article">
    <span class="n">ប្រការ១១៖</span>
    ក្នុងអំឡុងស្នាក់នៅ ភាគី“ខ”ត្រូវគោរពបទបញ្ជារផ្ទៃក្នុងរបស់ផ្ទះជួល និងយល់ព្រមមិនបង្កើតកម្មវិធីជួបជុំ ឬជប់
    លៀងផ្សេងៗ ដែលអាចបង្កជាសំឡេងឬការរំខានដល់អ្នកស្នាក់នៅឯទៀត។ ក្នុងករណី ភាគី“ខ” ចង់បង្កើតកម្មវិធីអ្វី
    មួយ ភាគី“ខ” ត្រូវសុំអនុញ្ញាតពី ភាគី“ក” ជាមុនសិន។ ក្នុងករណីនេះ ភាគី “ក” សូមរក្សាសិទ្ធិក្នុងការផ្តល់ការ
    អនុញ្ញាតឬមិនអនុញ្ញាត តាមសំណូមពររបស់ ភាគី“ខ”។ ក្នុងករណីភាគី“ខ” មិនបានគោរពតាមការណែនាំនេះ ភាគី“ក”
    មានសិទ្ធិបញ្ឈប់ការស្នាក់នៅ និងមិនមានការផ្តល់សំណងអ្វីទាំងអស់។
</div>

<div class="article">
    <span class="n">ប្រការ១២៖</span>
    ភាគីទាំងសងខាង បានអាន បានយល់ និងយល់ព្រមអនុវត្តតាមខ្លឹមសារដែលបានរៀបរាប់ក្នុងកិច្ចព្រមព្រៀងនេះ។
</div>

<div class="article">
    <span class="n">ប្រការ១៣៖</span>
    ក្នុងករណីដែលមានការបំពានលើប្រការណាមួយ ដែលបានរៀបរាប់ក្នុងកិច្ចព្រមព្រៀង នោះ ភាគី“ក” អាចរំលាយ
    កិច្ចសន្យា និងទាមទារឲ្យ ភាគី“ខ” ត្រូវចាកចេញពីបន្ទប់ជួលដែលមានលេខដូចខាងលើ បន្ទាប់ពីរំលាយកិច្ចសន្យា
    ហើយ ភាគី“ខ” ត្រូវសងសំណងខូចខាតដែលកើតមាន (ប្រសិនបើមាន) និងយល់ព្រមបោះបង់ប្រាក់កក់ ដែល
    មាននៅក្នុងប្រការ ២។
</div>

<div class="made-on">
    ធ្វើនៅរាជធានីភ្នំពេញថ្ងៃទី {!! $dots(6) !!} ខែ {!! $dots(6) !!} ឆ្នាំ{{ $genDate->format('Y') }}
</div>

<table class="signatures">
    <tr>
        <td>ស្នាមមេដៃភាគី“ខ”</td>
        <td>ស្នាមមេដៃភាគី“ក”</td>
        <td>ស្នាមមេដៃសាក្សី</td>
    </tr>
    {{-- Blank room to sign/thumbprint in. A spacer row, because mPDF drops
         margin-top on content inside a td. --}}
    <tr><td colspan="3" style="height: 60px;"></td></tr>
    <tr>
        <td>ឈ្មោះ</td>
        <td>ឈ្មោះ</td>
        <td>ឈ្មោះ</td>
    </tr>
</table>

@if($autoPrint)
    <script>window.addEventListener('load', function () { window.print(); });</script>
@endif
</body>
</html>
