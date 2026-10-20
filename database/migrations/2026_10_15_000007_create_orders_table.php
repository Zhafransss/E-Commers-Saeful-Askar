<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            // WEB-000123, derived from id right after insert; sent as Jubelio ref_no and Gapura partnerReferenceNo.
            $table->string('order_number', 20)->nullable()->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Reference only; the shipping_* columns are the snapshot used everywhere.
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending_payment');

            $table->string('shipping_recipient_name', 100);
            $table->string('shipping_phone', 15);
            $table->string('shipping_address_line');
            $table->string('shipping_province_id', 2);
            $table->string('shipping_province', 100);
            $table->string('shipping_city_id', 4);
            $table->string('shipping_city', 100);
            $table->string('shipping_district_id', 6);
            $table->string('shipping_district', 100);
            $table->string('shipping_subdistrict_id', 10);
            $table->string('shipping_subdistrict', 100);
            $table->char('shipping_post_code', 5);

            $table->string('courier_code', 30);
            $table->string('courier_name', 100);
            $table->string('service_code', 30);
            $table->string('service_name', 100);
            $table->string('service_type', 20);
            $table->string('etd_text', 30)->nullable();
            $table->string('tracking_no', 100)->nullable()->index();
            // Courier actually used by the warehouse, from the update-salesorder webhook.
            $table->string('jubelio_courier', 100)->nullable();
            $table->string('jubelio_shipper', 100)->nullable();

            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping_cost')->default(0);
            $table->unsignedBigInteger('insurance_cost')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('grand_total');

            $table->string('note')->nullable();
            $table->timestamp('payment_expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            // Checkout failed at the Jubelio/Gapura step: hidden from ORD-02, still visible to admins.
            $table->boolean('is_checkout_failed')->default(false);
            // Paid order later cancelled in Jubelio: admin must refund manually.
            $table->boolean('requires_refund')->default(false);

            $table->unsignedBigInteger('jubelio_salesorder_id')->nullable()->unique();
            $table->string('jubelio_salesorder_no', 50)->nullable();
            $table->string('jubelio_sync_status', 10)->default('pending')->index();
            $table->text('jubelio_last_error')->nullable();
            // last_modified of the newest processed webhook; older events are ignored (WH-05).
            $table->timestamp('jubelio_last_modified')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'payment_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
