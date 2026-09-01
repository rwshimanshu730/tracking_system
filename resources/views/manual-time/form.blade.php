@extends('layouts.app')

@section('content')
    <section class="content-stack">
        <div class="section-banner">
            <div>
                <p class="eyebrow">Manual Entry Form</p>
                <h3>{{ $pageTitle }}</h3>
            </div>
        </div>

        <section class="panel-card">
            <form method="POST" action="{{ $formAction }}" class="form-grid">
                @csrf
                @if ($formMethod !== 'POST')
                    @method($formMethod)
                @endif

                <label class="form-field">
                    <span>Employee</span>
                    <select name="employee_id">
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) old('employee_id', $entry->employee_id) === (string) $employee->id)>{{ $employee->name }} ({{ $employee->employee_code }})</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-field">
                    <span>Date</span>
                    <input type="date" name="entry_date" value="{{ old('entry_date', optional($entry->entry_date)->format('Y-m-d')) }}" required>
                </label>

                <label class="form-field">
                    <span>Minutes</span>
                    <input type="number" min="1" max="1440" name="minutes" value="{{ old('minutes', $entry->minutes) }}" required>
                </label>

                <label class="form-field">
                    <span>Entry Type</span>
                    <select name="entry_type">
                        @foreach (['productive' => 'Productive', 'idle' => 'Idle', 'meeting' => 'Meeting', 'break' => 'Break', 'manual_adjustment' => 'Manual Adjustment'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('entry_type', $entry->entry_type ?: 'manual_adjustment') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-field" style="grid-column: 1 / -1;">
                    <span>Reason</span>
                    <input type="text" name="reason" value="{{ old('reason', $entry->reason) }}">
                </label>

                <div class="form-actions">
                    <button type="submit" class="button-primary">Save Entry</button>
                    <a href="{{ route('manual-time.index') }}" class="button-secondary">Back</a>
                </div>
            </form>
        </section>
    </section>
@endsection
