@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-4">Fiscal Periods</h1>
        <a href="{{ route('admin.fiscalperiod.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Create New Fiscal Period
        </a>
    </div>

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
                            <td class="px-6 py-4 text-sm font-semibold">{{ number_format($period->opening_balance, 2) }}</td>
                            <td class="px-6 py-4 text-sm font-semibold">{{ number_format($period->closing_balance, 2) }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $period->status === 'open' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($period->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="flex gap-2">
                                    <a href="{{ route('admin.fiscalperiod.show', $period->id) }}" class="text-blue-600 hover:text-blue-800 font-medium">View</a>
                                    @if($period->status === 'open')
                                        <a href="{{ route('admin.fiscalperiod.edit', $period->id) }}" class="text-green-600 hover:text-green-800 font-medium">Edit</a>
                                        <a href="{{ route('admin.fiscalperiod.balance-sheet', $period->id) }}" class="text-purple-600 hover:text-purple-800 font-medium">Balance Sheet</a>
                                    @endif
                                    <a href="{{ route('admin.fiscalperiod.reports', $period->id) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Reports</a>
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
            <p class="text-yellow-900">No fiscal periods found. <a href="{{ route('admin.fiscalperiod.create') }}" class="text-blue-600 font-semibold hover:underline">Create one now</a></p>
        </div>
    @endif
</div>
@endsection
