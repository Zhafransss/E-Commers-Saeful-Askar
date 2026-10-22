<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminUserDetailResource;
use App\Http\Resources\Admin\AdminUserSummaryResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/admin.md. The customers are shown, never changed: there is no write endpoint here.
 */
class UserController extends Controller
{
    /** Orders that were paid, whatever happened to them afterwards. */
    private const PAID = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed];

    /**
     * ADM-18 · Daftar pelanggan (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = $this->customers()
            ->when(trim((string) ($filters['q'] ?? '')), function ($query, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
            })
            ->latest('created_at')->latest('id')
            ->paginate($request->integer('per_page', 20));

        return AdminUserSummaryResource::collection($users)->response();
    }

    /**
     * ADM-19 · Detail pelanggan (admin)
     */
    public function show(int $id): JsonResponse
    {
        $user = $this->customers()
            ->with(['addresses' => fn ($q) => $q->orderByDesc('is_default')->orderByDesc('id'), 'orders' => fn ($q) => $q->latest('created_at')->latest('id')->limit(10)])
            ->find($id) ?? throw new ApiException('Pelanggan tidak ditemukan.', 'USER_NOT_FOUND', 404);

        return (new AdminUserDetailResource($user))->response();
    }

    /**
     * Customers only: another admin is not "a customer", and its id answers 404 like any unknown one.
     */
    private function customers()
    {
        return User::query()
            ->where('role', UserRole::Customer->value)
            ->withCount('orders')
            ->withCount(['orders as paid_orders_count' => fn ($q) => $q->whereIn('status', self::PAID)])
            ->withSum(['orders as paid_orders_sum_grand_total' => fn ($q) => $q->whereIn('status', self::PAID)], 'grand_total');
    }
}
