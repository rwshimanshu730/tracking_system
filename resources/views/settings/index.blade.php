@extends('layouts.app')

@section('content')
    <section class="content-stack">
        <div class="card-grid">
            @foreach ($settingsSections as $section)
                <article class="panel-card">
                    <p class="eyebrow">Configuration</p>
                    <h3>{{ $section['title'] }}</h3>
                    <p class="muted-copy">{{ $section['detail'] }}</p>
                </article>
            @endforeach
        </div>

        <section class="panel-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Phase 3</p>
                    <h3>Analytics Features</h3>
                </div>
            </div>

            <div class="usage-list">
                <div class="usage-row">
                    <div>
                        <strong>Productivity Rules</strong>
                        <p>Configure app or window title matching for productive, neutral, or unproductive categorization.</p>
                    </div>
                    <span>Enabled</span>
                </div>
                <div class="usage-row">
                    <div>
                        <strong>Manual Time Entries</strong>
                        <p>Managers can add manual work, break, meeting, or adjustment entries directly from the dashboard.</p>
                    </div>
                    <span>Enabled</span>
                </div>
                <div class="usage-row">
                    <div>
                        <strong>CSV Exports</strong>
                        <p>Daily summary, application usage, and manual time exports are available from the reports page.</p>
                    </div>
                    <span>Enabled</span>
                </div>
            </div>
        </section>
    </section>
@endsection
