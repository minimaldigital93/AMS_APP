@if ($errors->any())
    <div class="m-6 mb-0 bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-red-600 text-sm">
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="p-6 space-y-5">
    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.property_name') }} <span class="text-red-400">*</span></label>
        <input type="text" name="name" required value="{{ old('name', $property?->name) }}"
               placeholder="{{ __('messages.eg_property_name') }}"
               class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
    </div>

    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.property_address') }}</label>
        <input type="text" name="address" value="{{ old('address', $property?->address) }}"
               class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
    </div>

    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.assign_supervisor') }}</label>
        <select name="supervisor_id"
                class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
            <option value="">{{ __('messages.unassigned') }}</option>
            @foreach ($supervisors as $supervisor)
                <option value="{{ $supervisor->id }}" @selected(old('supervisor_id', $property?->supervisor_id) == $supervisor->id)>{{ $supervisor->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.description') }}</label>
        <textarea name="description" rows="3"
                  class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">{{ old('description', $property?->description) }}</textarea>
    </div>
</div>

<div class="px-6 py-4 border-t border-slate-100 flex gap-3">
    <a href="{{ route('admin.properties.index') }}" class="flex-1 text-center text-slate-500 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 text-sm font-medium py-2.5 px-5 rounded-lg transition">
        {{ __('messages.cancel') }}
    </a>
    <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition">
        {{ __('messages.save') }}
    </button>
</div>
