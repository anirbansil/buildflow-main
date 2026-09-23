<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Project Report: milestone tracking and task status breakdown for one project.
     *
     * GET /api/v1/projects/{project}/reports/project
     */
    public function project(Request $request, int $projectId): JsonResponse
    {
        $project = $request->user()->projects()->where('projects.id', $projectId)->first();

        if (! $project) {
            return response()->json(['message' => 'Project not found or you do not have access.'], 404);
        }

        $milestones = $project->milestones()->get();
        $tasks = $project->tasks()->get();

        return response()->json([
            'data' => [
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'project_code' => $project->project_code,
                ],
                'milestones' => [
                    'total' => $milestones->count(),
                    'pending' => $milestones->where('status', Milestone::STATUSES['pending'])->count(),
                    'in_progress' => $milestones->where('status', Milestone::STATUSES['in_progress'])->count(),
                    'completed' => $milestones->where('status', Milestone::STATUSES['completed'])->count(),
                    'overdue' => $milestones->filter(fn ($m) =>
                        $m->status !== Milestone::STATUSES['completed']
                        && $m->due_date !== null
                        && $m->due_date->isPast()
                        && ! $m->due_date->isToday()
                    )->count(),
                ],
                'tasks' => [
                    'total' => $tasks->count(),
                    'pending' => $tasks->where('status', 1)->count(),
                    'assigned' => $tasks->where('status', 2)->count(),
                    'in_progress' => $tasks->where('status', 3)->count(),
                    'completed' => $tasks->where('status', 4)->count(),
                    'critical_path' => $tasks->where('is_critical_path', true)->count(),
                ],
                'generated_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Budget Report: budget vs. used and variance, across every project the user is assigned to.
     *
     * GET /api/v1/reports/budget
     */
    public function budget(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => 'nullable|integer',
        ]);

        $projects = $request->user()->projects()
            ->when($request->filled('project_id'), fn ($q) => $q->where('projects.id', $request->project_id))
            ->get();

        $rows = $projects->map(fn (Project $project) => $this->budgetRow($project));

        $totalBudget = $rows->sum('total_budget');
        $usedBudget = $rows->sum('used_budget');

        return response()->json([
            'data' => [
                'projects' => $rows->values(),
                'totals' => [
                    'total_budget' => $totalBudget,
                    'used_budget' => $usedBudget,
                    'remaining_budget' => $totalBudget - $usedBudget,
                    'variance_percent' => $this->variancePercent($totalBudget, $usedBudget),
                ],
                'generated_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    private function budgetRow(Project $project): array
    {
        $total = $project->total_budget ?? 0;
        $used = $project->used_budget ?? 0;
        $variance = $this->variancePercent($total, $used);

        return [
            'id' => $project->id,
            'name' => $project->name,
            'project_code' => $project->project_code,
            'total_budget' => $total,
            'used_budget' => $used,
            'remaining_budget' => $total - $used,
            'used_percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
            'variance_percent' => $variance,
            'status' => $variance > 0 ? 'over' : ($variance < 0 ? 'under' : 'on_track'),
        ];
    }

    private function variancePercent(float $total, float $used): float
    {
        return $total > 0 ? round((($used - $total) / $total) * 100, 1) : 0;
    }
}
