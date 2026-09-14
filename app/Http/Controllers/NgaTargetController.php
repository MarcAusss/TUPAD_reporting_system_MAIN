<?php

namespace App\Http\Controllers;

use App\Models\NgaTarget;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NgaTargetController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));

        $targets = NgaTarget::query()
            ->when(
                $search !== '',
                fn ($query) => $query->where('nga', 'like', "%{$search}%"),
            )
            ->orderBy('nga')
            ->paginate(15)
            ->withQueryString();

        return view('targets.index', [
            'targets' => $targets,
            'search' => $search,
            'ngaSuggestions' => $this->ngaSuggestions(),
        ]);
    }

    public function create(): View
    {
        return view('targets.create', [
            'ngaSuggestions' => $this->ngaSuggestions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTarget($request);

        NgaTarget::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('targets.index')
            ->with('success', 'NGA target created successfully.');
    }

    public function edit(NgaTarget $target): View
    {
        return view('targets.edit', [
            'target' => $target,
            'ngaSuggestions' => $this->ngaSuggestions(),
        ]);
    }

    public function update(Request $request, NgaTarget $target): RedirectResponse
    {
        $validated = $this->validateTarget($request);

        $target->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('targets.index')
            ->with('success', 'NGA target updated successfully.');
    }

    public function destroy(NgaTarget $target): RedirectResponse
    {
        $target->delete();

        return redirect()
            ->route('targets.index')
            ->with('success', 'NGA target removed.');
    }

    private function validateTarget(Request $request): array
    {
        $validated = $request->validate([
            'nga' => ['required', 'string', 'max:255'],
            'total_beneficiaries' => ['required', 'integer', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'accomplishments' => ['required', 'integer', 'min:0'],
        ]);

        $validated['nga'] = trim($validated['nga']);

        /*
        |--------------------------------------------------------------------------
        | Balance Is Server-Calculated
        |--------------------------------------------------------------------------
        |
        | The form shows a live preview, but the stored value is always
        | Total Beneficiaries - Accomplishments so it can't drift from a
        | manually-edited request.
        |
        */
        $validated['balance'] = $validated['total_beneficiaries'] - $validated['accomplishments'];

        return $validated;
    }

    /**
     * Existing Partner/NGA names already encoded on ADL allocations, offered
     * as suggestions (not a hard constraint) so target entries stay
     * consistent with project data without becoming a managed list.
     */
    private function ngaSuggestions(): array
    {
        return Project::query()
            ->whereNotNull('partner')
            ->where('partner', '!=', '')
            ->distinct()
            ->orderBy('partner')
            ->pluck('partner')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
