{{--
    User Management roster — A4 portrait, Khmer. Follows the page's role
    dropdown and search box (UserController::rosterPdf), so the title names the
    role that was selected and a role column appears only under "All roles".

    Rendered by mPDF (KhmerPdf), which shapes Khmer; Dompdf cannot. Inline/table
    styles only — mPDF has no flex/grid. The Khmer wording is document content,
    so it lives inline here.

    Vars: $rows (name, suspended, role, phone, id_card_number, address,
          start_date, end_date)  $role ('admin'|'supervisor'|'tenant'|null)
          $search  $activeOnly  $company[] (name, address, phone, email)  $generatedAt
--}}
@php
    use App\Services\Pdf\KhmerPdf;

    $khDigits = fn ($v) => strtr((string) $v, ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩']);
    $date = fn ($d) => $d ? $d->format('d/m/Y') : '—';

    $roleNames = ['admin' => 'អ្នកគ្រប់គ្រង', 'superadmin' => 'អ្នកគ្រប់គ្រង', 'supervisor' => 'អ្នកត្រួតពិនិត្យ', 'tenant' => 'អ្នកជួល'];
    $title = match ($role) {
        'tenant' => 'បញ្ជីឈ្មោះអ្នកជួល',
        'supervisor' => 'បញ្ជីឈ្មោះអ្នកត្រួតពិនិត្យ',
        'admin' => 'បញ្ជីឈ្មោះអ្នកគ្រប់គ្រង',
        default => 'បញ្ជីឈ្មោះអ្នកប្រើប្រាស់',
    };
    $countUnit = $role === 'tenant' ? 'អ្នកជួលសរុប' : 'ចំនួនសរុប';
    $showRole = ! $role;
    // Column widths (%); the address column takes what is left. Narrower with
    // the extra role column so the address still has room to read.
    $w = $showRole
        ? ['no' => 5, 'name' => 15, 'role' => 11, 'phone' => 12, 'id' => 17, 'date' => 11]
        : ['no' => 6, 'name' => 17, 'role' => 0, 'phone' => 14, 'id' => 19, 'date' => 12];
@endphp
<style>
    body { font-family: {{ KhmerPdf::BODY }}; font-size: 10pt; color: #111827; }
    .company { text-align: center; border-bottom: 2px solid #1e40af; padding-bottom: 6px; margin-bottom: 10px; }
    .company-name { font-family: {{ KhmerPdf::TITLE }}; font-size: 15pt; color: #1e40af; }
    .company-info { font-size: 9pt; color: #4b5563; }
    .title { text-align: center; font-family: {{ KhmerPdf::TITLE }}; font-size: 13pt; margin: 6px 0 2px; }
    .meta { text-align: center; font-size: 9pt; color: #6b7280; margin-bottom: 10px; }
    table.list { width: 100%; border-collapse: collapse; }
    table.list th { background: #1e40af; color: #ffffff; font-weight: bold; font-size: 9.5pt; padding: 5px 4px; border: 1px solid #1e40af; }
    table.list td { font-size: 9.5pt; padding: 4px; border: 1px solid #d1d5db; vertical-align: top; }
    table.list tr.alt td { background: #f3f4f6; }
    .c { text-align: center; }
</style>

<div class="company">
    <div class="company-name">{{ $company['name'] }}</div>
    @if($company['address'])
        <div class="company-info">អាសយដ្ឋាន៖ {{ $company['address'] }}</div>
    @endif
    @if($company['phone'] || $company['email'])
        <div class="company-info">
            @if($company['phone'])ទូរស័ព្ទ៖ {{ $company['phone'] }}@endif
            @if($company['phone'] && $company['email']) &nbsp;·&nbsp; @endif
            @if($company['email'])អ៊ីមែល៖ {{ $company['email'] }}@endif
        </div>
    @endif
</div>

<div class="title">{{ $title }}</div>
<div class="meta">
    {{ $countUnit }}៖ {{ $khDigits($rows->count()) }} នាក់
    @if($activeOnly ?? false) &nbsp;·&nbsp; សកម្មតែប៉ុណ្ណោះ @endif
    @if($search) &nbsp;·&nbsp; ស្វែងរក៖ {{ $search }} @endif
    &nbsp;·&nbsp; កាលបរិច្ឆេទ៖ {{ $khDigits($generatedAt->format('d/m/Y')) }}
</div>

<table class="list">
    <thead>
        <tr>
            <th style="width:{{ $w['no'] }}%">ល.រ</th>
            <th style="width:{{ $w['name'] }}%">ឈ្មោះ</th>
            @if($showRole)<th style="width:{{ $w['role'] }}%">តួនាទី</th>@endif
            <th style="width:{{ $w['phone'] }}%">លេខទូរស័ព្ទ</th>
            <th style="width:{{ $w['id'] }}%">លេខអត្តសញ្ញាណប័ណ្ណ</th>
            <th>អាសយដ្ឋាន</th>
            <th style="width:{{ $w['date'] }}%">ថ្ងៃចាប់ផ្តើម</th>
            <th style="width:{{ $w['date'] }}%">ថ្ងៃបញ្ចប់</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $i => $t)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="c">{{ $khDigits($i + 1) }}</td>
                <td>{{ $t['name'] }}@if($t['suspended']) <span style="white-space:nowrap; color:#b45309">(ផ្អាក)</span>@endif</td>
                @if($showRole)<td>{{ $roleNames[$t['role']] ?? '—' }}</td>@endif
                <td>{{ $t['phone'] ?: '—' }}</td>
                <td>{{ $t['id_card_number'] ?: '—' }}</td>
                <td>{{ $t['address'] ?: '—' }}</td>
                <td class="c">{{ $date($t['start_date']) }}</td>
                <td class="c">{{ $date($t['end_date']) }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $showRole ? 8 : 7 }}" class="c">មិនមានទិន្នន័យទេ</td></tr>
        @endforelse
    </tbody>
</table>
