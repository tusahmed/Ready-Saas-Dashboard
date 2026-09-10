<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Access;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $q = trim($request->input('q', ''));
        $users = collect();
        $roles = collect();
        $clients = collect();
        if ($q !== '') {
            $pattern = '%'.$q.'%';
            if ($request->user()->canDo('users.view')) {
                $users = Access::users($request->user())->with('role')->where(fn ($query) => $query->where('name', 'like', $pattern)->orWhere('email', 'like', $pattern))->limit(20)->get();
            }
            if ($request->user()->canDo('roles.view')) {
                $roles = Access::roles($request->user())->where('name', 'like', $pattern)->withCount('users')->limit(20)->get();
            }
            if ($request->user()->isPlatform() && $request->user()->canDo('clients.view')) {
                $clients = Tenant::where(fn ($query) => $query->where('name', 'like', $pattern)->orWhere('email', 'like', $pattern))->limit(20)->get();
            }
        }

        return view('search.index', compact('q', 'users', 'roles', 'clients'));
    }
}
