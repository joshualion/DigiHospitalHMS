<?php

namespace App\Http\Controllers\Admin;

use App\Models\NumberSequence;
use App\Services\NumberSequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NumberSequenceController extends FoundationController
{
    public function index(): Response
    {
        $this->authorize('viewAny', NumberSequence::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Numbering/Index', [
            'sequences' => NumberSequence::with('facility:id,name,code')
                ->where('hospital_id', $hospital->id)
                ->orderBy('key')
                ->get(),
        ]);
    }

    public function update(Request $request, NumberSequence $sequence, NumberSequenceService $service): RedirectResponse
    {
        $this->authorize('update', $sequence);
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:20'],
            'date_format' => ['nullable', 'string', 'max:20'],
            'padding_length' => ['required', 'integer', 'min:3', 'max:12'],
            'next_value' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $service->updateConfiguration($sequence, $validated);

        return back()->with('success', 'Number sequence updated.');
    }

    public function allocate(NumberSequence $sequence, NumberSequenceService $service): RedirectResponse
    {
        $this->authorize('update', $sequence);
        $number = $service->allocate($sequence);

        return back()->with('success', "Allocated {$number}.");
    }
}
