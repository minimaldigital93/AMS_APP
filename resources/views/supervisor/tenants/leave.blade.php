@extends('layouts.supervisor')

@section('title', __('messages.process_tenant_leave'))

@section('content')
    @include('partials.tenant-leave-form', [
        'formAction' => route('supervisor.tenants.processLeave', $tenant),
        'backUrl' => route('supervisor.tenants.index'),
    ])
@endsection
