<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Employee;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Http\Request;

class AdminMessageController extends Controller
{
    public function show()
    {
        $employees = Employee::query()->orderBy('name')->get();
        $customers = Customer::query()->orderBy('name')->get();
        return view('admin.chat', ['employees' => $employees, 'customers' => $customers]);
    }

    public function index(Request $request)
    {
        $actor = auth()->user();
        if (! $actor || ! ($actor instanceof User) || ! in_array($actor->role, ['admin', 'manager'], true)) {
            abort(403);
        }

        $withType = $request->query('with_type');
        $withId = (int) $request->query('with_id');

        $map = ['user' => User::class, 'employee' => Employee::class, 'customer' => Customer::class];

        $query = Comment::query();

        $actorType = User::class;
        $actorId = $actor->id;

        if ($withType && $withId && isset($map[$withType])) {
            $otherType = $map[$withType];
            $otherId = $withId;

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
        } else {
            return response()->json(['messages' => []]);
        }

        $messages = $query->orderBy('created_at')->get();
        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request)
    {
        $actor = auth()->user();
        if (! $actor || ! ($actor instanceof User) || ! in_array($actor->role, ['admin', 'manager'], true)) {
            abort(403);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'recipient_type' => ['required', 'string', 'in:employee,user,customer'],
            'recipient_id' => ['required', 'integer'],
        ]);

        $map = ['user' => User::class, 'employee' => Employee::class, 'customer' => Customer::class];
        $recipientType = $map[$data['recipient_type']];
        $recipientId = $data['recipient_id'];

        if (! $recipientType::where('id', $recipientId)->exists()) {
            abort(422, 'Recipient not found');
        }

        $comment = Comment::create([
            'sender_type' => User::class,
            'sender_id' => $actor->id,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'body' => $data['body'],
            'role' => 'admin',
        ]);

        return response()->json(['success' => true, 'message' => $comment], 201);
    }
}
