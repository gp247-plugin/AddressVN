<?php

namespace App\GP247\Plugins\AddressVN\Services;

/**
 * Vietnam's administrative units as shipped with the plugin: provinces / cities and,
 * under each, its wards / communes / special zones (two levels since 2025-07-01).
 *
 * The data file is JSON:
 *   {"version": "2025-07", "source": "...", "provinces": [
 *       {"code": "01", "name": "Thành phố Hà Nội", "wards": [{"code": "00004", "name": "Phường Ba Đình"}, ...]}, ...]}
 *
 * Loaded once per request. A missing or malformed file is an error the caller reports;
 * the shop then lets the shopper type the address by hand.
 *
 * @aidlc-unit plugin-address-vn
 * @aidlc-story US-address-vn-catalog
 */
class AdminUnitCatalog
{
    /** Prefixes of unit names that are left out when names are compared ("Hà Nội" = "Thành phố Hà Nội"). */
    private const NAME_PREFIXES = ['thanh pho', 'tinh', 'phuong', 'xa', 'thi tran', 'dac khu', 'tp'];

    /** @var array<string, array{name: string, wards: array<string, string>}>|null */
    private ?array $provinces = null;

    private string $version = '';

    /** @var array<string, array<string, array<string, list<string>>>> Name indexes per list ("provinces" / "<provinceCode>"), see nameKeys(). */
    private array $byName = [];

    /**
     * @param string|null $path Data file; null = config `Plugins/AddressVN.catalog_path`, then the bundled file.
     */
    public function __construct(private ?string $path = null)
    {
    }

    /**
     * @return string Version of the data set (stored with every matched address).
     */
    public function version(): string
    {
        $this->load();

        return $this->version;
    }

    /**
     * @return array<string, string> Province code => name, in file order.
     */
    public function provinces(): array
    {
        $this->load();

        return array_map(static fn (array $province): string => $province['name'], $this->provinces);
    }

    /**
     * @param string $provinceCode Province code.
     * @return array<string, string> Ward code => name of that province, in file order (empty for an unknown code).
     */
    public function wards(string $provinceCode): array
    {
        $this->load();

        return $this->provinces[$provinceCode]['wards'] ?? [];
    }

    /**
     * @param string|null $code Code sent by the form.
     * @param string|null $name Name sent by the form (used when no code is given).
     * @return array{code: string, name: string}|null The province, or null when nothing matches.
     */
    public function findProvince(?string $code, ?string $name): ?array
    {
        $this->load();

        return $this->find($this->provinces(), 'provinces', $code, $name);
    }

    /**
     * @param string      $provinceCode Code of the province the ward must belong to.
     * @param string|null $code         Code sent by the form.
     * @param string|null $name         Name sent by the form (used when no code is given).
     * @return array{code: string, name: string}|null The ward, or null when it is not a ward of that province.
     */
    public function findWard(string $provinceCode, ?string $code, ?string $name): ?array
    {
        return $this->find($this->wards($provinceCode), $provinceCode, $code, $name);
    }

    /**
     * @param array<string, string> $rows  Code => name.
     * @param string                $index Name of the folded-name index.
     * @param string|null           $code  Code sent by the form.
     * @param string|null           $name  Name sent by the form.
     * @return array{code: string, name: string}|null
     */
    private function find(array $rows, string $index, ?string $code, ?string $name): ?array
    {
        $code = trim((string) $code);
        if ($code !== '') {
            return isset($rows[$code]) ? ['code' => $code, 'name' => $rows[$code]] : null;
        }
        $name = (string) $name;
        if (self::fold($name) === '') {
            return null;
        }
        // WHY: the official list has units in one province whose names differ only by accents
        // ("Xã Văn Lang" / "Xã Văn Lăng") or only by prefix ("Phường Tân Thành" / "Xã Tân Thành").
        // Tiers go from strict to loose, and a tier answers only when exactly one unit has the key.
        // A name typed without accents says nothing about them ("Thanh An" may be "Thạnh An"):
        // it is compared only in the accent-less tiers.
        $keys = self::nameKeys($name);
        if ($keys['accented'] === $keys['folded']) {
            unset($keys['accented'], $keys['accented_short']);
        }
        $this->byName[$index] ??= $this->nameIndexes($rows);
        foreach ($keys as $tier => $key) {
            $codes = $this->byName[$index][$tier][$key] ?? [];
            if (count($codes) === 1) {
                return ['code' => $codes[0], 'name' => $rows[$codes[0]]];
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $rows Code => name.
     * @return array<string, array<string, list<string>>> Tier => key => codes having that key.
     */
    private function nameIndexes(array $rows): array
    {
        $indexes = [];
        foreach ($rows as $rowCode => $rowName) {
            foreach (self::nameKeys($rowName) as $tier => $key) {
                if ($key !== '') {
                    $indexes[$tier][$key][] = (string) $rowCode;
                }
            }
        }

        return $indexes;
    }

    /**
     * Comparison keys of a name, strictest first: with accents (full, then without its unit
     * prefix), then without accents (full, then without its prefix).
     *
     * @param string $name Unit name.
     * @return array<string, string> Tier => key.
     */
    private static function nameKeys(string $name): array
    {
        $accented = self::words($name);
        $folded = self::fold($name, false);
        $prefixWords = 0;
        foreach (self::NAME_PREFIXES as $prefix) {
            if (str_starts_with($folded, $prefix . ' ')) {
                $prefixWords = substr_count($prefix, ' ') + 1;
                break;
            }
        }
        $dropPrefix = static fn (string $words): string => implode(' ', array_slice(explode(' ', $words), $prefixWords));

        return [
            'accented' => $accented,
            'accented_short' => $dropPrefix($accented),
            'folded' => $folded,
            'folded_short' => $dropPrefix($folded),
        ];
    }

    /**
     * Name reduced to lower-case words, accents kept (composed form when intl is available).
     *
     * @param string $name Unit name.
     * @return string
     */
    private static function words(string $name): string
    {
        if (class_exists(\Normalizer::class)) {
            $name = (string) \Normalizer::normalize($name, \Normalizer::FORM_C);
        }
        $name = mb_strtolower($name, 'UTF-8');

        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name));
    }

    /**
     * Name reduced for comparison: lower case, accents removed (đ → d), unit prefix and
     * punctuation dropped, spaces collapsed.
     *
     * @param string $name Unit name.
     * @return string
     */
    public static function fold(string $name, bool $stripPrefix = true): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        // WHY: text in decomposed form (letter + combining mark) loses its marks here; the table
        // below covers the precomposed letters (no Normalizer: intl is often missing on shared hosts).
        $name = (string) preg_replace('/\p{Mn}+/u', '', $name);
        $name = strtr($name, self::accentMap());
        $name = (string) preg_replace('/[^a-z0-9]+/', ' ', $name);
        $name = trim((string) preg_replace('/\s+/', ' ', $name));
        if (!$stripPrefix) {
            return $name;
        }
        foreach (self::NAME_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix . ' ')) {
                $name = substr($name, strlen($prefix) + 1);
                break;
            }
        }

        return $name;
    }

    /**
     * @return array<string, string> Lower-case Vietnamese letter with diacritics => base letter.
     */
    private static function accentMap(): array
    {
        static $map = null;
        if ($map === null) {
            $map = ['đ' => 'd'];
            $groups = [
                'a' => 'àáảãạăằắẳẵặâầấẩẫậ', 'e' => 'èéẻẽẹêềếểễệ', 'i' => 'ìíỉĩị',
                'o' => 'òóỏõọôồốổỗộơờớởỡợ', 'u' => 'ùúủũụưừứửữự', 'y' => 'ỳýỷỹỵ',
            ];
            foreach ($groups as $base => $letters) {
                foreach (mb_str_split($letters, 1, 'UTF-8') as $letter) {
                    $map[$letter] = $base;
                }
            }
        }

        return $map;
    }

    /**
     * @return void
     * @throws \RuntimeException When the data file is missing or malformed.
     */
    private function load(): void
    {
        if ($this->provinces !== null) {
            return;
        }
        $path = $this->path ?: (config('Plugins/AddressVN.catalog_path') ?: dirname(__DIR__) . '/Data/vn-admin-units.json');
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('AddressVN: the administrative units file is missing (' . basename($path) . ').');
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || !is_array($data['provinces'] ?? null) || $data['provinces'] === []) {
            throw new \RuntimeException('AddressVN: the administrative units file is not valid (' . basename($path) . ').');
        }
        $provinces = [];
        foreach ($data['provinces'] as $province) {
            $code = trim((string) ($province['code'] ?? ''));
            if ($code === '' || !is_string($province['name'] ?? null)) {
                continue;
            }
            $wards = [];
            foreach ((array) ($province['wards'] ?? []) as $ward) {
                $wardCode = trim((string) ($ward['code'] ?? ''));
                if ($wardCode !== '' && is_string($ward['name'] ?? null)) {
                    $wards[$wardCode] = trim($ward['name']);
                }
            }
            $provinces[$code] = ['name' => trim($province['name']), 'wards' => $wards];
        }
        $this->provinces = $provinces;
        $this->version = (string) ($data['version'] ?? '');
    }
}
