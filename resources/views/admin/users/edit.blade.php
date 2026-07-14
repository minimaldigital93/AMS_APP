@extends('layouts.admin')

@section('title', __('messages.edit_user'))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.edit_user') }}</h1>
        </div>
        <a href="{{ route('admin.users.index') }}" title="{{ __('messages.back_to_users') }}" class="inline-flex items-center justify-center text-slate-400 hover:text-slate-600 p-2 rounded-lg border border-slate-200 hover:border-slate-300 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
    </div>

    <!-- Form Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
        <form action="{{ route('admin.users.update', $user) }}" method="POST" class="space-y-6 max-w-2xl">
            @csrf
            @method('PUT')

            <!-- Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.name') }}</label>
                <input 
                    type="text" 
                    name="name" 
                    value="{{ old('name', $user->name) }}"
                    required 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 {{ $errors->has('name') ? 'border-red-500' : 'border-gray-300' }}"
                    placeholder="{{ __('messages.enter_username') }}"
                >
                @error('name')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Phone -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.phone') }}</label>
                <input
                    type="text"
                    name="phone"
                    value="{{ old('phone', $user->phone) }}"
                    required
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 {{ $errors->has('phone') ? 'border-red-500' : 'border-gray-300' }}"
                    placeholder="{{ __('messages.enter_phone') }}"
                >
                @error('phone')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Role -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.role') }}</label>
                <select 
                    name="role" 
                    required 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 {{ $errors->has('role') ? 'border-red-500' : 'border-gray-300' }}"
                >
                    <option value="">{{ __('messages.select_a_role') }}</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" {{ old('role', $user->roles->first()?->name) == $role->name ? 'selected' : '' }}>
                            {{ ucfirst($role->name) }}
                        </option>
                    @endforeach
                </select>
                @error('role')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Status -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.status') }}</label>
                <select 
                    name="status" 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 {{ $errors->has('status') ? 'border-red-500' : 'border-gray-300' }}"
                >
                    <option value="active" {{ $user->status === 'active' ? 'selected' : '' }}>{{ __('messages.active') }}</option>
                    <option value="inactive" {{ $user->status === 'inactive' ? 'selected' : '' }}>{{ __('messages.inactive') }}</option>
                    <option value="suspended" {{ $user->status === 'suspended' ? 'selected' : '' }}>{{ __('messages.suspended') }}</option>
                </select>
                @error('status')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- New Password (optional) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.new_password') }}</label>
                <input
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 {{ $errors->has('password') ? 'border-red-500' : 'border-gray-300' }}"
                    placeholder="{{ __('messages.enter_password') }}"
                >
                @error('password')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
                <p class="text-gray-500 text-sm mt-1">{{ __('messages.leave_blank_keep_password') }}</p>
            </div>

            <!-- Buttons -->
            <div class="flex gap-4 pt-6 border-t border-gray-200">
                <a
                    href="{{ route('admin.users.index') }}"
                    class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition font-medium"
                >
                    {{ __('messages.cancel') }}
                </a>
                <button
                    type="submit"
                    class="px-6 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-700 transition font-medium"
                >
                    Update User
                </button>
            </div>
        </form>
    </div>

    <!-- Reset password to default -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">{{ __('messages.reset_password') }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ __('messages.reset_password_help', ['password' => '12345678']) }}</p>
            </div>
            <form method="POST" action="{{ route('admin.users.reset-password', $user) }}"
                  data-confirm="{{ __('messages.confirm_reset_password', ['name' => $user->name, 'password' => '12345678']) }}"
                  data-confirm-title="{{ __('messages.reset_password') }}"
                  data-confirm-ok="{{ __('messages.confirm_reset_password_ok') }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                    {{ __('messages.reset_password') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
