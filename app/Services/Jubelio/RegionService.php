<?php

namespace App\Services\Jubelio;

use App\Exceptions\IntegrationException;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Jubelio's region lists (INT-JB-08) for REG-01 – REG-04 and the address book,
 * so address IDs are the ones Jubelio expects on a sales order.
 *
 * Each list is cached for 30 days per parent ID. An expired list is kept as a
 * fallback: when Jubelio fails while refreshing it, the old list is served
 * (REG-01: "Bila Jubelio gagal tapi cache ada, response tetap 200").
 */
class RegionService
{
    private const FRESH_FOR = 30 * 24 * 60 * 60;

    public const UNAVAILABLE_MESSAGE = 'Data wilayah sedang tidak bisa dimuat. Coba lagi beberapa saat.';

    public function __construct(private readonly JubelioClient $jubelio) {}

    /**
     * @return list<array{id: string, name: string}>
     */
    public function provinces(): array
    {
        return $this->list('provinces', [], 'province_id');
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function cities(string $provinceId): array
    {
        return $this->list('cities', ['province_id' => $provinceId], 'city_id');
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function districts(string $cityId): array
    {
        return $this->list('districts', ['city_id' => $cityId], 'district_id');
    }

    /**
     * Jubelio's subdistricts carry no post code today, so post_code is null and
     * the customer types it (REG-04); a post code is used if Jubelio adds one.
     *
     * @return list<array{id: string, name: string, post_code: string|null}>
     */
    public function subdistricts(string $districtId): array
    {
        return $this->list('subdistricts', ['district_id' => $districtId], 'subdistrict_id', withPostCode: true);
    }

    /**
     * @param  array<string, string>  $parent
     * @return list<array<string, string|null>>
     */
    private function list(string $level, array $parent, string $idKey, bool $withPostCode = false): array
    {
        // IDs are digits only; anything else cannot exist in Jubelio.
        if (array_filter($parent, fn (string $id) => ! ctype_digit($id)) !== []) {
            return [];
        }

        return $this->remember("regions:{$level}:".implode(':', $parent), function () use ($level, $parent, $idKey, $withPostCode): array {
            $rows = array_map(function (array $row) use ($idKey, $withPostCode): array {
                $region = ['id' => (string) $row[$idKey], 'name' => trim((string) $row['name'])];

                if ($withPostCode) {
                    $postCode = $row['post_code'] ?? $row['postcode'] ?? null;
                    $region['post_code'] = filled($postCode) ? (string) $postCode : null;
                }

                return $region;
            }, $this->jubelio->withinCustomerRequest()->regions($level, $parent));

            usort($rows, fn (array $a, array $b) => strnatcasecmp($a['name'], $b['name']));

            return $rows;
        });
    }

    /**
     * @param  Closure(): list<array<string, string|null>>  $fetch
     * @return list<array<string, string|null>>
     */
    private function remember(string $key, Closure $fetch): array
    {
        $cached = Cache::get($key);

        if (is_array($cached) && $cached['fetched_at'] > now()->timestamp - self::FRESH_FOR) {
            return $cached['data'];
        }

        try {
            $data = $fetch();
        } catch (IntegrationException $e) {
            if (is_array($cached)) {
                return $cached['data'];
            }

            throw $e->withMessage(self::UNAVAILABLE_MESSAGE);
        }

        Cache::forever($key, ['fetched_at' => now()->timestamp, 'data' => $data]);

        return $data;
    }
}
