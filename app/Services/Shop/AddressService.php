<?php

namespace App\Services\Shop;

use App\Exceptions\ApiException;
use App\Models\Address;
use App\Models\User;
use App\Services\Jubelio\RegionService;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Address book rules (docs/api/addresses-regions.md ADDR-01 – ADDR-06):
 * at most 10 addresses, exactly one default once there is any, region names
 * taken from Jubelio. Default changes run in one transaction that locks the
 * user's row, so two requests cannot leave two defaults (or none).
 */
class AddressService
{
    public function __construct(private readonly RegionService $regions) {}

    /**
     * Default first, then newest (ADDR-01).
     *
     * @return Collection<int, Address>
     */
    public function list(User $user): Collection
    {
        return $user->addresses()->orderByDesc('is_default')->orderByDesc('created_at')->orderByDesc('id')->get();
    }

    /**
     * The user's address; another user's answers 404 like a missing one (AddressPolicy).
     */
    public function find(User $user, int $id, string $ability = 'view'): Address
    {
        $address = Address::find($id);

        if ($address === null || Gate::forUser($user)->denies($ability, $address)) {
            throw new ApiException('Alamat tidak ditemukan.', 'ADDRESS_NOT_FOUND', 404);
        }

        return $address;
    }

    /**
     * ADDR-02. The first address always becomes the default.
     *
     * @param  array<string, mixed>  $input  Validated StoreAddressRequest data
     */
    public function create(User $user, array $input): Address
    {
        if ($user->addresses()->count() >= Address::MAX_PER_USER) {
            throw $this->limitReached();
        }

        $attributes = $this->attributes($input);

        return DB::transaction(function () use ($user, $attributes, $input): Address {
            $this->lock($user);

            if ($user->addresses()->count() >= Address::MAX_PER_USER) {
                throw $this->limitReached();
            }

            $default = ($input['is_default'] ?? false) || ! $user->addresses()->exists();
            if ($default) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create($attributes + ['is_default' => $default]);
        });
    }

    /**
     * ADDR-04. is_default = false on the current default is ignored: the user
     * keeps one default address. Orders keep their own copy, so they never change.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, int $id, array $input): Address
    {
        $address = $this->find($user, $id, 'update');
        $attributes = $this->attributes($input);

        return DB::transaction(function () use ($user, $address, $attributes, $input): Address {
            $this->lock($user);

            if (($input['is_default'] ?? false) && ! $address->is_default) {
                $user->addresses()->update(['is_default' => false]);
                $attributes['is_default'] = true;
            }

            $address->update($attributes);

            return $address;
        });
    }

    /**
     * ADDR-05. Removing the default makes the newest remaining address the default.
     */
    public function delete(User $user, int $id): void
    {
        $address = $this->find($user, $id, 'delete');

        DB::transaction(function () use ($user, $address): void {
            $this->lock($user);
            $address->delete();

            if ($address->is_default) {
                $user->addresses()->orderByDesc('created_at')->orderByDesc('id')->first()?->update(['is_default' => true]);
            }
        });
    }

    /**
     * ADDR-06.
     */
    public function makeDefault(User $user, int $id): void
    {
        $address = $this->find($user, $id, 'update');

        DB::transaction(function () use ($user, $address): void {
            $this->lock($user);
            $user->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });
    }

    /**
     * Columns from the request, with region names from Jubelio after the chain
     * province ∋ city ∋ district ∋ subdistrict is checked (REGION_NOT_FOUND).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function attributes(array $input): array
    {
        $province = $this->pick($this->regions->provinces(), $input['province_id'], 'province_id',
            'Provinsi yang dipilih tidak ditemukan.', 'Provinsi tidak dikenal.');
        $city = $this->pick($this->regions->cities($province['id']), $input['city_id'], 'city_id',
            'Kota yang dipilih tidak berada di provinsi tersebut.', 'Kota tidak sesuai dengan provinsi.');
        $district = $this->pick($this->regions->districts($city['id']), $input['district_id'], 'district_id',
            'Kecamatan yang dipilih tidak berada di kota tersebut.', 'Kecamatan tidak sesuai dengan kota.');
        $subdistrict = $this->pick($this->regions->subdistricts($district['id']), $input['subdistrict_id'], 'subdistrict_id',
            'Kelurahan yang dipilih tidak berada di kecamatan tersebut.', 'Kelurahan tidak sesuai dengan kecamatan.');

        return [
            'label' => filled($input['label'] ?? null) ? trim($input['label']) : null,
            'recipient_name' => trim($input['recipient_name']),
            'phone' => PhoneNumber::normalize($input['phone']),
            'address_line' => trim($input['address_line']),
            'province_id' => $province['id'],
            'province_name' => $province['name'],
            'city_id' => $city['id'],
            'city_name' => $city['name'],
            'district_id' => $district['id'],
            'district_name' => $district['name'],
            'subdistrict_id' => $subdistrict['id'],
            'subdistrict_name' => $subdistrict['name'],
            'post_code' => $input['post_code'],
        ];
    }

    /**
     * @param  list<array<string, string|null>>  $regions
     * @return array<string, string|null>
     */
    private function pick(array $regions, string $id, string $field, string $message, string $fieldMessage): array
    {
        foreach ($regions as $region) {
            if ($region['id'] === $id) {
                return $region;
            }
        }

        throw new ApiException($message, 'REGION_NOT_FOUND', 422, [$field => [$fieldMessage]]);
    }

    private function limitReached(): ApiException
    {
        return new ApiException(
            'Maksimal '.Address::MAX_PER_USER.' alamat tersimpan. Hapus salah satu alamat terlebih dahulu.',
            'ADDRESS_LIMIT_REACHED',
            422,
        );
    }

    private function lock(User $user): void
    {
        User::whereKey($user->id)->lockForUpdate()->first();
    }
}
