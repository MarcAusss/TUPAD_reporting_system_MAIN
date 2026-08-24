@props(['name'])
@error($name)
    <p id="{{ $name }}-error" class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
@enderror
