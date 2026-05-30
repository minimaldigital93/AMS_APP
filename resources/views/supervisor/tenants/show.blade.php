@extends('layouts.supervisor')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="px-4 sm:px-6 lg:px-8">
        @include('partials.tenant-show', ['tenant' => $tenant, 'role' => 'supervisor'])
    </div>
</div>
@endsection
