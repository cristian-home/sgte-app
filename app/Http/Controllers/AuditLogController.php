<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_AUDIT_LOG->value);

        $activities = QueryBuilder::for(Activity::class)
            ->with(['causer:id,name,email'])
            ->allowedFilters([
                AllowedFilter::exact('log_name'),
                AllowedFilter::exact('subject_type'),
                AllowedFilter::exact('causer_id'),
                AllowedFilter::exact('event'),
            ])
            ->allowedSorts(['created_at', 'log_name', 'event'])
            ->defaultSort('-created_at')
            ->limit($request->integer('per_page', 50) ?: 50)
            ->get()
            ->map(fn (Activity $activity): array => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'event' => $activity->event,
                'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
                'subject_id' => $activity->subject_id,
                'causer' => $activity->causer ? [
                    'id' => $activity->causer->id,
                    'name' => $activity->causer->name,
                    'email' => $activity->causer->email,
                ] : null,
                'created_at' => $activity->created_at?->toIso8601String(),
            ]);

        return Inertia::render('audit-log/index', [
            'activities' => $activities,
        ]);
    }
}
