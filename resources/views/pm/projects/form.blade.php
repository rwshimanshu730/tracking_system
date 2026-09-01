@extends('layouts.app')

@section('content')
    <section class="content-stack">
        <div class="section-banner">
         

        <section class="panel-card">
            <form method="POST" action="{{ $formAction }}" class="form-grid" enctype="multipart/form-data">
                @csrf
                @if ($formMethod !== 'POST')
                    @method($formMethod)
                @endif

                <label class="form-field">
                    <span>Project Name</span>
                    <input type="text" name="name" value="{{ old('name', $project->name) }}" required>
                </label>

                <label class="form-field">
                    <span>Status</span>
                    <select name="status">
                        @foreach (['planned', 'maintainance','in_progress', 'on_hold', 'completed'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $project->status ?? 'planned') === $status)>
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="form-field">
                    <span>Priority</span>
                    <select name="priority">
                        @foreach (['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', $project->priority ?? 'medium') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-field">
                    <span>Start Date</span>
                    <input type="date" name="start_date" value="{{ old('start_date', optional($project->start_date)->format('Y-m-d')) }}">
                </label>

                <label class="form-field">
                    <span>Due Date</span>
                    <input type="date" name="due_date" value="{{ old('due_date', optional($project->due_date)->format('Y-m-d')) }}">
                </label>

                <label class="form-field form-field-full">
                    <span>Description</span>
                    <textarea name="description" id="description" >{{ old('description', $project->description) }}</textarea>
                </label>

                <label class="form-field form-field-full">
                    <span>Assign Employees</span>
                    <select name="employee_members[]" multiple size="6">
                        @forelse ($employees as $employee)
                            <option value="{{ $employee->employee_id }}" @selected(in_array($employee->employee_id, old('employee_members', $selectedEmployees), true))>
                                {{ $employee->employee_name }} ({{ $employee->employee_code ?: 'No Code' }})
                            </option>
                        @empty
                            <option value="" disabled>No employees found.</option>
                        @endforelse
                    </select>
                </label>
				
                <label class="form-field form-field-full">
                    <span>Upload File</span>
                    <input type="file" name="project_files[]" multiple>
                    @error('project_files')<small>{{ $message }}</small>@enderror
                    @error('project_files.*')<small>{{ $message }}</small>@enderror

                    @if (($formMethod ?? 'POST') !== 'POST' && $project->exists)
                        @php($existingFiles = $project->files()->latest()->get())
                        @if ($existingFiles->isNotEmpty())
                            <div style="margin-top: 8px;">
                                <small style="display:block; margin-bottom: 6px;">Uploaded Files</small>
                                <ul style="margin: 0; padding-left: 18px;">
                                    @foreach ($existingFiles as $file)
                                        <li>
                                            <a href="{{ route('pm.files.download', $file) }}" target="_blank" rel="noopener">
                                                {{ $file->original_name }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endif
                </label>
				

                <label class="form-field form-field-full">
                    <span>Assign Customers</span>
                    <select name="customer_members[]" multiple size="6">
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(in_array($customer->id, old('customer_members', $selectedCustomers), true))>
                                {{ $customer->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <div class="form-actions">
                    <button type="submit" class="button-primary">Save Project</button>
                    <a href="{{ route('pm.projects.index') }}" class="button-secondary">Back</a>
                </div>
            </form>
        </section>
    </section>
	
	<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
	<script>
    ClassicEditor
        .create(document.querySelector('#description'))
		 height: '200px'
        .catch(error => {
            console.error(error);
        });
</script>
@endsection
