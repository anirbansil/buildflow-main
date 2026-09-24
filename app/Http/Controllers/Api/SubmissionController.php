<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Submission;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubmissionController extends Controller
{
    /**
     * List submissions across every project the user is assigned to (Submission Review list).
     *
     * GET /api/v1/submissions
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string|in:'.implode(',', array_keys(Submission::STATUSES)),
            'project_id' => 'nullable|integer',
            'search' => 'nullable|string|max:255',
        ]);

        $projectIds = $request->user()->projects()->pluck('projects.id');

        $submissions = Submission::query()
            ->whereIn('project_id', $projectIds)
            ->with(['task', 'worker'])
            ->when($request->filled('status'), fn ($q) =>
                $q->where('status', Submission::STATUSES[$request->status])
            )
            ->when($request->filled('project_id'), fn ($q) =>
                $q->where('project_id', $request->project_id)
            )
            ->when($request->filled('search'), fn ($q) =>
                $q->whereHas('task', fn ($t) => $t->where('title', 'like', '%'.trim($request->search).'%'))
            )
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $submissions->map(fn ($submission) => $this->listResource($submission)),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
                'from' => $submissions->firstItem(),
                'to' => $submissions->lastItem(),
            ],
        ]);
    }

    /**
     * View a submission's detail (quantity, worker description, supervisor verification, media).
     *
     * GET /api/v1/submissions/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $submission = $this->findAccessibleSubmission($request, $id);

        if (! $submission) {
            return response()->json(['message' => 'Submission not found or you do not have access.'], 404);
        }

        return response()->json(['data' => $this->detailResource($submission)]);
    }

    /**
     * Submit work for a task. Send as multipart/form-data when attaching media.
     * (Normally called from the worker app — included here so a submission exists to review.)
     *
     * POST /api/v1/tasks/{task}/submissions
     */
    public function store(Request $request, int $taskId): JsonResponse
    {
        $task = Task::with('project')->find($taskId);

        $hasAccess = $task && $request->user()->projects()->where('projects.id', $task->project_id)->exists();

        if (! $hasAccess) {
            return response()->json(['message' => 'Task not found or you do not have access.'], 404);
        }

        $callerIsWorker = $request->user()->hasRole('WORKER');

        $request->validate([
            'worker_id' => [$callerIsWorker ? 'nullable' : 'required', 'integer', 'exists:users,id'],
            'quantity' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'worker_description' => 'nullable|string|max:500',
            'media' => 'nullable|array',
            'media.*' => 'file|mimes:jpg,jpeg,png,webp,pdf,mp4,mov,webm|max:51200',
        ]);

        // A worker submitting for themself doesn't need to pass worker_id; anyone else
        // (e.g. an owner entering it on a worker's behalf) must specify it explicitly.
        $worker = $request->filled('worker_id') ? User::find($request->worker_id) : $request->user();

        if (! $worker->hasRole('WORKER')) {
            return response()->json(['message' => 'Selected user is not a worker.'], 422);
        }

        // A worker can only submit for a task actually assigned to them; an explicit
        // worker_id (owner/admin entry) skips this check.
        if ($callerIsWorker && ! $request->filled('worker_id') && $task->assigned_worker_id !== $worker->id) {
            return response()->json(['message' => 'This task is not assigned to you.'], 403);
        }

        try {
            $submission = Submission::create([
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'worker_id' => $worker->id,
                'quantity' => $request->quantity,
                'unit' => $request->unit,
                'worker_description' => $request->worker_description,
                'status' => Submission::STATUSES['pending'],
            ]);

            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $path = $file->store("submission_media/{$submission->id}", 'public');
                    $submission->media()->create([
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            $task->update(['status' => Task::STATUSES['submitted']]);

            ActivityLog::record($task->project_id, 'submission_created', 'Submission for "'.$task->title.'" received from '.$worker->full_name, $request->user()->id);

            UserNotification::notifyOwners(
                $task->project,
                'task',
                'New Submission Received',
                $worker->full_name.' submitted work for "'.$task->title.'".',
                "/submissions/{$submission->id}"
            );

            return response()->json([
                'message' => 'Submission created successfully.',
                'data' => $this->detailResource($submission->fresh()),
            ], 201);

        } catch (\Exception $e) {
            Log::error('API create submission: '.$e->getMessage());

            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    /**
     * Supervisor starts reviewing a submission (Pending → In Review). Caller must have the
     * SUPERVISOR role. Optional step before verify() — verify() also accepts a submission
     * still in Pending, so this isn't required to reach Pending Final Approval.
     *
     * POST /api/v1/submissions/{id}/start-review
     */
    public function startReview(Request $request, int $id): JsonResponse
    {
        $submission = $this->findAccessibleSubmission($request, $id);

        if (! $submission) {
            return response()->json(['message' => 'Submission not found or you do not have access.'], 404);
        }

        if (! $request->user()->hasRole('SUPERVISOR')) {
            return response()->json(['message' => 'Only a supervisor can review a submission.'], 403);
        }

        if ($submission->status !== Submission::STATUSES['pending']) {
            return response()->json(['message' => 'Only a pending submission can be moved to In Review.'], 422);
        }

        $submission->update([
            'supervisor_id' => $request->user()->id,
            'status' => Submission::STATUSES['in_review'],
        ]);

        ActivityLog::record($submission->project_id, 'submission_in_review', $request->user()->full_name.' started reviewing submission for "'.$submission->task->title.'"', $request->user()->id);

        return response()->json([
            'message' => 'Submission marked as in review.',
            'data' => $this->detailResource($submission->fresh()),
        ]);
    }

    /**
     * Supervisor verification — adds a comment and moves the submission to Pending Final Approval.
     * Caller must have the SUPERVISOR role.
     *
     * POST /api/v1/submissions/{id}/verify
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $submission = $this->findAccessibleSubmission($request, $id);

        if (! $submission) {
            return response()->json(['message' => 'Submission not found or you do not have access.'], 404);
        }

        if (! $request->user()->hasRole('SUPERVISOR')) {
            return response()->json(['message' => 'Only a supervisor can verify a submission.'], 403);
        }

        $request->validate([
            'supervisor_comment' => 'required|string',
        ]);

        $submission->update([
            'supervisor_id' => $request->user()->id,
            'supervisor_comment' => $request->supervisor_comment,
            'supervisor_reviewed_at' => now(),
            'status' => Submission::STATUSES['pending_final_approval'],
        ]);

        ActivityLog::record($submission->project_id, 'submission_verified', 'Submission for "'.$submission->task->title.'" verified by supervisor', $request->user()->id);

        UserNotification::notifyOwners(
            $submission->project,
            'action_required',
            'Submission Ready for Final Approval',
            'Supervisor verification complete for "'.$submission->task->title.'".',
            "/submissions/{$submission->id}"
        );

        return response()->json([
            'message' => 'Submission verified successfully.',
            'data' => $this->detailResource($submission->fresh()),
        ]);
    }

    /**
     * Supervisor returns work directly to the worker — no owner involvement required.
     * Only allowed while the submission is still Pending or In Review (i.e. before the
     * supervisor has verified and forwarded it to the owner).
     *
     * POST /api/v1/submissions/{id}/return-to-worker
     */
    public function returnToWorker(Request $request, int $id): JsonResponse
    {
        $submission = $this->findAccessibleSubmission($request, $id);

        if (! $submission) {
            return response()->json(['message' => 'Submission not found or you do not have access.'], 404);
        }

        if (! $request->user()->hasRole('SUPERVISOR')) {
            return response()->json(['message' => 'Only a supervisor can return a submission.'], 403);
        }

        if (! in_array($submission->status, [Submission::STATUSES['pending'], Submission::STATUSES['in_review']], true)) {
            return response()->json(['message' => 'This submission has already moved past supervisor review.'], 422);
        }

        $request->validate([
            'supervisor_comment' => 'required|string',
        ]);

        $submission->update([
            'supervisor_id' => $request->user()->id,
            'supervisor_comment' => $request->supervisor_comment,
            'supervisor_reviewed_at' => now(),
            'status' => Submission::STATUSES['revision_requested'],
        ]);

        $submission->task->update(['status' => Task::STATUSES['returned']]);

        ActivityLog::record($submission->project_id, 'submission_returned', 'Submission for "'.$submission->task->title.'" returned to worker by supervisor', $request->user()->id);

        if ($submission->worker_id) {
            UserNotification::notifyUser(
                $submission->worker_id,
                $submission->project_id,
                'task',
                'Task Returned',
                'Your submission for "'.$submission->task->title.'" needs changes: '.$request->supervisor_comment,
                "/submissions/{$submission->id}"
            );
        }

        return response()->json([
            'message' => 'Submission returned to worker.',
            'data' => $this->detailResource($submission->fresh()),
        ]);
    }

    /**
     * Final approve (Owner action).
     *
     * POST /api/v1/submissions/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->decide($request, $id, 'approved', 'submission_approved', 'approved', false);
    }

    /**
     * Reject (Owner action).
     *
     * POST /api/v1/submissions/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        return $this->decide($request, $id, 'rejected', 'submission_rejected', 'rejected', true);
    }

    /**
     * Request revision (Owner action).
     *
     * POST /api/v1/submissions/{id}/request-revision
     */
    public function requestRevision(Request $request, int $id): JsonResponse
    {
        return $this->decide($request, $id, 'revision_requested', 'submission_revision_requested', 'sent back for revision', true);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function decide(Request $request, int $id, string $status, string $logType, string $logVerb, bool $commentRequired): JsonResponse
    {
        $submission = $this->findAccessibleSubmission($request, $id);

        if (! $submission) {
            return response()->json(['message' => 'Submission not found or you do not have access.'], 404);
        }

        $request->validate([
            'comment' => ($commentRequired ? 'required' : 'nullable').'|string',
        ]);

        $submission->update([
            'status' => Submission::STATUSES[$status],
            'owner_comment' => $request->comment,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $taskStatus = match ($status) {
            'approved' => Task::STATUSES['completed'],
            default => Task::STATUSES['returned'], // rejected, revision_requested
        };
        $submission->task->update(['status' => $taskStatus]);

        ActivityLog::record($submission->project_id, $logType, 'Submission for "'.$submission->task->title.'" '.$logVerb, $request->user()->id);

        if ($submission->worker_id) {
            [$title, $body] = match ($status) {
                'approved' => ['Submission Approved', 'Your submission for "'.$submission->task->title.'" has been approved.'],
                'rejected' => ['Submission Rejected', 'Your submission for "'.$submission->task->title.'" was rejected. '.$request->comment],
                default => ['Task Returned', 'Your submission for "'.$submission->task->title.'" needs changes: '.$request->comment],
            };

            UserNotification::notifyUser($submission->worker_id, $submission->project_id, 'task', $title, $body, "/submissions/{$submission->id}");
        }

        return response()->json([
            'message' => 'Submission updated successfully.',
            'data' => $this->detailResource($submission->fresh()),
        ]);
    }

    private function findAccessibleSubmission(Request $request, int $id): ?Submission
    {
        $submission = Submission::with(['task', 'project', 'worker', 'supervisor', 'reviewer', 'media'])->find($id);

        if (! $submission) {
            return null;
        }

        $hasAccess = $request->user()->projects()->where('projects.id', $submission->project_id)->exists();

        return $hasAccess ? $submission : null;
    }

    private function code(Submission $submission): string
    {
        return 'SUB-'.str_pad((string) $submission->id, 4, '0', STR_PAD_LEFT);
    }

    private function listResource(Submission $submission): array
    {
        return [
            'id' => $submission->id,
            'code' => $this->code($submission),
            'task_id' => $submission->task_id,
            'task_title' => $submission->task?->title,
            'worker' => $submission->worker ? [
                'id' => $submission->worker->id,
                'full_name' => $submission->worker->full_name,
                'title' => $submission->worker->title,
            ] : null,
            'status' => array_search($submission->status, Submission::STATUSES, true) ?: null,
            'status_label' => Submission::STATUS_LABELS[$submission->status] ?? null,
            'submitted_at' => $submission->created_at?->toDateTimeString(),
        ];
    }

    private function detailResource(Submission $submission): array
    {
        $submission->loadMissing(['task', 'worker', 'supervisor', 'reviewer', 'media']);

        return [
            'id' => $submission->id,
            'code' => $this->code($submission),
            'task' => $submission->task ? ['id' => $submission->task->id, 'title' => $submission->task->title] : null,
            'project_id' => $submission->project_id,
            'worker' => $submission->worker ? [
                'id' => $submission->worker->id,
                'full_name' => $submission->worker->full_name,
                'title' => $submission->worker->title,
            ] : null,
            'quantity' => $submission->quantity,
            'unit' => $submission->unit,
            'worker_description' => $submission->worker_description,
            'status' => array_search($submission->status, Submission::STATUSES, true) ?: null,
            'status_label' => Submission::STATUS_LABELS[$submission->status] ?? null,
            'supervisor_verification' => $submission->supervisor_id ? [
                'supervisor' => [
                    'id' => $submission->supervisor->id,
                    'full_name' => $submission->supervisor->full_name,
                    'title' => $submission->supervisor->title,
                    'profile_photo' => $submission->supervisor->profile_photo ? url('storage/'.$submission->supervisor->profile_photo) : null,
                ],
                'comment' => $submission->supervisor_comment,
                'reviewed_at' => $submission->supervisor_reviewed_at?->toDateTimeString(),
            ] : null,
            'owner_decision' => $submission->reviewed_by ? [
                'reviewer' => ['id' => $submission->reviewer->id, 'full_name' => $submission->reviewer->full_name],
                'comment' => $submission->owner_comment,
                'reviewed_at' => $submission->reviewed_at?->toDateTimeString(),
            ] : null,
            'media' => $submission->media->map(fn ($m) => [
                'id' => $m->id,
                'file_name' => $m->file_name,
                'url' => url('storage/'.$m->file_path),
            ]),
            'submitted_at' => $submission->created_at?->toDateTimeString(),
            'updated_at' => $submission->updated_at?->toDateTimeString(),
        ];
    }
}
