<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Earning;
use App\Models\Project;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningController extends Controller
{
    /**
     * The authenticated worker's own earnings: summary cards + list (Earnings Overview,
     * Payment History). Optional `status`, `range` (this_week|this_month|all_time), `per_page`.
     *
     * GET /api/v1/my-earnings
     */
    public function myEarnings(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string|in:'.implode(',', array_keys(Earning::STATUSES)),
            'range' => 'nullable|string|in:this_week,this_month,all_time',
        ]);

        $workerId = $request->user()->id;

        $pending = Earning::where('worker_id', $workerId)->where('status', Earning::STATUSES['pending']);
        $approved = Earning::where('worker_id', $workerId)->where('status', Earning::STATUSES['approved']);
        $paidThisMonth = Earning::where('worker_id', $workerId)
            ->where('status', Earning::STATUSES['paid'])
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()]);

        $list = Earning::where('worker_id', $workerId)
            ->with('task')
            ->when($request->filled('status'), fn ($q) => $q->where('status', Earning::STATUSES[$request->status]))
            ->when($request->input('range') === 'this_week', fn ($q) => $q->where('created_at', '>=', now()->startOfWeek()))
            ->when($request->input('range') === 'this_month', fn ($q) => $q->where('created_at', '>=', now()->startOfMonth()))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => [
                'summary' => [
                    'pending' => ['amount' => round($pending->sum('amount'), 2), 'count' => $pending->count()],
                    'approved' => ['amount' => round($approved->sum('amount'), 2), 'count' => $approved->count()],
                    'paid_this_month' => ['amount' => round($paidThisMonth->sum('amount'), 2), 'count' => $paidThisMonth->count()],
                    'available_balance' => round($approved->sum('amount'), 2),
                ],
                'earnings' => $list->map(fn ($e) => $this->resource($e)),
                'meta' => [
                    'current_page' => $list->currentPage(),
                    'last_page' => $list->lastPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'from' => $list->firstItem(),
                    'to' => $list->lastItem(),
                ],
            ],
        ]);
    }

    /**
     * Worker requests payout of everything currently Approved — moves it to Processing.
     *
     * POST /api/v1/my-earnings/withdraw
     */
    public function withdraw(Request $request): JsonResponse
    {
        $approved = Earning::where('worker_id', $request->user()->id)
            ->where('status', Earning::STATUSES['approved'])
            ->get();

        if ($approved->isEmpty()) {
            return response()->json(['message' => 'No approved earnings available to withdraw.'], 422);
        }

        Earning::whereIn('id', $approved->pluck('id'))->update(['status' => Earning::STATUSES['processing']]);

        return response()->json([
            'message' => 'Withdrawal requested successfully.',
            'data' => [
                'amount' => round($approved->sum('amount'), 2),
                'count' => $approved->count(),
            ],
        ]);
    }

    /**
     * Owner's list of earnings entries for a project (payroll management).
     *
     * GET /api/v1/projects/{project}/earnings
     */
    public function indexForProject(Request $request, int $projectId): JsonResponse
    {
        $project = $this->findAccessibleProject($request, $projectId);

        if (! $project) {
            return response()->json(['message' => 'Project not found or you do not have access.'], 404);
        }

        $earnings = $project->earnings()
            ->with('worker', 'task')
            ->when($request->filled('status'), fn ($q) => $q->where('status', Earning::STATUSES[$request->status]))
            ->when($request->filled('worker_id'), fn ($q) => $q->where('worker_id', $request->worker_id))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $earnings->map(fn ($e) => $this->resource($e, includeWorker: true)),
            'meta' => [
                'current_page' => $earnings->currentPage(),
                'last_page' => $earnings->lastPage(),
                'per_page' => $earnings->perPage(),
                'total' => $earnings->total(),
                'from' => $earnings->firstItem(),
                'to' => $earnings->lastItem(),
            ],
        ]);
    }

    /**
     * Owner creates a payroll entry for a worker. Caller must be an OWNER.
     *
     * POST /api/v1/projects/{project}/earnings
     */
    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = $this->findAccessibleProject($request, $projectId);

        if (! $project) {
            return response()->json(['message' => 'Project not found or you do not have access.'], 404);
        }

        if (! $request->user()->hasRole('OWNER')) {
            return response()->json(['message' => 'Only an owner can create earnings entries.'], 403);
        }

        $request->validate([
            'worker_id' => 'required|integer|exists:users,id',
            'task_id' => 'nullable|integer|exists:tasks,id,project_id,'.$project->id,
            'title' => 'required|string|max:150',
            'subtitle' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'earned_at' => 'nullable|date',
        ]);

        $worker = User::find($request->worker_id);

        if (! $worker->hasRole('WORKER')) {
            return response()->json(['message' => 'Selected user is not a worker.'], 422);
        }

        $earning = $project->earnings()->create([
            'task_id' => $request->task_id,
            'worker_id' => $worker->id,
            'title' => $request->title,
            'subtitle' => $request->subtitle,
            'amount' => $request->amount,
            'status' => Earning::STATUSES['pending'],
            'earned_at' => $request->input('earned_at', now()->toDateString()),
            'created_by' => $request->user()->id,
        ]);

        ActivityLog::record($project->id, 'earning_created', 'Earning of $'.number_format($earning->amount, 2).' logged for '.$worker->full_name, $request->user()->id);

        return response()->json([
            'message' => 'Earning created successfully.',
            'data' => $this->resource($earning, includeWorker: true),
        ], 201);
    }

    /**
     * POST /api/v1/earnings/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'approved', ['approved_at' => now()]);
    }

    /**
     * Marks an earning as paid (the actual transfer completed). Works from Approved or
     * Processing. Notifies the worker ("Payment Released").
     *
     * POST /api/v1/earnings/{id}/pay
     */
    public function pay(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'paid', ['paid_at' => now()], notify: true);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function transition(Request $request, int $id, string $status, array $extra, bool $notify = false): JsonResponse
    {
        $earning = Earning::with('worker', 'project')->find($id);

        $hasAccess = $earning && $request->user()->hasRole('OWNER')
            && (! $earning->project_id || $request->user()->projects()->where('projects.id', $earning->project_id)->exists());

        if (! $hasAccess) {
            return response()->json(['message' => 'Earning not found or you do not have access.'], 404);
        }

        $earning->update(array_merge(['status' => Earning::STATUSES[$status]], $extra));

        if ($notify) {
            UserNotification::notifyUser(
                $earning->worker_id,
                $earning->project_id,
                'financial',
                'Payment Released',
                'A payment of $'.number_format($earning->amount, 2).' has been processed to your account.',
            );
        }

        return response()->json([
            'message' => 'Earning updated successfully.',
            'data' => $this->resource($earning->fresh(), includeWorker: true),
        ]);
    }

    private function findAccessibleProject(Request $request, int $projectId): ?Project
    {
        return $request->user()->projects()->where('projects.id', $projectId)->first();
    }

    private function resource(Earning $earning, bool $includeWorker = false): array
    {
        $data = [
            'id' => $earning->id,
            'title' => $earning->title,
            'subtitle' => $earning->subtitle,
            'task_id' => $earning->task_id,
            'task_code' => $earning->task?->task_code,
            'amount' => $earning->amount,
            'status' => array_search($earning->status, Earning::STATUSES, true) ?: null,
            'status_label' => Earning::STATUS_LABELS[$earning->status] ?? null,
            'earned_at' => $earning->earned_at?->format('Y-m-d'),
            'paid_at' => $earning->paid_at?->toDateTimeString(),
            'created_at' => $earning->created_at?->toDateTimeString(),
        ];

        if ($includeWorker) {
            $data['worker'] = $earning->worker ? ['id' => $earning->worker->id, 'full_name' => $earning->worker->full_name] : null;
        }

        return $data;
    }
}
