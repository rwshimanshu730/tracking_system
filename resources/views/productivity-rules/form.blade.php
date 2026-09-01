@extends('layouts.app')

@section('content')
    <section class="content-stack">
        <div class="section-banner">
            <div>
                <p class="eyebrow">Rule Form</p>
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
                    <span>Match Type</span>
                    <select name="match_type">
                        @foreach (['app_name' => 'Application Name', 'window_title' => 'Window Title', 'domain' => 'Domain'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('match_type', $rule->match_type ?: 'app_name') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-field">
                    <span>Match Value</span>
                    <input type="text" name="match_value" value="{{ old('match_value', $rule->match_value) }}" required>
                </label>

                <label class="form-field">
                    <span>Category</span>
                    <input type="text" name="category" value="{{ old('category', $rule->category) }}" required>
                </label>

                <label class="form-field">
                    <span>Productivity Type</span>
                    <select name="productivity_type">
                        @foreach (['productive' => 'Productive', 'neutral' => 'Neutral', 'unproductive' => 'Unproductive'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('productivity_type', $rule->productivity_type ?: 'productive') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-check">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule->is_active ?? true))>
                    <span>Rule is active</span>
                </label>

                <div class="form-actions">
                    <button type="submit" class="button-primary">Save Rule</button>
                    <a href="{{ route('productivity-rules.index') }}" class="button-secondary">Back</a>
                </div>
            </form>
        </section>
    </section>
@endsection
