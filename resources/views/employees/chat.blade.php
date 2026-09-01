@extends('layouts.employee')

@section('content')
    <div style="max-width:1000px;margin:20px auto;">
        <h2>Messages</h2>
        <div>@include('employees._chat_panel', ['admins' => $admins])</div>
    </div>
@endsection
