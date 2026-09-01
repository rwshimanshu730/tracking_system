<!-- Customer chat page -->
@extends('layouts.customer')

@section('content')
    <div style="max-width:1000px;margin:20px auto;">
        <h2>Customer Messages</h2>
        <div>@include('customers._chat_panel', ['admins' => $admins])</div>
    </div>
@endsection
