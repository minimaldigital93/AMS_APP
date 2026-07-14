@extends('layouts.admin')

@section('title', __('messages.create_new_user'))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.create_new_user') }}</h1>
        </div>
        <a href="{{ route('admin.users.index') }}" class="text-slate-400 hover:text-slate-600 text-sm font-medium py-2 px-4 rounded-lg border border-slate-200 hover:border-slate-300 transition">
            {{ __('messages.back_to_users') }}
        </a>
    </div>

    <!-- Form Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
        <form action="{{ route('admin.users.store') }}" method="POST" class="space-y-6 max-w-2xl">
            @csrf

            <!-- Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.name') }}</label>
                <input 
                    type="text" 
                    name="name" 
                    value="{{ old('name') }}"
                    required 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 @error('name') border-red-500 @else border-gray-300 @enderror"
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
                    value="{{ old('phone') }}"
                    required
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 @error('phone') border-red-500 @else border-gray-300 @enderror"
                    placeholder="{{ __('messages.enter_phone') }}"
                >
                @error('phone')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.password') }}</label>
                <input 
                    type="password" 
                    name="password" 
                    required 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 @error('password') border-red-500 @else border-gray-300 @enderror"
                    placeholder="{{ __('messages.enter_password') }}"
                >
                @error('password')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
                <p class="text-gray-500 text-sm mt-1">{{ __('messages.password_requirements') }}</p>
            </div>

            <!-- Role -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.role') }}</label>
                <select 
                    name="role" 
                    required 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 @error('role') border-red-500 @else border-gray-300 @enderror"
                >
                    <option value="">{{ __('messages.select_a_role') }}</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" {{ old('role') == $role->name ? 'selected' : '' }}>
                            {{ ucfirst($role->name) }}
                        </option>
                    @endforeach
                </select>
                @error('role')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
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
                    Create User
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
