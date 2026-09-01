<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ManualTimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManualTimeEntryController extends Controller
{
    public function index(): View
    {
        return view('manual-time.index', [
            'pageTitle' => 'Manual Time Entries',
            'entries' => ManualTimeEntry::with(['employee', 'creator'])->latest('entry_date')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('manual-time.form', [
            'pageTitle' => 'Add Manual Time Entry',
            'entry' => new ManualTimeEntry(),
            'employees' => Employee::orderBy('name')->get(),
            'formAction' => route('manual-time.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ManualTimeEntry::create($this->validatedData($request) + [
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('manual-time.index')->with('status', 'Manual time entry created successfully.');
    }

    public function edit(ManualTimeEntry $manualTimeEntry): View
    {
        return view('manual-time.form', [
            'pageTitle' => 'Edit Manual Time Entry',
            'entry' => $manualTimeEntry,
            'employees' => Employee::orderBy('name')->get(),
            'formAction' => route('manual-time.update', $manualTimeEntry),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(Request $request, ManualTimeEntry $manualTimeEntry): RedirectResponse
    {
        $manualTimeEntry->update($this->validatedData($request));

        return redirect()->route('manual-time.index')->with('status', 'Manual time entry updated successfully.');
    }

    public function destroy(ManualTimeEntry $manualTimeEntry): RedirectResponse
    {
        $manualTimeEntry->delete();

        return redirect()->route('manual-time.index')->with('status', 'Manual time entry deleted successfully.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'entry_date' => ['required', 'date'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'entry_type' => ['required', 'in:productive,idle,meeting,break,manual_adjustment'],
            'reason' => ['nullable', 'string'],
        ]);
    }
}
