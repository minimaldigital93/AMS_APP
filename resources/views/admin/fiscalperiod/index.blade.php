@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2">Fiscal Periods</h1>
        <p class="text-gray-600 mb-4">Manage your accounting periods to track rent revenue, record expenses, and monitor financial performance.</p>
        <a href="{{ route('admin.fiscalperiod.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Create New Fiscal Period
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    @if($fiscalPeriods->count())
        <div class="overflow-x-auto bg-white rounded-lg shadow">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-100 border-b">
                        <th class="px-6 py-3 text-left text-sm font-semibold">Period Name</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold">Opening Date</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold">Closing Date</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold">Opening Balance</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold">Closing Balance</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold">Status</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fiscalPeriods as $period)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium">{{ $period->name }}</td>
                            <td class="px-6 py-4 text-sm">{{ $period->opening_date->format('Y-m-d') }}</td>
                            <td class="px-6 py-4 text-sm">{{ $period->closing_date->format('Y-m-d') }}</td>
                            <td class="px-6 py-4 text-sm font-semibold">${{ number_format($period->opening_balance, 2) }}</td>
                            <td class="px-6 py-4 text-sm font-semibold">
                                @if($period->status === 'closed')
                                    ${{ number_format($period->closing_balance, 2) }}
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $period->status === 'open' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($period->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="flex items-center gap-1">
                                    {{-- View --}}
                                    <a href="{{ route('admin.fiscalperiod.show', $period->id) }}" class="p-2 rounded-lg text-blue-600 hover:bg-blue-50 hover:text-blue-800 transition" title="View Details">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    @if($period->status === 'open')
                                        {{-- Edit --}}
                                        <a href="{{ route('admin.fiscalperiod.edit', $period->id) }}" class="p-2 rounded-lg text-green-600 hover:bg-green-50 hover:text-green-800 transition" title="Edit Period">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        {{-- Balance Sheet --}}
                                        <a href="{{ route('admin.fiscalperiod.balance-sheet', $period->id) }}" class="p-2 rounded-lg text-purple-600 hover:bg-purple-50 hover:text-purple-800 transition" title="Balance Sheet">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        </a>
                                    @endif
                                    {{-- Reports --}}
                                    <a href="{{ route('admin.fiscalperiod.reports', $period->id) }}" class="p-2 rounded-lg text-indigo-600 hover:bg-indigo-50 hover:text-indigo-800 transition" title="Reports">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $fiscalPeriods->links() }}
        </div>
    @else
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <h3 class="text-lg font-bold text-yellow-900 mb-2">No Fiscal Periods Found</h3>
            <p class="text-yellow-800 mb-3">
                You need to create a fiscal period before you can record any transactions (rent payments, expenses, etc.).
                A fiscal period helps you organize your financial data by time frame and generate accurate reports.
            </p>
            <a href="{{ route('admin.fiscalperiod.create') }}" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 font-semibold">Create Your First Fiscal Period</a>
        </div>
    @endif
</div>
@endsection
