<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Customer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
  public function index(): View
{
    $roleFilter = request('role');
    $query = User::orderBy('name');

    if ($roleFilter) {
        $query->where('role', $roleFilter);
    } else {
        // 🔥 default me customer hata do
        $query->whereIn('role', ['admin', 'manager', 'user']);
    }

    return view('users.index', [
        'pageTitle' => 'User Management',
        'users' => $query->paginate(12)->withQueryString(),
        'roleFilter' => $roleFilter,
    ]);
}



    public function create(): View
    {
        return view('users.form', [
            'pageTitle' => 'Add User',
            'user' => new User(),
            'formAction' => route('users.store'),
            'formMethod' => 'POST',
        ]);
    }
	


    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $payload['password'] = Hash::make($payload['password']);

        User::create($payload);

        return redirect()->route('users.index')->with('status', 'User created successfully.');
    }
	

    public function edit(User $user): View
    {
        return view('users.form', [
            'pageTitle' => 'Edit User',
            'user' => $user,
            'formAction' => route('users.update', $user),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $payload = $this->validatedData($request, $user);

        if (! empty($payload['password'])) {
            $payload['password'] = Hash::make($payload['password']);
        } else {
            unset($payload['password']);
        }

        $user->update($payload);

        return redirect()->route('users.index')->with('status', 'User updated successfully.');
    }
	
	

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(auth()->user())) {
            return redirect()->route('users.index')->with('status', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User deleted successfully.');
    }


public function customerIndex(): View
{
    $customers = Customer::orderBy('name')
        ->paginate(12);

    return view('customers.index', [
        'pageTitle' => 'Customer Management',
        'users' => $customers, // view same use kar rahe ho
        'roleFilter' => 'customer',
    ]);
}
	
		public function customercreate(): View
    {
        return view('customers.form', [
            'pageTitle' => 'Add Customer',
            'user' => new User(),
            'formAction' => route('customer.store'),
            'formMethod' => 'POST',
        ]);
    }
	
	
	
	
     public function customerstore(Request $request): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $payload['password'] = Hash::make($payload['password']);

        $customer = Customer::create($payload);

        Password::broker('customers')->sendResetLink([
            'email' => $customer->email,
        ]);

        return redirect()->route('customer.index')->with('status', 'Customer created successfully and reset password email sent.');
    }
	
	
	public function customeredit(Customer $customer): View
{
    return view('customers.form', [
        'pageTitle' => 'Edit Customer',
        'user' => $customer, // 👈 blade ke hisaab se user naam rakh diya
        'formAction' => route('customer.update', $customer),
        'formMethod' => 'PUT',
    ]);
}
	
	 public function customerupdate(Request $request, Customer $customer): RedirectResponse
    {
        $payload = $this->validatedCustomerData($request, $customer);

        if (! empty($payload['password'])) {
            $payload['password'] = Hash::make($payload['password']);
        } else {
            unset($payload['password']);
        }

        $customer->update($payload);

        return redirect()->route('customer.index')->with('status', 'customer updated successfully.');
    }
	
	
public function customerdestroy(Customer $customer): RedirectResponse
{
    
    $customer->delete();

    return redirect()->route('customer.index')
        ->with('status', 'Customer deleted successfully.');
}

    public function sendCustomerResetPassword(Customer $customer): RedirectResponse
    {
        $status = Password::broker('customers')->sendResetLink([
            'email' => $customer->email,
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return redirect()->route('customer.index')
                ->with('status', 'Unable to send reset password email right now.');
        }

        return redirect()->route('customer.index')
            ->with('status', 'Reset password email sent to '.$customer->email.'.');
    }


    private function validatedData(Request $request, ?User $user = null): array
    {
        $passwordRules = $user
            ? ['nullable', 'confirmed', 'min:8']
            : ['required', 'confirmed', 'min:8'];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(['admin', 'manager', 'user', 'customer'])],
            'password' => $passwordRules,
        ]);
    }
	
	private function validatedCustomerData(Request $request, ?Customer $customer = null): array
{
    $passwordRules = $customer
        ? ['nullable', 'confirmed', 'min:8']
        : ['required', 'confirmed', 'min:8'];

    return $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => [
            'required',
            'email',
            'max:255',
            Rule::unique('customers', 'email')->ignore($customer?->id)
        ],
        'password' => $passwordRules,
    ]);
}
}
