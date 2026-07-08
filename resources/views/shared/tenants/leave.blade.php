@extends('layouts.'.$panel)

@section('title', __('messages.process_tenant_leave'))

@section('content')
    @include('partials.tenant-leave-form', [
        'formAction' => route($panel.'.tenants.processLeave', $tenant->id),
        'backUrl' => route($panel.'.tenants.index'),
    ])
@endsection
