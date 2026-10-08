{{--
    Summary financial statement for a chosen date range — the PDF behind the
    "Download PDF" popup on Reports & Exports (FiscalPeriodController::statementPdf).

    Rendered by mPDF (KhmerPdf) so a Khmer-locale statement shapes correctly;
    inline/table styles only — mPDF has no flex/grid. The statement body is the
    same partial the Overview tab prints.

    Vars: $fiscalperiod  $pf  $cash  $from  $to  $scopeLabel  $drawsByMonthEnd  $company[]
--}}
@php use App\Services\Pdf\KhmerPdf; @endphp
<style>
    body { font-family: {{ KhmerPdf::BODY }}; font-size: 10pt; color: #111827; }
    .company { text-align: center; border-bottom: 2px solid #111827; padding-bottom: 6px; margin-bottom: 8px; }
    .company-name { font-size: 15pt; font-weight: bold; }
    .company-info { font-size: 9pt; color: #4b5563; }
    .meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .meta td { font-size: 8.5pt; color: #4b5563; padding: 2px 0; }
    .meta strong { color: #111827; }
</style>

<div class="company">
    <div class="company-name">{{ $company['name'] }}</div>
    @if($company['address'])
        <div class="company-info">{{ $company['address'] }}</div>
    @endif
    @if($company['phone'] || $company['email'])
        <div class="company-info">{{ collect([$company['phone'], $company['email']])->filter()->implode(' · ') }}</div>
    @endif
</div>

<table class="meta">
    <tr>
        <td><strong>{{ __('messages.fiscal_period') }}:</strong> {{ $fiscalperiod->name }}</td>
        <td style="text-align:center;"><strong>{{ __('messages.property') }}:</strong> {{ $scopeLabel }}</td>
        <td style="text-align:right;"><strong>{{ __('messages.generated') }}:</strong> {{ now()->format('d M Y · h:i A') }}</td>
    </tr>
</table>

@include('admin.fiscalperiod.partials.summary-statement')

<div style="margin-top:20px; padding-top:6px; border-top:1px solid #e5e7eb; text-align:center; font-size:8pt; color:#9ca3af;">
    {{ $company['name'] }} &nbsp;·&nbsp; {{ __('messages.generated_by') }}: {{ auth()->user()?->name }} &nbsp;·&nbsp; {{ __('messages.ams') }}
</div>
