<?php

namespace App\Services\Shop;

use App\Enums\ShippingVoucherType;
use App\Exceptions\ApiException;
use App\Models\ShippingVoucher;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Which shipping vouchers a customer may use (SHIP-02) and the checks ORD-01 runs before an order takes one.
 *
 * The rules: the voucher has started and not ended, the products' subtotal (before shipping) reaches its
 * minimum, and neither its total quota nor the customer's own limit is used up. One voucher per order.
 */
class ShippingVoucherService
{
    /**
     * Running vouchers the customer still has uses of, best first. The minimum spend is not checked here: the
     * checkout shows a voucher it does not reach yet, greyed out, as an incentive.
     *
     * @return Collection<int, ShippingVoucher>
     */
    public function availableFor(User $user): Collection
    {
        return ShippingVoucher::query()
            ->running()
            ->get()
            ->filter(fn (ShippingVoucher $voucher) => $voucher->remainingQuota() !== 0 && $voucher->remainingFor($user) !== 0)
            ->sortBy([
                fn (ShippingVoucher $a, ShippingVoucher $b) => ($b->type === ShippingVoucherType::FreeShipping) <=> ($a->type === ShippingVoucherType::FreeShipping),
                fn (ShippingVoucher $a, ShippingVoucher $b) => (int) $b->amount <=> (int) $a->amount,
                fn (ShippingVoucher $a, ShippingVoucher $b) => $a->min_subtotal <=> $b->min_subtotal,
                fn (ShippingVoucher $a, ShippingVoucher $b) => $a->id <=> $b->id,
            ])
            ->values();
    }

    /**
     * The voucher the customer chose, checked for this order. Called again inside the order's transaction with
     * the row locked ($lock), so two customers taking the last use at the same moment cannot both get it.
     */
    public function usable(int $voucherId, User $user, int $subtotal, bool $lock = false): ShippingVoucher
    {
        $voucher = ShippingVoucher::query()->when($lock, fn ($query) => $query->lockForUpdate())->find($voucherId);

        if ($voucher === null || ! $voucher->isRunning()) {
            throw $this->unavailable('Voucher ongkir ini sudah tidak berlaku. Pilih voucher lain atau lanjut tanpa voucher.');
        }

        if ($subtotal < $voucher->min_subtotal) {
            $minimum = 'Rp'.number_format($voucher->min_subtotal, 0, ',', '.');

            throw $this->unavailable("Voucher {$voucher->name} berlaku untuk belanja minimal {$minimum}.");
        }

        if ($voucher->remainingQuota() === 0) {
            throw $this->unavailable("Kuota voucher {$voucher->name} sudah habis. Pilih voucher lain atau lanjut tanpa voucher.");
        }

        if ($voucher->remainingFor($user) === 0) {
            throw $this->unavailable("Anda sudah memakai voucher {$voucher->name} sebanyak batas yang diizinkan.");
        }

        return $voucher;
    }

    private function unavailable(string $message): ApiException
    {
        return new ApiException($message, 'VOUCHER_UNAVAILABLE', 409);
    }
}
