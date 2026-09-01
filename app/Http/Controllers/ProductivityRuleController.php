<?php

namespace App\Http\Controllers;

use App\Models\ProductivityRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductivityRuleController extends Controller
{
    public function index(): View
    {
        return view('productivity-rules.index', [
            'pageTitle' => 'Productivity Rules',
            'rules' => ProductivityRule::latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('productivity-rules.form', [
            'pageTitle' => 'Add Productivity Rule',
            'rule' => new ProductivityRule(),
            'formAction' => route('productivity-rules.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ProductivityRule::create($this->validatedData($request));

        return redirect()->route('productivity-rules.index')->with('status', 'Productivity rule created successfully.');
    }

    public function edit(ProductivityRule $productivityRule): View
    {
        return view('productivity-rules.form', [
            'pageTitle' => 'Edit Productivity Rule',
            'rule' => $productivityRule,
            'formAction' => route('productivity-rules.update', $productivityRule),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(Request $request, ProductivityRule $productivityRule): RedirectResponse
    {
        $productivityRule->update($this->validatedData($request));

        return redirect()->route('productivity-rules.index')->with('status', 'Productivity rule updated successfully.');
    }

    public function destroy(ProductivityRule $productivityRule): RedirectResponse
    {
        $productivityRule->delete();

        return redirect()->route('productivity-rules.index')->with('status', 'Productivity rule deleted successfully.');
    }

    private function validatedData(Request $request): array
    {
        $payload = $request->validate([
            'match_type' => ['required', 'in:app_name,window_title,domain'],
            'match_value' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'productivity_type' => ['required', 'in:productive,neutral,unproductive'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $payload['is_active'] = $request->boolean('is_active');

        return $payload;
    }
}
