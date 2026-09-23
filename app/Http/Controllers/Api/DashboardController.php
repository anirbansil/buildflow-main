<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\Submission;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    /**
     * Owner Dashboard: stats, project overview, pending actions and recent activity,
     * combining every module built so far.
     *
     * GET /api/v1/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $activeProjects = $user->projects()->where('projects.status', 1)->get();
        $projectIds = $activeProjects->pluck('id');

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'full_name' => $user->full_name,
                    'profile_photo' => $user->profile_photo ? url('storage/'.$user->profile_photo) : null,
                ],
                'stats' => $this->stats($activeProjects, $projectIds),
                'project_overview' => $activeProjects
                    ->sortByDesc('updated_at')
                    ->take(5)
                    ->values()
                    ->map(fn (Project $p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'phase' => array_search($p->phase, Project::PHASES, true) ?: null,
                        'phase_label' => Project::PHASE_LABELS[$p->phase] ?? null,
                        'progress' => $p->progress,
                    ]),
                'pending_actions' => $this->pendingActions($projectIds),
                'recent_activity' => ActivityLog::whereIn('project_id', $projectIds)
                    ->with('user')
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get()
                    ->map(fn (ActivityLog $log) => [
                        'id' => $log->id,
                        'project_id' => $log->project_id,
                        'type' => $log->type,
                        'description' => $log->description,
                        'actor' => $log->user?->full_name,
                        'time_ago' => $log->created_at?->diffForHumans(),
                    ]),
            ],
        ]);
    }

    private function stats(Collection $activeProjects, Collection $projectIds): array
    {
        $supervisorCount = User::role('SUPERVISOR')
            ->whereHas('projects', fn ($q) => $q->whereIn('projects.id', $projectIds))
            ->count();

        $pendingApprovals = Submission::whereIn('project_id', $projectIds)
            ->where('status', Submission::STATUSES['pending_final_approval'])
            ->count()
            + Payment::whereIn('project_id', $projectIds)
                ->where('status', Payment::STATUSES['pending_approval'])
                ->count();

        $totalBudget = $activeProjects->sum('total_budget');
        $usedBudget = $activeProjects->sum('used_budget');

        return [
            'assigned_projects' => $activeProjects->count(),
            'active_supervisors' => $supervisorCount,
            'pending_approvals' => $pendingApprovals,
            'budget_used' => round($usedBudget, 2),
            'budget_used_percent' => $totalBudget > 0 ? round(($usedBudget / $totalBudget) * 100, 1) : 0,
        ];
    }

    private function pendingActions(Collection $projectIds): Collection
    {
        $materials = ProjectMaterial::whereIn('project_id', $projectIds)
            ->where('status', ProjectMaterial::STATUSES['delayed'])
            ->with('project')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ProjectMaterial $m) => [
                'type' => 'material_shortage',
                'title' => 'Material Shortage',
                'subtitle' => $m->project?->name.' - '.$m->name,
                'project_id' => $m->project_id,
                'reference_id' => $m->id,
                'action' => 'Resolve',
                'created_at' => $m->updated_at,
            ]);

        $tasks = Task::whereIn('project_id', $projectIds)
            ->whereNull('assigned_to')
            ->where('status', 1)
            ->with('project')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Task $t) => [
                'type' => 'supervisor_assignment',
                'title' => 'Supervisor Assignment',
                'subtitle' => $t->project?->name.' - '.$t->title,
                'project_id' => $t->project_id,
                'reference_id' => $t->id,
                'action' => 'Assign',
                'created_at' => $t->created_at,
            ]);

        $payments = Payment::whereIn('project_id', $projectIds)
            ->where('status', Payment::STATUSES['pending_approval'])
            ->with('project')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Payment $p) => [
                'type' => 'invoice_approval',
                'title' => 'Invoice Approval',
                'subtitle' => $p->project?->name.' - '.$p->contractor_name,
                'project_id' => $p->project_id,
                'reference_id' => $p->id,
                'action' => 'Review',
                'created_at' => $p->created_at,
            ]);

        return $materials->concat($tasks)->concat($payments)
            ->sortByDesc('created_at')
            ->take(10)
            ->values()
            ->map(fn ($item) => [...$item, 'created_at' => $item['created_at']?->toDateTimeString()]);
    }
}
