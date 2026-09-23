<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\Project;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Global payment ledger across every project the user is assigned to (Reports & Ledger table).
     *
     * GET /api/v1/payments
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate($this->filterRules());

        $projectIds = $request->user()->projects()->pluck('projects.id');

        $payments = $this->filtered(Payment::query()->whereIn('project_id', $projectIds), $request)
            ->with('project')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $payments->map(fn ($payment) => $this->resource($payment, includeProject: true)),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ],
        ]);
    }

    /**
     * Payment Requests table for a single project (Financial Summary screen).
     *
     * GET /api/v1/projects/{project}/payments
     */
    public function indexForProject(Request $request, int $projectId): JsonResponse
    {
        $project = $this->findAccessibleProject($request, $projectId);

        if (! $project) {
            return response()->json(['message' => 'Project not found or you do not have access.'], 404);
        }

        $request->validate($this->filterRules());

        $payments = $this->filtered($project->payments(), $request)
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $payments->map(fn ($payment) => $this->resource($payment)),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ],
        ]);
    }

    /**
     * Add a payment request ("Add Record").
     *
     * POST /api/v1/projects/{project}/payments
     */
    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = $this->findAccessibleProject($request, $projectId);

        if (! $project) {
            return response()->json(['message' => 'Project not found or you do not have access.'], 404);
        }

        $request->validate([
            'contractor_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:100',
            'work_package' => 'nullable|string|max:255',
            'task_id' => 'nullable|integer|exists:tasks,id,project_id,'.$project->id,
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'is_retainage' => 'nullable|boolean',
        ]);

        try {
            $payment = $project->payments()->create([
                'task_id' => $request->task_id,
                'contractor_name' => $request->contractor_name,
                'contact_name' => $request->contact_name,
                'invoice_number' => $request->invoice_number,
                'work_package' => $request->work_package,
                'description' => $request->description,
                'amount' => $request->amount,
                'is_retainage' => $request->boolean('is_retainage'),
                'status' => Payment::STATUSES['pending_approval'],
                'requested_by' => $request->user()->id,
            ]);

            ActivityLog::record($project->id, 'payment_requested', 'Payment request added for '.$payment->contractor_name.' ($'.number_format($payment->amount, 2).')', $request->user()->id);

            UserNotification::notifyOwners(
                $project,
                'financial',
                'Payment Request Pending',
                'New invoice from '.$payment->contractor_name.' for $'.number_format($payment->amount, 2).' awaiting approval.',
                "/payments/{$payment->id}"
            );

            return response()->json([
                'message' => 'Payment request added successfully.',
                'data' => $this->resource($payment),
            ], 201);

        } catch (\Exception $e) {
            Log::error('API create payment: '.$e->getMessage());

            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    /**
     * POST /api/v1/payments/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'approved', function (Payment $payment) use ($request) {
            $payment->approved_by = $request->user()->id;
            $payment->approved_at = now();

            UserNotification::notifyOwners(
                $payment->project,
                'financial',
                'Invoice Approved',
                'Payment request from '.$payment->contractor_name.' for $'.number_format($payment->amount, 2).' has been approved and routed for processing.',
                "/payments/{$payment->id}"
            );
        }, 'payment_approved', 'approved');
    }

    /**
     * Marks the payment as paid/cleared and adds its amount to the project's used_budget.
     *
     * POST /api/v1/payments/{id}/clear
     */
    public function clear(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'cleared', function (Payment $payment) {
            $payment->cleared_at = now();
            $payment->project->increment('used_budget', $payment->amount);

            UserNotification::notifyOwners(
                $payment->project,
                'financial',
                'Payment Processed',
                'Payment to '.$payment->contractor_name.' for $'.number_format($payment->amount, 2).' has been cleared.',
                "/payments/{$payment->id}"
            );
        }, 'payment_cleared', 'cleared');
    }

    /**
     * POST /api/v1/payments/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string']);

        return $this->transition($request, $id, 'rejected', function (Payment $payment) use ($request) {
            $payment->rejected_reason = $request->reason;
        }, 'payment_rejected', 'rejected');
    }

    /**
     * Budget summary cards for the Financial Summary screen.
     *
     * GET /api/v1/projects/{project}/financial-summary
     */
    public function financialSummary(Request $request, int $projectId): JsonResponse
    {
        $project = $this->findAccessibleProject($request, $projectId);

        if (! $project) {
            return response()->json(['message' => 'Project not found or you do not have access.'], 404);
        }

        $pending = $project->payments()->where('status', Payment::STATUSES['pending_approval']);
        $clearedThisMonth = $project->payments()
            ->where('status', Payment::STATUSES['cleared'])
            ->whereBetween('cleared_at', [now()->startOfMonth(), now()->endOfMonth()]);
        $retainageHeld = $project->payments()
            ->where('is_retainage', true)
            ->where('status', '!=', Payment::STATUSES['cleared']);

        $totalBudget = $project->total_budget ?? 0;
        $usedBudget = $project->used_budget ?? 0;

        return response()->json([
            'data' => [
                'total_approved_budget' => $totalBudget,
                'total_approved_budget_variance_percent' => $project->budgetVariancePercent(),
                'budget_consumed' => $usedBudget,
                'budget_consumed_percent' => $totalBudget > 0 ? round(($usedBudget / $totalBudget) * 100, 1) : 0,
                'pending_approval' => [
                    'amount' => round($pending->sum('amount'), 2),
                    'count' => $pending->count(),
                ],
                'cleared_this_month' => [
                    'amount' => round($clearedThisMonth->sum('amount'), 2),
                    'count' => $clearedThisMonth->count(),
                ],
                'retainage_held' => [
                    'amount' => round($retainageHeld->sum('amount'), 2),
                    'rate_percent' => $project->retainage_rate,
                ],
            ],
        ]);
    }

    /**
     * Export the ledger as CSV (same filters as the Global Ledger). Opens directly in Excel.
     *
     * GET /api/v1/payments/export
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate($this->filterRules());

        $projectIds = $request->user()->projects()->pluck('projects.id');

        $payments = $this->filtered(Payment::query()->whereIn('project_id', $projectIds), $request)
            ->with('project')
            ->get();

        $filename = 'payment-ledger-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Project', 'Contractor', 'Invoice #', 'Work Package', 'Description', 'Amount', 'Retainage', 'Status']);

            foreach ($payments as $payment) {
                fputcsv($out, [
                    $payment->created_at?->format('Y-m-d'),
                    $payment->project?->name,
                    $payment->contractor_name,
                    $payment->invoice_number,
                    $payment->work_package,
                    $payment->description,
                    $payment->amount,
                    $payment->is_retainage ? 'Yes' : 'No',
                    Payment::STATUS_LABELS[$payment->status] ?? '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function transition(Request $request, int $id, string $status, callable $mutate, string $logType, string $logVerb): JsonResponse
    {
        $payment = Payment::with('project')->find($id);

        $hasAccess = $payment && $request->user()->projects()->where('projects.id', $payment->project_id)->exists();

        if (! $hasAccess) {
            return response()->json(['message' => 'Payment not found or you do not have access.'], 404);
        }

        $payment->status = Payment::STATUSES[$status];
        $mutate($payment);
        $payment->save();

        ActivityLog::record($payment->project_id, $logType, 'Payment to '.$payment->contractor_name.' '.$logVerb, $request->user()->id);

        return response()->json([
            'message' => 'Payment updated successfully.',
            'data' => $this->resource($payment->fresh()),
        ]);
    }

    private function filterRules(): array
    {
        return [
            'status' => 'nullable|string|in:'.implode(',', array_keys(Payment::STATUSES)),
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'search' => 'nullable|string|max:255',
        ];
    }

    private function filtered($query, Request $request)
    {
        return $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', Payment::STATUSES[$request->status]))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('search'), fn ($q) =>
                $q->where('contractor_name', 'like', '%'.trim($request->search).'%')
            )
            ->orderByDesc('created_at');
    }

    private function findAccessibleProject(Request $request, int $projectId): ?Project
    {
        return $request->user()->projects()->where('projects.id', $projectId)->first();
    }

    private function resource(Payment $payment, bool $includeProject = false): array
    {
        $data = [
            'id' => $payment->id,
            'contractor_name' => $payment->contractor_name,
            'contact_name' => $payment->contact_name,
            'invoice_number' => $payment->invoice_number,
            'work_package' => $payment->work_package,
            'task_id' => $payment->task_id,
            'description' => $payment->description,
            'amount' => $payment->amount,
            'is_retainage' => $payment->is_retainage,
            'status' => array_search($payment->status, Payment::STATUSES, true) ?: null,
            'status_label' => Payment::STATUS_LABELS[$payment->status] ?? null,
            'requested_by' => $payment->requester?->full_name,
            'approved_by' => $payment->approver?->full_name,
            'approved_at' => $payment->approved_at?->toDateTimeString(),
            'cleared_at' => $payment->cleared_at?->toDateTimeString(),
            'rejected_reason' => $payment->rejected_reason,
            'created_at' => $payment->created_at?->toDateTimeString(),
        ];

        if ($includeProject) {
            $data['project'] = $payment->project ? ['id' => $payment->project->id, 'name' => $payment->project->name] : null;
        }

        return $data;
    }
}
