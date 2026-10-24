<?php

namespace App\Services\Jubelio\Mappers;

use App\Support\HtmlSanitizer;
use Illuminate\Support\Carbon;

/**
 * Turns a Jubelio item group (INT-JB-04) plus its SKU rows from INT-JB-05 into
 * column values for products, product_variants, and product_images.
 *
 * Formats seen on the store's real account (15 Oct 2026, docs/api/integrations.md):
 * numbers arrive as decimal strings ("200.0000") or null; min/max 0 means "no
 * limit"; prices[] is usually empty, so the SKU sell_price applies; ids are integers.
 */
class ProductMapper
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly ?string $storeId,
    ) {}

    /**
     * Whether the item group may be sold on the website (INT-JB-04).
     *
     * @param  array<string, mixed>  $group
     */
    public function isSellable(array $group): bool
    {
        return ($group['is_active'] ?? false) === true && ($group['sell_this'] ?? false) === true;
    }

    /**
     * Product columns, except category_id and slug (the sync resolves those).
     *
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    public function product(array $group): array
    {
        $cover = $this->images($group)[0] ?? null;

        return [
            'name' => trim((string) ($group['item_group_name'] ?? '')),
            'brand_name' => $this->stringOrNull($group['brand_name'] ?? null),
            'description_html' => $this->sanitizer->clean($group['description'] ?? null) ?: null,
            'sell_unit' => $this->stringOrNull($group['sell_unit'] ?? null),
            'min_qty' => $this->limit($group['min'] ?? null),
            'max_qty' => $this->limit($group['max'] ?? null),
            'package_weight' => $this->positiveInt($group['package_weight'] ?? null),
            'package_length' => $this->positiveInt($group['package_length'] ?? null),
            'package_width' => $this->positiveInt($group['package_width'] ?? null),
            'package_height' => $this->positiveInt($group['package_height'] ?? null),
            'variations' => $this->variations($group['variations'] ?? null),
            'thumbnail_url' => $cover['thumbnail_url'] ?? $cover['url'] ?? null,
            'is_active' => $this->isSellable($group),
            'jubelio_last_modified' => $this->date($group['last_modified'] ?? null),
        ];
    }

    /**
     * One entry per SKU, keyed by jubelio_item_id.
     *
     * @param  array<string, mixed>  $group
     * @param  array<int, array<string, mixed>>  $skuRows  INT-JB-05 rows keyed by item_id (name, weight, bundle flag)
     * @return array<int, array<string, mixed>>
     */
    public function variants(array $group, array $skuRows): array
    {
        $variants = [];
        $groupName = trim((string) ($group['item_group_name'] ?? ''));

        foreach ($group['product_skus'] ?? [] as $sku) {
            $itemId = (int) $sku['item_id'];

            // The group detail still lists a SKU that Jubelio has removed or switched off; INT-JB-05 no longer
            // does. It is left out here, so the sync hides the variant it may have copied earlier.
            if ($skuRows !== [] && ! isset($skuRows[$itemId])) {
                continue;
            }

            $row = $skuRows[$itemId] ?? [];
            $values = $this->variationValues($sku['variation_values'] ?? null);
            $images = $this->skuImages($sku);
            $price = $this->price($sku);

            $variants[$itemId] = [
                'jubelio_item_id' => $itemId,
                'sku' => trim((string) $sku['item_code']),
                'name' => $this->stringOrNull($row['item_name'] ?? null) ?? $this->variantName($groupName, $values),
                'variation_values' => $values,
                'price' => $price,
                'package_weight' => $this->positiveInt($row['package_weight'] ?? null),
                'barcode' => $this->stringOrNull($sku['barcode'] ?? null),
                'image_url' => $images[0]['cloud_key'] ?? null,
                'is_bundle' => (bool) ($row['is_bundle'] ?? false),
                // A bundle can be left at 0 in Jubelio; it is not for sale until it has a price.
                'is_active' => $price > 0,
            ];
        }

        return $variants;
    }

    /**
     * Gallery for the whole product, in display order. Jubelio stores images per
     * SKU; an image that belongs to a single SKU of a multi-SKU product is tied
     * to that variant (shown when it is selected), the rest belong to the product.
     *
     * @param  array<string, mixed>  $group
     * @return list<array{jubelio_image_id: int, jubelio_item_id: int|null, url: string, thumbnail_url: string|null, sort_order: int}>
     */
    public function images(array $group): array
    {
        $skus = $group['product_skus'] ?? [];
        $owners = [];
        $images = [];

        foreach ($skus as $sku) {
            foreach ($this->skuImages($sku) as $image) {
                $id = (int) $image['image_id'];
                $owners[$id][] = (int) $sku['item_id'];
                $images[$id] ??= $image;
            }
        }

        $result = [];
        foreach (array_values($images) as $index => $image) {
            $id = (int) $image['image_id'];
            $itemIds = array_unique($owners[$id]);

            $result[] = [
                'jubelio_image_id' => $id,
                'jubelio_item_id' => count($skus) > 1 && count($itemIds) === 1 ? $itemIds[0] : null,
                'url' => (string) $image['cloud_key'],
                'thumbnail_url' => $this->stringOrNull($image['thumbnail'] ?? null),
                'sort_order' => $index,
            ];
        }

        return $result;
    }

    /**
     * Price for the website's internal store from prices[] when set, otherwise
     * the SKU sell_price (INT-JB-04, WH-03).
     *
     * @param  array<string, mixed>  $sku
     */
    public function price(array $sku): int
    {
        foreach ($sku['prices'] ?? [] as $price) {
            if ($this->storeId !== null && (string) ($price['store_id'] ?? '') === $this->storeId && isset($price['sell_price'])) {
                return $this->nonNegativeInt($price['sell_price']);
            }
        }

        return $this->nonNegativeInt($sku['sell_price'] ?? 0);
    }

    /**
     * Rounds Jubelio's decimal strings ("200.0000") to whole numbers; 0, empty,
     * and non-numeric values become null.
     */
    public function positiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) round((float) $value);

        return $int > 0 ? $int : null;
    }

    /**
     * Purchase limits: Jubelio sends 0 for "no limit", which must not become
     * max_qty = 0 (that would block every purchase).
     */
    private function limit(mixed $value): ?int
    {
        $int = $this->positiveInt($value);

        return $int === null ? null : min($int, 65535);
    }

    private function nonNegativeInt(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) round((float) $value)) : 0;
    }

    /**
     * @param  array<string, mixed>  $sku
     * @return list<array<string, mixed>>
     */
    private function skuImages(array $sku): array
    {
        $images = array_filter($sku['images'] ?? [], fn ($image) => is_array($image) && isset($image['image_id']) && filled($image['cloud_key'] ?? null));
        usort($images, fn (array $a, array $b) => ((int) ($a['sequence_number'] ?? 0)) <=> ((int) ($b['sequence_number'] ?? 0)));

        return $images;
    }

    /**
     * @return list<array{label: string, values: list<string>}>|null
     */
    private function variations(mixed $variations): ?array
    {
        if (! is_array($variations)) {
            return null;
        }

        $result = [];
        foreach ($variations as $variation) {
            if (is_array($variation) && filled($variation['label'] ?? null) && is_array($variation['values'] ?? null)) {
                $result[] = ['label' => (string) $variation['label'], 'values' => array_values(array_map('strval', $variation['values']))];
            }
        }

        return $result ?: null;
    }

    /**
     * @return list<array{label: string, value: string}>|null
     */
    private function variationValues(mixed $values): ?array
    {
        if (! is_array($values)) {
            return null;
        }

        $result = [];
        foreach ($values as $value) {
            if (is_array($value) && filled($value['label'] ?? null) && filled($value['value'] ?? null)) {
                $result[] = ['label' => (string) $value['label'], 'value' => (string) $value['value']];
            }
        }

        return $result ?: null;
    }

    /**
     * Used only when INT-JB-05 has no row for the SKU, e.g. "Baterai - 20Ah".
     *
     * @param  list<array{label: string, value: string}>|null  $values
     */
    private function variantName(string $groupName, ?array $values): string
    {
        $suffix = implode(' ', array_column($values ?? [], 'value'));

        return $suffix === '' ? $groupName : "{$groupName} - {$suffix}";
    }

    private function date(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value)->setTimezone(config('app.timezone')) : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
