{{-- Desktop table row for a single user. Expects: $user, $roles, $number --}}
<tr class="hover:bg-gray-50 transition {{ ($user->status ?? null) === 'suspended' ? 'bg-gray-50/60' : '' }}">
    <td class="px-4 py-3 text-gray-600">{{ $number }}</td>
    <td class="px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
    <td class="px-4 py-3 text-gray-600">{{ $user->phone }}</td>
    <td class="px-4 py-3">
        <span class="px-3 py-1 text-sm font-semibold rounded-full bg-sky-100 text-sky-700">{{ ucfirst($user->roles->first()?->name ?? 'N/A') }}</span>
    </td>
    <td class="px-4 py-3">
        @php
            $tenantRecord = $user->roles->first()?->name === 'tenant'
                ? $user->tenants->whereIn('status', ['active', 'pending'])->first()
                : null;
        @endphp
        @if($tenantRecord?->apartment)
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-50 text-sky-700 whitespace-nowrap">
                @if($tenantRecord->apartment->floor){{ $tenantRecord->apartment->floor->floor_name }} / @endif{{ $tenantRecord->apartment->apartment_number }}
            </span>
        @else
            <span class="text-gray-400 text-xs">—</span>
        @endif
    </td>
    <td class="px-4 py-3">
        <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $user->status === 'active' ? 'bg-green-100 text-green-700' : (($user->status ?? null) === 'suspended' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-700') }}">{{ status_label($user->status ?? 'unknown') }}</span>
    </td>
    <td class="px-4 py-3">
        @if($user->hasAnyRole(['admin', 'superadmin']))
            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-400">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                {{ ucfirst($user->roles->first()?->name) }}
            </span>
        @else
            <form action="{{ route('admin.users.updateRole', $user) }}" method="POST">
                @csrf
                @method('PATCH')
                <select name="role" onchange="this.form.submit()" class="w-40 xl:w-52 px-2 py-1 text-xs font-medium rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-slate-400">
                    <option value="">{{ __('messages.assign_role') }}</option>
                    @foreach($roles->whereIn('name', ['supervisor', 'tenant']) as $role)
                        <option value="{{ $role->id }}" {{ $user->roles->contains($role->id) ? 'selected' : '' }}>{{ ucfirst($role->name) }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </td>
    <td class="px-4 py-3 flex items-center gap-3">
        {{-- Admin/superadmin rows aren't manageable here (the controller 403s) --}}
        @unless($user->hasAnyRole(['admin', 'superadmin']))
        <a href="{{ route('admin.users.edit', $user) }}"
           class="text-sky-600 hover:text-sky-700 p-2 rounded-lg bg-sky-50/20 hover:bg-sky-50/40 transition" title="{{ __('messages.edit_user') }}">
            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
            </svg>
        </a>
        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" data-confirm="Delete this user?">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-red-500 hover:text-red-600 p-2 rounded-lg bg-red-50/20 hover:bg-red-50/40 transition" title="{{ __('messages.delete') }}">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
            </button>
        </form>
        @endunless
    </td>
</tr>
