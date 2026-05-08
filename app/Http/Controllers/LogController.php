<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LogController extends Controller
{
    public function index(Request $request, $type)
    {
        // $type = $request->query('type');

        $logs = AuditLog::query()
            ->when($type, fn($q) => $q->where('type', $type))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('pages.logs.index', compact('logs', 'type'));
    }

    public function show(AuditLog $audit): JsonResponse
{
    $audit->load(['causer', 'subject']);

    return response()->json([
        'status' => 'success',
        'data' => [
            'id' => $audit->id,
            'type' => $audit->type,
            'action' => $audit->action,
            'title' => $this->makeTitle($audit),
            'summary' => $this->makeSummary($audit),
            'meta' => [
                'created_at' => $audit->created_at?->format('Y-m-d H:i'),
                'subject_type' => class_basename($audit->subject_type),
                'subject_name' => $this->getSubjectName($audit),
                'subject_id' => $audit->subject_id,
                'causer_name' => $audit->causer?->name,
                'causer_id' => $audit->causer_id,
                'type_label' => $audit->type,
                'action_label' => __('messages.log_actions.' . $audit->action),
            ],
            'changes' => $audit->changes ?? [],
        ],
    ]);
}

private function getSubjectName(AuditLog $audit): string
{
    if (!$audit->subject) {
        return '—';
    }

    if ($audit->subject instanceof \App\Models\Department) {
        return $audit->subject->name ?? '—';
    }

    if ($audit->subject instanceof \App\Models\User) {
        return $audit->subject->name ?? '—';
    }

    return $audit->subject->name
        ?? $audit->subject->title
        ?? class_basename($audit->subject_type);
}

private function makeTitle(AuditLog $audit): string
{
    return Str::headline($audit->type) . ' • ' . Str::headline($audit->action);
}

private function makeSummary(AuditLog $audit): string
{
    $subjectType = class_basename($audit->subject_type);
    $subjectName = $this->getSubjectName($audit);

    return "{$subjectType} — {$subjectName} (#{$audit->subject_id})";
}
}