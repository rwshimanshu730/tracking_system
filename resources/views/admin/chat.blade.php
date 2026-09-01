@extends('layouts.app')

@section('content')
    <div style="max-width:1000px;margin:20px auto;">
        <h2>Admin — Messages</h2>
        <div>@include('admin._chat_panel', ['employees' => $employees, 'customers' => $customers ?? []])</div>
    </div>
@endsection
