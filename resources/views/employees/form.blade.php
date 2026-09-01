@extends('layouts.app')

@section('content')
    <section class="content-stack">
      

        <section class="panel-card">
            <form method="POST" action="{{ $formAction }}" class="form-grid">
                @csrf
                @if ($formMethod !== 'POST')
                    @method($formMethod)
                @endif

                <label class="form-field">
                    <span>Employee Code</span>
                    <input type="text" name="employee_code" value="{{ old('employee_code', $employee->employee_code) }}" required>
                    @error('employee_code')<small>{{ $message }}</small>@enderror
                </label>

                <label class="form-field">
                    <span>Name</span>
                    <input type="text" name="name" value="{{ old('name', $employee->name) }}" required>
                    @error('name')<small>{{ $message }}</small>@enderror
                </label>

                <label class="form-field">
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email', $employee->email) }}">
                    @error('email')<small>{{ $message }}</small>@enderror
                </label>
				
				 <label class="form-field">
                    <span>Password</span>
                    <input type="password" name="password" {{ $formMethod === 'POST' ? 'required' : '' }}>
                    @error('password')<small>{{ $message }}</small>@enderror
                </label>

                <label class="form-field">
                    <span>Confirm Password</span>
                    <input type="password" name="password_confirmation" {{ $formMethod === 'POST' ? 'required' : '' }}>
                </label>

                <label class="form-field">
                    <span>Department</span>
                    <input type="text" name="department" value="{{ old('department', $employee->department) }}">
                </label>

                <label class="form-field">
                    <span>Designation</span>
                    <input type="text" name="designation" value="{{ old('designation', $employee->designation) }}">
                </label>

                <label class="form-field">
                    <span>Status</span>
                    <select name="employment_status">
                        @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'on_leave' => 'On Leave'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('employment_status', $employee->employment_status ?: 'active') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-field">
                    <span>Joined On</span>
                    <input type="date" name="joined_on" value="{{ old('joined_on', optional($employee->joined_on)->format('Y-m-d')) }}">
                </label>

                <div class="form-actions">
                    <button type="submit" class="button-primary">Save Employee</button>
                    <a href="{{ route('employees.manage') }}" class="button-secondary">Back</a>
                </div>
            </form>
        </section>
    </section>
@endsection
