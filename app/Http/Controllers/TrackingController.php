<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tracking;

class TrackingController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'app' => 'required|string',
            'window' => 'required|string',
            'time' => 'required|string',
        ]);

        Tracking::create($data);

        return response()->json(['status' => 'success']);
    }
}