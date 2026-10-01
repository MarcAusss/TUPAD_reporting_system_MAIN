{{--
    <x-breadcrumbs :items="[['label' => 'Projects', 'url' => route('projects.index')], ['label' => 'Current page']]" />
    The last item is the current page. An item with 'data' => 'workspace-tab' is
    kept in sync with the project workspace tab by project-workspace.js.
--}}
@props(['items' => []])

@if (count($items) > 0)
    <nav aria-label="Breadcrumb" {{ $attributes->class('mb-2 min-w-0') }}>
        <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-slate-500">
            @foreach ($items as $item)
                @php $isLast = $loop->last; @endphp
                <li class="flex min-w-0 items-center gap-1.5" @if (($item['data'] ?? null) === 'workspace-tab') data-breadcrumb-tab @endif>
                    @if (! $loop->first)
                        <svg class="h-3 w-3 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    @endif

                    @if (! empty($item['url']) && ! $isLast)
                        <a href="{{ $item['url'] }}" class="max-w-[240px] truncate font-medium text-slate-500 hover:text-[#063b86] hover:underline">{{ $item['label'] }}</a>
                    @else
                        <span @class(['max-w-[320px] truncate', 'font-semibold text-slate-700' => $isLast]) @if ($isLast) aria-current="page" @endif data-breadcrumb-label>{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
