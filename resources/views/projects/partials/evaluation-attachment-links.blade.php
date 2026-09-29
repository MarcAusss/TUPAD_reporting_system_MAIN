{{-- Download links for evaluation / compliance attachments. Expects $attachments and $projectId. --}}
@if ($attachments->isNotEmpty())
    <ul class="mt-2 space-y-1">
        @foreach ($attachments as $evaluationFile)
            <li>
                <a href="{{ route('projects.compliance.attachments.download', [$projectId, $evaluationFile]) }}"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-[#063b86] hover:underline">
                    <span aria-hidden="true">📎</span> {{ $evaluationFile->original_name }}
                </a>
            </li>
        @endforeach
    </ul>
@endif
