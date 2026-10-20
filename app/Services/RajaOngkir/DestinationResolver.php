<?php

namespace App\Services\RajaOngkir;

use App\Exceptions\ApiException;
use App\Models\Address;
use Illuminate\Support\Facades\Cache;

/**
 * Finds RajaOngkir's destination id for an address. Our addresses carry Jubelio's region ids, which RajaOngkir
 * does not know, so the id is found by searching the names and picking the closest match (subdistrict, district,
 * city, and postal code). Found ids are remembered, since they do not change.
 */
class DestinationResolver
{
    public function __construct(private readonly RajaOngkirClient $client) {}

    /**
     * @throws ApiException 422 NO_SHIPPING_SERVICE when RajaOngkir does not know the place
     */
    public function forAddress(Address $address): int
    {
        return $this->find(
            "address:{$address->subdistrict_id}",
            (string) $address->subdistrict_name,
            (string) $address->district_name,
            (string) $address->city_name,
            (string) $address->post_code,
        ) ?? throw new ApiException(
            'Alamat ini belum dikenali oleh layanan ongkir. Periksa kelurahan, kecamatan, dan kode pos, atau hubungi toko.',
            'NO_SHIPPING_SERVICE',
            422,
        );
    }

    /**
     * The warehouse: RAJAONGKIR_ORIGIN_ID if set, otherwise looked up from the origin address in config.
     */
    public function origin(): int
    {
        if (filled($fixed = config('shipping.rajaongkir.origin_id'))) {
            return (int) $fixed;
        }

        $origin = config('shipping.origin');

        return $this->find('origin', $origin['subdistrict'], $origin['district'], $origin['city'], $origin['post_code'])
            ?? throw new ApiException('Gudang asal belum dikenali oleh layanan ongkir. Isi RAJAONGKIR_ORIGIN_ID.', 'NO_SHIPPING_SERVICE', 422);
    }

    private function find(string $key, string $subdistrict, string $district, string $city, string $postCode): ?int
    {
        $cacheKey = "rajaongkir:destination:{$key}:".md5(mb_strtolower("{$subdistrict}|{$district}|{$city}|{$postCode}"));

        if (is_int($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        $best = null;
        $bestScore = 0;

        // Most specific search first; a coarser one only if that finds nothing usable.
        foreach ([trim("{$subdistrict} {$district}"), trim("{$district} {$city}"), $postCode] as $keyword) {
            if ($keyword === '') {
                continue;
            }

            foreach ($this->client->withinCustomerRequest()->searchDestinations($keyword) as $candidate) {
                $score = $this->score($candidate, $subdistrict, $district, $city, $postCode);

                if ($score > $bestScore) {
                    [$best, $bestScore] = [$candidate, $score];
                }
            }

            // Subdistrict and district both matched: good enough, no need to spend another search.
            if ($bestScore >= 6) {
                break;
            }
        }

        // A match on the district alone would price the wrong place.
        if ($best === null || $bestScore < 5) {
            return null;
        }

        Cache::put($cacheKey, $best['id'], (int) config('shipping.rajaongkir.destination_cache_ttl'));

        return $best['id'];
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function score(array $candidate, string $subdistrict, string $district, string $city, string $postCode): int
    {
        return ($this->same($candidate['subdistrict_name'], $subdistrict) ? 4 : 0)
            + ($this->same($candidate['district_name'], $district) ? 3 : 0)
            + ($this->sameCity($candidate['city_name'], $city) ? 2 : 0)
            + ($postCode !== '' && $candidate['zip_code'] === $postCode ? 1 : 0);
    }

    private function same(string $a, string $b): bool
    {
        return $this->normalize($a) !== '' && $this->normalize($a) === $this->normalize($b);
    }

    /**
     * "KAB. BEKASI", "KABUPATEN BEKASI", and "BEKASI" are the same place; "KOTA BEKASI" is not.
     */
    private function sameCity(string $a, string $b): bool
    {
        return $this->cityKey($a) === $this->cityKey($b) && $this->cityKey($a) !== '';
    }

    private function cityKey(string $name): string
    {
        $name = mb_strtolower($name);
        $isCity = (bool) preg_match('/\b(kota|kota adm\.?|adm\.?)\b/u', $name);
        $core = trim(preg_replace('/\b(kabupaten|kab\.?|kota|adm\.?|administrasi)\b/u', ' ', $name));

        return ($isCity ? 'kota ' : 'kab ').preg_replace('/[^a-z0-9]+/', ' ', trim($core));
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($value)));
    }
}
