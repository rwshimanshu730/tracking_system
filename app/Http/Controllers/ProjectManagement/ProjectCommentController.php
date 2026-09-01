<?php

namespace App\Http\Controllers\ProjectManagement;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectChatDraft;
use App\Models\Customer;
use App\Models\User;
use App\Mail\ProjectChatMessageMail;
use App\Mail\ProjectChatCustomerThanksMail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectCommentController extends Controller
{
    public function index(Project $project)
    {
        $this->authorizeAccess($project);

        $withType = request()->query('with_type');
        $withId = request()->query('with_id');

        $query = $project->comments()->with(['sender', 'recipient']);

        if ($withType && $withId) {
            $map = [
                'user' => User::class,
                'customer' => Customer::class,
                'employee' => Employee::class,
            ];

            if (! isset($map[$withType])) {
                abort(400, 'Invalid recipient type');
            }

            $otherType = $map[$withType];
            $otherId = (int) $withId;

            $actor = $this->currentActor();
            $actorType = null; $actorId = null;
            if ($actor) {
                if ($actor instanceof User) {
                    if (method_exists($actor, 'hasRole') && $actor->hasRole('user')) {
                        $employee = Employee::query()->where('email', $actor->email)->first();
                        if ($employee) {
                            $actorType = Employee::class; $actorId = $employee->id;
                        } else {
                            $actorType = User::class; $actorId = $actor->id;
                        }
                    } else {
                        $actorType = User::class; $actorId = $actor->id;
                    }
                } elseif ($actor instanceof Customer) {
                    $actorType = Customer::class; $actorId = $actor->id;
                } elseif ($actor instanceof Employee) {
                    $actorType = Employee::class; $actorId = $actor->id;
                }
            }

            if (! $actorType || ! $actorId) {
                abort(403);
            }

            $query->where(function ($q) use ($actorType, $actorId, $otherType, $otherId) {
                $q->where(function ($q2) use ($actorType, $actorId, $otherType, $otherId) {
                    $q2->where('sender_type', $actorType)
                        ->where('sender_id', $actorId)
                        ->where('recipient_type', $otherType)
                        ->where('recipient_id', $otherId);
                })->orWhere(function ($q2) use ($actorType, $actorId, $otherType, $otherId) {
                    $q2->where('sender_type', $otherType)
                        ->where('sender_id', $otherId)
                        ->where('recipient_type', $actorType)
                        ->where('recipient_id', $actorId);
                });
            });
        }

        $comments = $query->orderBy('created_at')->get();

        return response()->json([
            'comments' => $comments->map(fn (Comment $comment) => $this->serializeComment($project, $comment))->values(),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $this->authorizeAccess($project);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
            'recipient_type' => ['nullable', 'string', 'in:employee,customer,user'],
            'recipient_id' => ['nullable', 'integer'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
            'notify_teammates' => ['nullable', 'boolean'],
            'notify_customers' => ['nullable', 'boolean'],
        ]);

        $actor = $this->currentActor();
        [$senderType, $senderId] = $this->resolveActorIdentity($actor);

        abort_unless($senderType && $senderId, 403);

        $recipientClass = null;
        if (! empty($data['recipient_type']) && ! empty($data['recipient_id'])) {
            $map = [
                'user' => User::class,
                'customer' => Customer::class,
                'employee' => Employee::class,
            ];
            $recipientType = $data['recipient_type'];
            if (! isset($map[$recipientType])) {
                abort(400, 'Invalid recipient type');
            }
            $recipientClass = $map[$recipientType];
            // basic existence check
            if (! $recipientClass::where('id', $data['recipient_id'])->exists()) {
                abort(422, 'Recipient not found');
            }
        }

        $attachments = collect($request->file('attachments', []))
            ->filter()
            ->map(function ($uploadedFile) {
                $path = $uploadedFile->store('pm-chat');
                $mimeType = $uploadedFile->getMimeType() ?: 'application/octet-stream';

                return [
                    'disk' => 'local',
                    'file_path' => $path,
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'size_bytes' => $uploadedFile->getSize(),
                    'mime_type' => $mimeType,
                    'category' => Str::startsWith($mimeType, 'image/') ? 'media' : 'doc',
                ];
            })
            ->values()
            ->all();

        // If an admin posts a project comment directed to an employee,
        // treat it as a private 1:1 message so the employee receives it
        // in their private chat panel instead of the project comments.
        if ($recipientClass === Employee::class && $senderType === User::class && $actor && method_exists($actor, 'hasRole') && $actor->hasRole('admin', 'manager')) {
            $comment = Comment::create([
                'project_id' => $project->id,
                'sender_type' => User::class,
                'sender_id' => $senderId,
                'author_name' => $actor?->name ?? null,
                'parent_id' => null,
                'recipient_type' => $recipientClass,
                'recipient_id' => $data['recipient_id'],
                'body' => $data['body'],
                'role' => 'admin',
                'attachments' => $attachments,
            ]);

            $comment->load(['sender', 'recipient']);

            $this->dispatchCommentEmails($project, $comment, $actor, $data);

            return response()->json([
                'success' => true,
                'comment' => $this->serializeComment($project, $comment),
            ], 201);
        }

        $comment = Comment::create([
            'project_id' => $project->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'author_name' => $actor?->name ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'recipient_type' => $recipientClass ?? null,
            'recipient_id' => $data['recipient_id'] ?? null,
            'body' => $data['body'],
            'role' => $this->detectRole($actor),
            'attachments' => $attachments,
        ]);

        $comment->load(['sender', 'recipient']);

        ProjectChatDraft::query()
            ->where('project_id', $project->id)
            ->where('sender_type', $senderType)
            ->where('sender_id', $senderId)
            ->delete();

        $this->dispatchCommentEmails($project, $comment, $actor, $data);

        return response()->json([
            'success' => true,
            'comment' => $this->serializeComment($project, $comment),
        ], 201);
    }

    public function showDraft(Project $project)
    {
        $this->authorizeAccess($project);

        $actor = $this->currentActor();
        [$senderType, $senderId] = $this->resolveActorIdentity($actor);
        abort_unless($senderType && $senderId, 403);

        $draft = ProjectChatDraft::query()
            ->where('project_id', $project->id)
            ->where('sender_type', $senderType)
            ->where('sender_id', $senderId)
            ->first();

        return response()->json([
            'body' => (string) ($draft?->body ?? ''),
        ]);
    }

    public function saveDraft(Request $request, Project $project)
    {
        $this->authorizeAccess($project);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        $actor = $this->currentActor();
        [$senderType, $senderId] = $this->resolveActorIdentity($actor);
        abort_unless($senderType && $senderId, 403);

        $body = trim((string) ($data['body'] ?? ''));

        if ($body === '') {
            ProjectChatDraft::query()
                ->where('project_id', $project->id)
                ->where('sender_type', $senderType)
                ->where('sender_id', $senderId)
                ->delete();

            return response()->json(['saved' => false]);
        }

        ProjectChatDraft::updateOrCreate(
            [
                'project_id' => $project->id,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
            ],
            [
                'body' => $data['body'],
            ]
        );

        return response()->json(['saved' => true]);
    }

    public function destroy(Project $project, Comment $comment): Response
    {
        $this->authorizeAccess($project);

        $actor = $this->currentActor();
        abort_unless($actor && method_exists($actor, 'hasRole') && $actor->hasRole('admin', 'manager'), 403);
        abort_unless($comment->project_id === $project->id, 404);

        $comment->delete();

        return response()->noContent();
    }

    public function attachment(Project $project, Comment $comment, int $attachmentIndex)
    {
        $this->authorizeAccess($project);
        abort_unless($comment->project_id === $project->id, 404);

        $attachments = collect($comment->attachments ?? []);
        $attachment = $attachments->get($attachmentIndex);

        abort_unless($attachment, 404);

        $disk = $attachment['disk'] ?? 'local';
        $path = $attachment['file_path'] ?? null;
        abort_unless($path && Storage::disk($disk)->exists($path), 404);

        $mimeType = $attachment['mime_type'] ?? Storage::disk($disk)->mimeType($path) ?? 'application/octet-stream';
        $downloadName = $attachment['original_name'] ?? basename($path);

        if (Str::startsWith($mimeType, 'image/')) {
            return response()->file(Storage::disk($disk)->path($path), [
                'Content-Type' => $mimeType,
            ]);
        }

        return Storage::disk($disk)->download($path, $downloadName);
    }

    private function detectRole($actor): ?string
    {
        if (! $actor || ! method_exists($actor, 'hasRole')) {
            return null;
        }
        if ($actor->hasRole('customer')) {
            return 'customer';
        }
        if ($actor->hasRole('admin', 'manager')) {
            return 'admin';
        }
        if ($actor->hasRole('user')) {
            return 'employee';
        }

        return null;
    }

    private function authorizeAccess(Project $project): void
    {
        $user = $this->currentActor();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('admin', 'manager')) {
            return;
        }

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('customer')) {
            abort_unless($project->customerMembers()->where('customers.id', $user->id)->exists(), 403);
            return;
        }

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('user')) {
            $employee = $this->resolveEmployeeModel($user);
            abort_unless($employee && $project->employeeMembers()->where('employees.id', $employee->id)->exists(), 403);
            return;
        }

        abort(403);
    }

    private function serializeComment(Project $project, Comment $comment): array
    {
        $attachments = collect($comment->attachments ?? [])->values()->map(function (array $attachment, int $index) use ($project, $comment) {
            $mimeType = $attachment['mime_type'] ?? 'application/octet-stream';

            return [
                'name' => $attachment['original_name'] ?? basename($attachment['file_path'] ?? 'file'),
                'size_bytes' => (int) ($attachment['size_bytes'] ?? 0),
                'mime_type' => $mimeType,
                'category' => $attachment['category'] ?? (Str::startsWith($mimeType, 'image/') ? 'media' : 'doc'),
                'url' => route($this->attachmentRouteName(), [$project, $comment, 'attachmentIndex' => $index]),
            ];
        })->all();

        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'author_name' => $comment->author_name ?: $comment->sender?->name ?: 'User',
            'created_at' => optional($comment->created_at)->format('d M Y h:i A'),
            'role' => $comment->role ?: $this->inferCommentRole($comment),
            'sender_type' => $comment->sender_type,
            'sender_id' => $comment->sender_id,
            'recipient_type' => $comment->recipient_type,
            'recipient_id' => $comment->recipient_id,
            'attachments' => $attachments,
            'links' => $this->extractLinks($comment->body),
        ];
    }

    private function inferCommentRole(Comment $comment): string
    {
        if (Str::contains((string) $comment->sender_type, 'Customer')) {
            return 'customer';
        }

        if (Str::contains((string) $comment->sender_type, 'Employee')) {
            return 'employee';
        }

        return 'admin';
    }

    private function dispatchCommentEmails(Project $project, Comment $comment, $actor, array $data): void
    {
        $isCustomerSender = $actor && method_exists($actor, 'hasRole') && $actor->hasRole('customer');

        if ($isCustomerSender) {
            $this->sendInternalProjectChatNotifications($project, $comment, $actor?->email ?? null);

            if (!empty($actor?->email)) {
                Mail::to($actor->email)->send(new ProjectChatCustomerThanksMail($project, $comment, $actor));
            }

            return;
        }

        if (! empty($data['notify_teammates'])) {
            $this->sendInternalProjectChatNotifications($project, $comment, $actor?->email ?? null);
        }

        if (! empty($data['notify_customers'])) {
            $this->sendProjectChatNotification(
                $this->projectCustomerEmails($project, $actor?->email ?? null),
                new ProjectChatMessageMail($project, $comment, 'customer', 'customer')
            );
        }
    }

    private function sendInternalProjectChatNotifications(Project $project, Comment $comment, ?string $excludeEmail = null): void
    {
        $this->sendProjectChatNotification(
            $this->projectEmployeeEmails($project, $excludeEmail),
            new ProjectChatMessageMail($project, $comment, 'team', 'employee')
        );

        $this->sendProjectChatNotification(
            $this->projectAdminEmails($excludeEmail),
            new ProjectChatMessageMail($project, $comment, 'team')
        );
    }

    private function projectEmployeeEmails(Project $project, ?string $excludeEmail = null): array
    {
        return $project->employeeMembers()
            ->whereNotNull('employees.email')
            ->pluck('employees.email')
            ->filter()
            ->reject(fn ($email) => $excludeEmail && strcasecmp((string) $email, (string) $excludeEmail) === 0)
            ->unique(fn ($email) => strtolower((string) $email))
            ->values()
            ->all();
    }

    private function projectAdminEmails(?string $excludeEmail = null): array
    {
        return User::query()
            ->whereIn('role', ['admin', 'manager'])
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->reject(fn ($email) => $excludeEmail && strcasecmp((string) $email, (string) $excludeEmail) === 0)
            ->unique(fn ($email) => strtolower((string) $email))
            ->values()
            ->all();
    }

    private function projectCustomerEmails(Project $project, ?string $excludeEmail = null): array
    {
        return $project->customerMembers()
            ->whereNotNull('customers.email')
            ->pluck('customers.email')
            ->filter()
            ->reject(fn ($email) => $excludeEmail && strcasecmp((string) $email, (string) $excludeEmail) === 0)
            ->unique(fn ($email) => strtolower((string) $email))
            ->values()
            ->all();
    }

    private function sendProjectChatNotification(array $emails, ProjectChatMessageMail $mailable): void
    {
        foreach ($emails as $email) {
            Mail::to($email)->send(clone $mailable);
        }
    }

    private function extractLinks(string $body): array
    {
        preg_match_all('/https?:\/\/[^\s<>"\']+/i', $body, $matches);

        return collect($matches[0] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    private function currentActor()
    {
        if (request()->routeIs('employee.*')) {
            return auth('employee')->user();
        }

        if (request()->routeIs('customer.*')) {
            return auth('customer')->user();
        }

        return auth()->user() ?? auth('employee')->user() ?? auth('customer')->user();
    }

    private function resolveEmployeeModel($user): ?Employee
    {
        if ($user instanceof Employee) {
            return $user;
        }

        if ($user instanceof User) {
            return Employee::query()->where('email', $user->email)->first();
        }

        return null;
    }

    private function resolveActorIdentity($actor): array
    {
        if ($actor instanceof User) {
            if (method_exists($actor, 'hasRole') && $actor->hasRole('user')) {
                $employee = Employee::query()->where('email', $actor->email)->first();

                if ($employee) {
                    return [Employee::class, $employee->id];
                }
            }

            return [User::class, $actor->id];
        }

        if ($actor instanceof Customer) {
            return [Customer::class, $actor->id];
        }

        if ($actor instanceof Employee) {
            return [Employee::class, $actor->id];
        }

        return [null, null];
    }

    private function attachmentRouteName(): string
    {
        if (request()->routeIs('employee.*')) {
            return 'employee.projects.comments.attachments.show';
        }

        if (request()->routeIs('customer.*')) {
            return 'customer.projects.comments.attachments.show';
        }

        return 'pm.projects.comments.attachments.show';
    }
}
