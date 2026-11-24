<?php

namespace App\GP247\Plugins\AddressVN\Services;

use GP247\Shop\Address\Contracts\AddressProvider;
use GP247\Shop\Address\Contracts\ListsAddressOptions;
use GP247\Shop\Address\Data\AddressComponent;
use GP247\Shop\Address\Data\AddressOption;
use GP247\Shop\Address\Data\AddressSchema;
use GP247\Shop\Address\Data\NormalizedAddress;

/**
 * Vietnam behind the shop's address contract: the shopper picks a province / city, then
 * one of its wards / communes. Two levels since 2025-07-01 — there is no district.
 *
 * Default mapping onto the shop's address fields: City ← province, Ward ← ward. The site
 * owner can change it on the shop's Address settings tab. All data comes from the file
 * shipped with the plugin; nothing is sent to another service.
 *
 * @aidlc-unit plugin-address-vn
 * @aidlc-story US-address-vn-province-ward
 * @aidlc-story US-address-vn-match-by-name
 * @aidlc-adr shop-address_provider-capability-contract
 */
class VietnamAddressProvider implements AddressProvider, ListsAddressOptions
{
    public const COUNTRY = 'VN';

    /**
     * @param AdminUnitCatalog $catalog Administrative units shipped with the plugin.
     */
    public function __construct(private AdminUnitCatalog $catalog)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function schema(string $country): AddressSchema
    {
        return new AddressSchema(
            self::COUNTRY,
            $this->catalog->version(),
            [
                new AddressComponent('province', 'Plugins/AddressVN::lang.province', AddressComponent::KIND_LIST),
                new AddressComponent('ward', 'Plugins/AddressVN::lang.ward', AddressComponent::KIND_LIST, 'province'),
            ],
            ['city' => 'province', 'ward' => 'ward'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function options(string $country, string $component, ?string $parentCode, string $locale): array
    {
        $rows = match ($component) {
            'province' => $this->catalog->provinces(),
            'ward' => $parentCode === null ? [] : $this->catalog->wards($parentCode),
            default => [],
        };
        $out = [];
        foreach ($rows as $code => $name) {
            $out[] = new AddressOption((string) $code, $name);
        }

        return $out;
    }

    /**
     * Checks a submitted province / ward: a code must exist (and the ward must belong to
     * the province); a name is matched without accents or the unit prefix
     * ("ha noi" = "Thành phố Hà Nội"). The canonical names are returned.
     *
     * {@inheritDoc}
     */
    public function normalize(string $country, array $components, string $locale): NormalizedAddress
    {
        $matched = [];
        $errors = [];

        $province = isset($components['province'])
            ? $this->catalog->findProvince($components['province']['code'] ?? null, $components['province']['name'] ?? null)
            : null;
        if ($province !== null) {
            $matched['province'] = $province;
        } elseif (isset($components['province'])) {
            $errors['province'] = 'Plugins/AddressVN::lang.unknown_province';
        }

        if (isset($components['ward'])) {
            $ward = $province === null
                ? null
                : $this->catalog->findWard($province['code'], $components['ward']['code'] ?? null, $components['ward']['name'] ?? null);
            if ($ward !== null) {
                $matched['ward'] = $ward;
            } else {
                $errors['ward'] = 'Plugins/AddressVN::lang.unknown_ward';
            }
        }

        return new NormalizedAddress($matched, $errors);
    }
}
