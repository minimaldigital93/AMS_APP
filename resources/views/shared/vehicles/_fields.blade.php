{{--
    The add/edit vehicle field row, shared by both forms on the vehicle
    management page.

    Required vars:
      $vehicle : App\Models\TenantVehicle | null  (null = the add form)
      $formId  : the `_form` token identifying this form
      $ringCls, $btnCls : panel-tinted class strings

    Only the form that failed validation repopulates from old input and shows
    errors — `old()` is global, so without the $formId check every form on the
    page would echo the same failure.
--}}
@php
    $failed = old('_form') === $formId;
    $val = fn (string $key, $fallback = null) => $failed ? old($key, $fallback) : $fallback;
@endphp

{{-- Column order and widths mirror the display row above it, so a field sits
     under the value it edits. --}}
<div class="grid grid-cols-1 sm:grid-cols-[7.5rem_10rem_minmax(0,1fr)_8rem_auto] gap-2 items-start bg-slate-50 rounded-lg p-3">
    <div>
        <select name="vehicle_type" required
                class="w-full px-2 py-2 text-sm border border-slate-200 rounded-lg bg-white {{ $failed && $errors->has('vehicle_type') ? 'border-red-400' : '' }} {{ $ringCls }}">
            @foreach(\App\Models\TenantVehicle::TYPES as $vt)
                <option value="{{ $vt }}" @selected($val('vehicle_type', $vehicle?->vehicle_type) === $vt)>{{ __('messages.vehicle_type_'.$vt) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <input type="text" name="plate_number" required maxlength="30" value="{{ $val('plate_number', $vehicle?->plate_number) }}"
               placeholder="{{ __('messages.plate_number_placeholder') }}"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg uppercase {{ $failed && $errors->has('plate_number') ? 'border-red-400' : '' }} {{ $ringCls }}">
    </div>
    <div>
        {{-- Description only — the plate is the identity, the model is what the
             guard on the gate recognises. --}}
        <input type="text" name="vehicle_model" maxlength="50" value="{{ $val('vehicle_model', $vehicle?->vehicle_model) }}"
               placeholder="{{ __('messages.vehicle_model_placeholder') }}"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg {{ $failed && $errors->has('vehicle_model') ? 'border-red-400' : '' }} {{ $ringCls }}">
    </div>
    <div>
        <input type="number" name="monthly_fee" step="0.01" min="0"
               value="{{ $val('monthly_fee', $vehicle && $vehicle->monthly_fee > 0 ? money_input($vehicle->monthly_fee) : null) }}"
               placeholder="{{ __('messages.monthly_parking_fee') }} ({{ currency_symbol() }})"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg text-right {{ $failed && $errors->has('monthly_fee') ? 'border-red-400' : '' }} {{ $ringCls }}">
    </div>
    <div class="flex items-center gap-2">
        <button type="submit" class="px-4 py-2 rounded-lg text-white text-sm font-medium {{ $btnCls }} transition">{{ __('messages.save') }}</button>
        <button type="button" x-on:click="open = null" class="px-3 py-2 rounded-lg text-sm text-slate-500 hover:text-slate-700 transition">{{ __('messages.cancel') }}</button>
    </div>

    @if($failed && $errors->any())
    <ul class="sm:col-span-5 text-xs text-red-500 space-y-0.5">
        @foreach($errors->all() as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
    @else
    <p class="sm:col-span-5 text-xs text-slate-400">{{ __('messages.vehicle_fee_hint') }}</p>
    @endif
</div>
