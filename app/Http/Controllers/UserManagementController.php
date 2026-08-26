<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class UserManagementController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->orderByDesc('role')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::query()->create([
            ...$request->validated(),
            'role' => User::ROLE_OPERATOR,
        ]);

        return to_route('users.index')->with('status', 'Usuário operador criado com sucesso.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->role === User::ROLE_OPERATOR, 403);

        $attributes = $request->validated();

        if (($attributes['password'] ?? null) === null) {
            unset($attributes['password']);
        }

        $user->update($attributes);

        return to_route('users.index')->with('status', 'Usuário operador atualizado com sucesso.');
    }
}
