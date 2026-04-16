@extends('layouts.supervisor')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <h1 class="text-2xl font-bold">Support the Property</h1>
        <p class="text-gray-600 mt-2">If you'd like to contribute or record a donation for the property, use the form below.</p>

        <form action="#" method="POST" class="mt-4 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700">Donor Name</label>
                <input name="name" type="text" class="mt-1 block w-full rounded-md border-gray-200 shadow-sm" placeholder="Name" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Amount</label>
                <input name="amount" type="number" step="0.01" class="mt-1 block w-full rounded-md border-gray-200 shadow-sm" placeholder="0.00" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Note (optional)</label>
                <textarea name="note" rows="3" class="mt-1 block w-full rounded-md border-gray-200 shadow-sm" placeholder="Message or note"></textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Record Donation</button>
            </div>
        </form>
    </div>
</div>
@endsection
