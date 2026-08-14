<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends FoundationController
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AuditEvent::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Audit/Index', [
            'filters' => $request->only(['action', 'subject']),
            'events' => AuditEvent::with('actor:id,firstname,lastname,email')
                ->where('hospital_id', $hospital->id)
                ->when($request->action, fn ($query, $action) => $query->where('action', 'like', "%{$action}%"))
                ->when($request->subject, fn ($query, $subject) => $query->where('subject_type', 'like', "%{$subject}%"))
                ->latest('occurred_at')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }
}
