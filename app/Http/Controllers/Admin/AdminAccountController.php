<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use App\Http\Resources\Admin\AdminAccountResource;
use App\Models\User;
use App\Support\UserSessions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/admin.md ADM-25 – ADM-28. The store's own admin accounts; customers are ADM-18 / ADM-19.
 *
 * An admin cannot delete their own account, so at least one admin is always left to log in.
 */
class AdminAccountController extends Controller
{
    /**
     * ADM-25 · Daftar admin
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $admins = $this->admins()
            ->when(trim((string) ($filters['q'] ?? '')), function ($query, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderBy('created_at')->orderBy('id')
            ->paginate($request->integer('per_page', 20));

        return AdminAccountResource::collection($admins)->response();
    }

    /**
     * ADM-26 · Tambah admin
     */
    public function store(StoreAdminRequest $request): JsonResponse
    {
        $admin = new User($request->safe()->only(['name', 'email', 'password']));
        $admin->forceFill(['role' => UserRole::Admin, 'email_verified_at' => now()])->save();

        return (new AdminAccountResource($admin))->response()->setStatusCode(201);
    }

    /**
     * ADM-27 · Ubah admin
     */
    public function update(UpdateAdminRequest $request, int $id): JsonResponse
    {
        $admin = $this->find($id);
        $data = $request->safe()->only(['name', 'email', 'password']);

        $admin->fill(array_filter($data, fn ($value) => $value !== null))->save();

        // A new password signs the account out everywhere else; the admin who changed their own stays logged in.
        if (isset($data['password'])) {
            UserSessions::endAll($admin, $admin->is($request->user()) && $request->hasSession() ? $request->session()->getId() : null);
        }

        return (new AdminAccountResource($admin))->response();
    }

    /**
     * ADM-28 · Hapus admin
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $admin = $this->find($id);

        if ($admin->is($request->user())) {
            throw new ApiException('Anda tidak bisa menghapus akun Anda sendiri.', 'CANNOT_DELETE_SELF', 409);
        }

        UserSessions::endAll($admin);
        $admin->delete();

        return response()->json(['message' => 'Admin dihapus.']);
    }

    private function admins()
    {
        return User::query()->where('role', UserRole::Admin->value);
    }

    /**
     * Admins only: a customer's id answers 404 here like any unknown one.
     */
    private function find(int $id): User
    {
        return $this->admins()->find($id) ?? throw new ApiException('Admin tidak ditemukan.', 'ADMIN_NOT_FOUND', 404);
    }
}
