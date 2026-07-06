@extends('layouts.admin')

@section('title', __('messages.process_tenant_leave'))

@section('content')
    @include('partials.tenant-leave-form', [
        'formAction' => route('admin.tenants.processLeave', $tenant->id),
        'backUrl' => route('admin.tenants.index'),
    ])
@endsection
