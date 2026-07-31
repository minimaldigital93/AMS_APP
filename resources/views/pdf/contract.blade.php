{{--
    Rental contract — A4 portrait, Khmer.

    The wording, article numbering, layout and signature block reproduce the
    landlord's own printed form (កិច្ចសន្យាជួលបន្ទប់) verbatim. It is a legal
    document TEMPLATE, so the Khmer text lives inline here on purpose — it is
    document content, not translatable app chrome, and must not drift.

    NOTE ON NUMBERING: the source form jumps ប្រការ២ → ប្រការ៤ (there is no
    article 3). That is reproduced as-is deliberately so the generated contract
    matches the paper one article-for-article. Do not "fix" the sequence.

    This is rendered by mPDF (ContractGenerator → KhmerPdf, $forPdf = true), which
    both shapes AND justifies Khmer. That stored PDF is what every action shows —
    preview, download, and the on-screen "view + print" page (pdf.contract_viewer),
    which renders the PDF with pdf.js and prints it. Do NOT print this template as browser HTML:
    no browser can justify spaceless Khmer via CSS, so the paragraphs come out
    ragged. The $forPdf = false / $autoPrint browser path is kept only as a
    fallback and is no longer wired to any route.

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
    // Every value merged in from the database goes through this, so the
    // filled-in data reads apart from the pre-printed wording — exactly like
    // handwriting on the paper form. The pad spaces stay OUTSIDE the span for
    // the line-breaking reason above; putting them inside welds the value to
    // the Khmer either side again.
    $bold = fn (string $html, bool $nowrap = false) => ' <span class="v"'
        .($nowrap ? ' style="white-space: nowrap"' : '').'>'.$html.'</span> ';
    $val = fn ($v, int $n = 20) => filled($v)
        ? $bold(e($v))
        : $dots($n);
    // nowrap because mPDF will otherwise break a line after the decimal point
    // and print "$2." at the end of one line and "00" at the start of the next.
    $price = fn ($v, int $n = 12) => ($v !== null && (float) $v > 0)
        ? $bold(e(money($v)), true)
        : $dots($n);
    // Late-fee penalty (ប្រការ៥) is a percentage of the rent per overdue day,
    // not a money amount. nowrap so mPDF keeps "3.5%" on one line, and trailing
    // zeros are trimmed so 2.00 → "2" and 3.50 → "3.5".
    $pct = fn ($v, int $n = 6) => ($v !== null && (float) $v > 0)
        ? $bold(e(rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.')).'%', true)
        : $dots($n);

    $genderLabels = ['male' => 'ប្រុស', 'female' => 'ស្រី', 'other' => 'ផ្សេងៗ'];
    $genderVal = fn (?string $g, int $n = 8) => $g
        ? $bold(e($genderLabels[$g] ?? $g))
        : $dots($n);

    // Khmer has no inter-word space, so mPDF's line breaker will split a Khmer
    // run at any cluster boundary when it needs room — which chopped labels
    // mid-word ("កាន់អត្តសញ្ញាណ / ប័ណ្ណលេខ", "តម្លៃសំ / រាម"). Wrapping the
    // short fixed labels keeps each one whole; they are far narrower than the
    // text column, so this can never push a line past the margin.
    $kw = fn (string $s) => '<span style="white-space: nowrap">'.$s.'</span>';

    $start = $rental->start_date ? Carbon::parse($rental->start_date) : null;
    $genDate = Carbon::parse($generatedAt);

    // Khmer numerals + month names for the "made on" date line.
    // NOTE: the array form of strtr — the three-arg string form maps byte-for-byte
    // and would splice the 3-byte Khmer digits into invalid UTF-8, which then hangs
    // mPDF's purify_utf8() sanitiser for 30s+.
    $khNum = fn ($v) => strtr((string) $v, [
        '0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤',
        '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩',
    ]);
    $khMonths = [
        1 => 'មករា', 2 => 'កុម្ភៈ', 3 => 'មីនា', 4 => 'មេសា',
        5 => 'ឧសភា', 6 => 'មិថុនា', 7 => 'កក្កដា', 8 => 'សីហា',
        9 => 'កញ្ញា', 10 => 'តុលា', 11 => 'វិច្ឆិកា', 12 => 'ធ្នូ',
    ];

    // Khmer-numeral / Khmer-month fills for the lease dates, keeping $val's
    // dotted-blank fallback when no start date is set. The month name is nowrap
    // for the same reason the fixed labels are: mPDF happily split "សីហា" into
    // "សី" / "ហា" across a line end.
    $khDay = fn (?Carbon $d, int $n = 6) => $d ? $bold($khNum($d->format('d'))) : $dots($n);
    $khMonthName = fn (?Carbon $d, int $n = 8) => $d ? $bold($khMonths[(int) $d->format('n')], true) : $dots($n);
    $khYear = fn (?Carbon $d, int $n = 8) => $d ? $bold($khNum($d->format('Y'))) : $dots($n);

    // Rent due-day (ប្រការ៤). ContractGenerator resolves it — the lease's own
    // payment_due_day, else the move-in day — and this default only keeps the
    // template renderable on its own.
    $dueDay = $dueDay ?? $rental->payment_due_day ?? $start?->day;

    // Fixed lease term (ប្រការ២). When a 3/6/12-month term was agreed, state the
    // duration and its end date; an open-ended tenancy prints nothing extra.
    $termMonths = $termMonths ?? null;
    $termEnd = $termEnd ?? null;
    $termEndCarbon = $termEnd ? Carbon::parse($termEnd) : null;

    // ប្រការ១ lists the monthly charges. A utility whose resolved rate is null —
    // neither the lease nor the account default sets a positive price, i.e. it is
    // unused or explicitly set to 0 — is dropped entirely, label and all, rather
    // than printed as a blank fill-in line. Rent always prints (dotted if unset).
    // "និង" (and) is welded onto whichever utility ends up last so the sentence
    // still reads, and if every utility is hidden the line is just the rent.
    $utilities = array_filter([
        'តម្លៃទឹក' => $rates['water'],
        'តម្លៃភ្លើង' => $rates['electricity'],
        'តម្លៃចំណតរថយន្ត' => $rates['parking'],
        'តម្លៃអុីនធីណេត' => $rates['internet'],
        'តម្លៃសំរាម' => $rates['garbage'],
    ], fn ($rate) => $rate !== null);

    $renderUtilities = function () use ($utilities, $kw, $price) {
        $out = '';
        $lastLabel = array_key_last($utilities);
        foreach ($utilities as $label => $rate) {
            $prefix = $label === $lastLabel ? 'និង' : '';
            $out .= $kw($prefix.$label).$price($rate);
        }

        return $out;
    };
@endphp
<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <title>កិច្ចសន្យាជួលបន្ទប់ — {{ $contractNumber }}</title>
    <style>
        /* Page margins live in KhmerPdf::make() — mPDF ignores @page. */
        /* Justified by default: the paper form sets every paragraph flush to
           both margins, so the right edge lines up down the whole page. The
           centred/right blocks below each override it explicitly. mPDF cannot
           stretch a paragraph's LAST line (no text-align-last support), so those
           stay ragged — that matches the printed form. */
        /* Roomy leading throughout — the paper form is airy and the stacked
           Khmer subscripts need the headroom. Note this is line spacing only:
           NEVER add letter-spacing here. mPDF treats a fixed letter-spacing as
           "fixedlSpacing", which switches justification to word-spacing only —
           and Khmer lines often contain no spaces at all, so those lines would
           stop justifying entirely and the right edge would fall apart. */
        body {
            font-family: khmerossiemreap, 'Khmer OS Siemreap', sans-serif;
            font-size: 11pt;
            line-height: 1.75;
            color: #000;
            text-align: justify;
        }

        /* Every value merged in from the database — names, ID numbers, prices,
           room numbers, dates — so the filled-in data stands out from the
           pre-printed wording. Khmer OS Siemreap ships no bold face, so mPDF
           synthesises one, the same faux-bold the headings already use. */
        .v { font-weight: bold; }

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
            /* Breathing room between the national motto and the contract title,
               as on the printed form. */
            margin: 30px 0 18px;
        }

        .parties { text-align: justify; }
        /* One-tab (12.7mm) first-line indent on each party paragraph — wrapped
           lines return to the margin, so "ឈ្មោះ" / "អ្នកជួលបន្ទប់ឈ្មោះ" both open
           at the same tab stop. */
        .parties p { margin: 0 0 10px; text-indent: 12.7mm; }
        .and { text-align: center; font-weight: bold; text-decoration: underline; margin: 8px 0; }

        .agreed {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            margin: 16px 0 12px;
        }

        /* Articles are a two-column table, not a paragraph with an inline label:
           the paper form sets every article body at the same tab stop and hangs
           the wrapped lines to it, so "ប្រការ១៖" and "ប្រការ១៣៖" must start
           their text at the same column despite the different label widths.
           A table cell does that reliably in mPDF; negative text-indent does not. */
        /* No page-break-inside: avoid here — mPDF answers that by shrinking the
           whole article to fit the remaining page (article ១១ came out squashed
           in a smaller type than the rest). Letting a long article split across
           the page break is both correct and what the divs did before. */
        table.article { width: 100%; margin-bottom: 11px; border-collapse: collapse; }
        /* line-height is repeated here on purpose: mPDF does not inherit the
           body value into table cells, so without it the article bodies stay
           tight while everything around them opens up. */
        table.article td { vertical-align: top; padding: 0; line-height: 1.75; }
        /* Wide enough for the longest label (ប្រការ១៣៖) plus the gap after it. */
        table.article td.n { width: 20mm; font-weight: bold; white-space: nowrap; }
        table.article td.b { text-align: justify; }

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
            <td class="c">{{ $khNum($genDate->format('d/m/Y')) }}</td>
            <td class="r">{PAGENO} / {nbpg}</td>
        </tr></table>
    </htmlpagefooter>
    <sethtmlpagefooter name="contractfooter" value="on" />
@endif

<div class="header">
    <div class="country">ព្រះរាជាណាចក្រកម្ពុជា</div>
    <div class="motto">ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
</div>

<div class="title">កិច្ចសន្យាជួលបន្ទប់</div>

{{-- Party A — the owner, from Settings → Owner Information --}}
<div class="parties">
    <p>
        {!! $kw('ឈ្មោះ') !!}{!! $val($landlord['name'] ?? null, 26) !!}{!! $kw('ភេទ') !!}{!! $genderVal($landlord['gender'] ?? null, 10) !!}{!! $kw('កាន់អត្តសញ្ញាណប័ណ្ណលេខ') !!}{!! $val($landlord['id_card'] ?? null, 28) !!}{!! $kw('លេខទូរស័ព្ទ') !!}{!! $val($landlord['phone'] ?? null, 16) !!}
        {!! $kw('ជាម្ចាស់ផ្ទះជួលនៅអាស័យដ្ឋាន') !!}
        {!! $val($landlord['address'] ?? null, 46) !!}
        {!! $kw('ហៅកាត់ថា') !!} {!! $kw('ភាគី “ក”។') !!}
    </p>

    <div class="and">និង</div>

    <p>
        {!! $kw('អ្នកជួលបន្ទប់ឈ្មោះ') !!}{!! $val($tenant?->name, 22) !!}{!! $kw('ភេទ') !!}{!! $genderVal($tenant?->gender) !!}{!! $kw('កាន់អត្តសញ្ញាណប័ណ្ណលេខ') !!}{!! $val($tenant?->id_card_number, 20) !!}
         {!! $kw('លេខទូរស័ព្ទ') !!}{!! $val($tenant?->phone, 20) !!} ហៅកាត់ថាភាគី “ខ”។
    </p>
</div>

<div class="agreed">ភាគីទាំងពីរបានព្រមព្រៀងគ្នាដូចខាងក្រោម</div>

<table class="article" autosize="0"><tr>
    <td class="n">ប្រការ១៖</td>
    <td class="b">
    ភាគី“ក” បានយល់ព្រមជួលបន្ទប់ដែលមានលេខ{!! $val($apartment?->apartment_number, 6) !!}ស្ថិតនៅអាស័យដ្ឋានផ្ទះជួលខាងលើទៅឱ្យ ភាគី“ខ”
    ក្នុងតម្លៃ {!! $price($rates['rent']) !!}{!! $renderUtilities() !!}។
    </td>
</tr></table>

<table class="article" autosize="0"><tr>
    <td class="n">ប្រការ២៖</td>
    <td class="b">
    ភាគី“ខ” យល់ព្រមជួលបន្ទប់ដែលមានលេខ និងតម្លៃយល់ព្រមក្នុងប្រការ១ {!! $kw('ដោយគិតពីថ្ងៃទី') !!}{!! $khDay($start) !!}{!! $kw('ខែ') !!}{!! $khMonthName($start) !!}{!! $kw('ឆ្នាំ') !!}{!! $khYear($start) !!}@if($termMonths){!! $kw('ក្នុងរយៈពេល') !!}{!! $bold($khNum($termMonths)) !!}{!! $kw('ខែ គឺរហូតដល់ថ្ងៃទី') !!}{!! $khDay($termEndCarbon) !!}{!! $kw('ខែ') !!}{!! $khMonthName($termEndCarbon) !!}{!! $kw('ឆ្នាំ') !!}{!! $khYear($termEndCarbon) !!}@else
    ដល់ថ្ងៃដែលភាគីទាំងពីរយល់ព្រមបញ្ចប់កិច្ចសន្យា@endif និងតម្រូវឱ្យបង់ប្រាក់កក់ថ្លៃបន្ទប់ចំនួន ០១ខែ
    ដើម្បីជាការធានាលើការខូតខាតផ្សេងៗរបស់ម្ចាស់ផ្ទះ។
    </td>
</tr></table>

<table class="article" autosize="0"><tr>
    <td class="n">ប្រការ៤៖</td>
    <td class="b">
    ភាគី“ខ” យល់ព្រមបង់ថ្លៃបន្ទប់ស្នាក់នៅប្រចាំខែក្នុងតម្លៃយល់ព្រម ថ្លៃចំណត ថ្លៃទឹក អគ្គិសនី សំរាម នៅខែបន្តបន្ទប់
    {{-- No space between the fill and នៃខែនីមួយៗ: $bold() already pads the value
         with one on each side, so a literal space here printed a double gap that
         justification then widened further. --}}
    {!! $kw('រៀងរាល់ថ្ងៃទី') !!}{!! $dueDay ? $bold($khNum($dueDay)) : $dots(6).' ' !!}{!! $kw('នៃខែនីមួយៗ') !!}អំឡុងពេលស្នាក់នៅរហូតដល់កិច្ចសន្យាត្រូវបានបញ្ចប់។
    </td>
</tr></table>

<table class="article" autosize="0"><tr>
    <td class="n">ប្រការ៥៖</td>
    <td class="b">
    ភាគី“ខ” យល់ព្រមទទួលការផាកពិន័យចំនួន {!! $pct($rates['late_percent']) !!}{!! $kw('នៃថ្លៃឈ្នួលក្នុងមួយថ្ងៃ') !!} ក្នុងករណីភាគី“ខ” ខកខានបង់ប្រាក់ថ្លៃបន្ទប់ ចំណត និងថ្លៃផ្សេងៗ
    ក្រោយរយៈពេល ០៣ថ្ងៃដែលបានព្រមព្រៀងនៅក្នុងកិច្ចសន្យានេះ។
    </td>
</tr></table>

<table class="article" autosize="0"><tr>
    <td class="n">ប្រការ៦៖</td>
    <td class="b">
    ភាគី“ខ” យល់ព្រម ប្រើប្រាស់បន្ទប់ខាងលើ សម្រាប់តែស្នាក់នៅប៉ុណ្ណោះ។ ភាគី“ខ” មិនអាចប្រើប្រាស់បន្ទប់ធ្វើជា
    កន្លែងរកស៊ី-លក់ដូរ ការិយាល័យ ឃ្លាំងដាក់ទំនិញ។ល។ និងមិនអាចប្រើប្រាស់សម្រាប់ប្រព្រឹត្តអំពើខុសច្បាប់ មាន
    ដូចជាប្រើប្រាស់ ផលិត ឬរក្សាទុកគ្រឿងញៀន ទំនិញឬរបស់ខុសច្បាប់ ការជួញដូរមនុស្ស ការធ្វើអាជីវកម្មផ្លូវភេទ
    ល្បែងស៊ីសងជាដើម។ ក្នុងករណីដែល ភាគី“ខ” បំពានលើអ្វីដែលបានរៀបរាប់ក្នុងកិច្ចសន្យា ភាគី“ក” មានសិទ្ធិ
    បញ្ឈប់ការជួលបន្ទប់ទៅឲ្យ ភាគី“ខ” ភ្លាមៗ និងជូនដំណឹងទៅអាជ្ញាធរមានសមត្ថកិច្ចដោយមិនចាំបាច់ជូនដំណឹង
    ដល់ ភាគី“ខ” ជាមុន ហើយ ភាគី“ខ” ក៏មិនអាចទាមទារសំណង ឬប្រាក់កក់អ្វីឡើយ។
    </td>
</tr></table>

<table class="article" autosize="0"><tr>
    <td class="n">ប្រការ៧៖</td>
    <td class="b">
    ក្នុងអំឡុងស្នាក់នៅ ភាគី “ខ” យល់ព្រមគ្រប់គ្រង និងថែរក្សាបន្ទប់ ឧបករណ៍ និងសម្ភារៈដែលមាននៅក្នុងបន្ទប់
    ដោយសុចរិតភាព <span class="stress">និងមិនត្រូវស្វានជញ្ជាំង វាយដែក លាបពណ៌ ផ្លាស់ប្តូរប្រព័ន្ធទឹកភ្លើង បិទស្ទីកគ័រ អុជធុប ឬបិទ
    រូបភាពផ្សេងៗលើជញ្ជាំង</span>។ ក្នុងករណីមានការខូចខាត ភាគី“ខ” ត្រូវជូនដំណឹងដល់ ភាគី“ក” និងត្រូវចេញថ្លៃជួស
    ជុល ឬសងការខូចខាតទាំងស្រុងដល់ ភាគី“ក” ឬត្រូវយកប្រាក់កក់ដើម្បីជាសំណងទូទាត់។
    </td>
</tr></table>

<table class="article" autosize="0"><tr>
    <td class="n">ប្រការ៨៖</td>
    <td class="b">
    ភាគី“ខ” យល់ព្រម មិនជួលបន្ទប់បន្តទៅឲ្យអ្នកផ្សេងឡើយ ក្នុងករណីមិនគោរពភាគី“ក” មានសិទ្ធិបញ្ចប់ការស្នាក់
    នៅភ្លាមៗ និងមិនផ្តល់សំណង ឬប្រាក់កក់អ្វីទាំងអស់។
    </td>
</tr></table>

<table class="article" autosize="0"><tr>
    <td class="n">ប្រការ៩៖</td>
    <td class="b">
    ភាគី“ខ” យល់ព្រម មិនអនុញ្ញាត្តិឱ្យអ្នកផ្សេង ក្រៅពីសមាជិកដូចដែលបានរៀបរាប់ឈ្មោះក្នុងកិច្ចសន្យា មកស្នាក់នៅឬ
    ចេញចូលបរិវេណផ្ទះឡើយ។ ក្នុងករណីភាគី“ខ” មានបំណងចង់ឲ្យអ្នកផ្សេង ក្រៅពីសមាជិកដែលបានរៀបរាប់ឈ្មោះ
    ក្នុងកិច្ចសន្យា មកស្នាក់នៅ ភាគី“ខ” ត្រូវជូនដំណឹងទៅ ភាគី“ក” ជាមុនសិន ហើយលុះណាតែ ភាគី“ក” យល់ព្រម
    ទើប ភាគី“ខ” អាចឲ្យអ្នកក្រៅសមាជិកដែលបានរៀបរាប់ឈ្មោះក្នុងកិច្ចសន្យា ស្នាក់នៅបាន។ ក្នុងករណីនេះ ភាគី“ក”
    សូមរក្សាសិទ្ធិក្នុងការផ្តល់ការអនុញ្ញាតឬមិនអនុញ្ញាត តាមសំណូមពររបស់ ភាគី“ខ”។
    </td>
</tr></table>

<table class="article" autosize="0"><tr>
    <td class="n">ប្រការ១០៖</td>
    <td class="b">
    ក្នុងអំឡុងការស្នាក់នៅ ភាគី“ខ” យល់ព្រមមើលថែរក្សាទ្រព្យសម្បត្តិផ្ទាល់ខ្លួន និងទទួលការខុសត្រូវលើការបាត់បង់
    ដោយប្រការណាមួយដោយខ្លួនឯង។ ក្នុងករណីខូចខាត ឬបាត់បង់ដោយប្រការណាមួយ ភាគី“ក” មិនទទួលខុស
    ត្រូវឡើយ។
    </td>
</tr></table>

<table class="article" autosize="0"><tr>
    <td class="n">ប្រការ១១៖</td>
    <td class="b">
    ក្នុងអំឡុងស្នាក់នៅ ភាគី“ខ”ត្រូវគោរពបទបញ្ជារផ្ទៃក្នុងរបស់ផ្ទះជួល និងយល់ព្រមមិនបង្កើតកម្មវិធីជួបជុំ ឬជប់
    លៀងផ្សេងៗ ដែលអាចបង្កជាសំឡេងឬការរំខានដល់អ្នកស្នាក់នៅឯទៀត។ ក្នុងករណី ភាគី“ខ” ចង់បង្កើតកម្មវិធីអ្វី
    មួយ ភាគី“ខ” ត្រូវសុំអនុញ្ញាតពី ភាគី“ក” ជាមុនសិន។ ក្នុងករណីនេះ ភាគី “ក” សូមរក្សាសិទ្ធិក្នុងការផ្តល់ការ
    អនុញ្ញាតឬមិនអនុញ្ញាត តាមសំណូមពររបស់ ភាគី“ខ”។ ក្នុងករណីភាគី“ខ” មិនបានគោរពតាមការណែនាំនេះ ភាគី“ក”
    មានសិទ្ធិបញ្ឈប់ការស្នាក់នៅ និងមិនមានការផ្តល់សំណងអ្វីទាំងអស់។
    </td>
</tr></table>

<table class="article" autosize="0"><tr>
    <td class="n">ប្រការ១២៖</td>
    <td class="b">
    ភាគីទាំងសងខាង បានអាន បានយល់ និងយល់ព្រមអនុវត្តតាមខ្លឹមសារដែលបានរៀបរាប់ក្នុងកិច្ចព្រមព្រៀងនេះ។
    </td>
</tr></table>

<table class="article" autosize="0"><tr>
    <td class="n">ប្រការ១៣៖</td>
    <td class="b">
    ក្នុងករណីដែលមានការបំពានលើប្រការណាមួយ ដែលបានរៀបរាប់ក្នុងកិច្ចព្រមព្រៀង នោះ ភាគី“ក” អាចរំលាយ
    កិច្ចសន្យា និងទាមទារឲ្យ ភាគី“ខ” ត្រូវចាកចេញពីបន្ទប់ជួលដែលមានលេខដូចខាងលើ បន្ទាប់ពីរំលាយកិច្ចសន្យា
    ហើយ ភាគី“ខ” ត្រូវសងសំណងខូចខាតដែលកើតមាន (ប្រសិនបើមាន) និងយល់ព្រមបោះបង់ប្រាក់កក់ ដែល
    មាននៅក្នុងប្រការ ២។
    </td>
</tr></table>

<div class="made-on">
    ធ្វើនៅរាជធានីភ្នំពេញថ្ងៃទី{!! $khDay($genDate) !!}ខែ{!! $khMonthName($genDate) !!}ឆ្នាំ{!! $khYear($genDate) !!}
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
