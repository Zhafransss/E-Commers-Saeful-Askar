<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAddressRequest;
use App\Http\Resources\Api\V1\AddressResource;
use App\Models\Address;
use App\Services\Shop\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/addresses-regions.md
 */
class AddressController extends Controller
{
    public function __construct(private readonly AddressService $addresses) {}

    /**
     * ADDR-01 · Daftar alamat saya
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => AddressResource::collection($this->addresses->list($request->user()))->resolve()]);
    }

    /**
     * ADDR-02 · Tambah alamat
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $this->addresses->create($request->user(), $request->validated());

        return $this->one($address, 'Alamat disimpan.', 201);
    }

    /**
     * ADDR-03 · Detail alamat
     */
    public function show(Request $request, int $id): JsonResponse
    {
        return $this->one($this->addresses->find($request->user(), $id));
    }

    /**
     * ADDR-04 · Ubah alamat
     */
    public function update(StoreAddressRequest $request, int $id): JsonResponse
    {
        $address = $this->addresses->update($request->user(), $id, $request->validated());

        return $this->one($address, 'Alamat diperbarui.');
    }

    /**
     * ADDR-05 · Hapus alamat
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->addresses->delete($request->user(), $id);

        return response()->json(['message' => 'Alamat dihapus.']);
    }

    /**
     * ADDR-06 · Jadikan alamat utama. Answers the whole list, like ADDR-01.
     */
    public function setDefault(Request $request, int $id): JsonResponse
    {
        $this->addresses->makeDefault($request->user(), $id);

        return response()->json([
            'data' => AddressResource::collection($this->addresses->list($request->user()))->resolve(),
            'message' => 'Alamat utama diperbarui.',
        ]);
    }

    private function one(Address $address, ?string $message = null, int $status = 200): JsonResponse
    {
        $body = ['data' => (new AddressResource($address))->resolve()];

        if ($message !== null) {
            $body['message'] = $message;
        }

        return response()->json($body, $status);
    }
}
