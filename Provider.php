<?php
/**
 * Provides everything needed for the extension.
 *
 * @aidlc-unit plugin-address-vn
 * @aidlc-story US-address-vn-province-ward
 */

$config = file_get_contents(__DIR__.'/gp247.json');
$config = json_decode($config, true);
$extensionPath = $config['configGroup'].'/'.$config['configKey'];

$this->loadTranslationsFrom(__DIR__.'/Lang', $extensionPath);

if (gp247_extension_check_active($config['configGroup'], $config['configKey'])) {

    if (file_exists(__DIR__.'/config.php')) {
        $this->mergeConfigFrom(__DIR__.'/config.php', $extensionPath);
    }

    // Plug into the shop's address layer (gp247-config.shop.address_providers).
    // WHY guarded: on a shop without that layer the plugin stays installed and does nothing.
    if (interface_exists(\GP247\Shop\Address\Contracts\AddressProvider::class)) {
        config(['gp247-config.shop.address_providers.AddressVN' => [
            'driver' => \App\GP247\Plugins\AddressVN\Services\VietnamAddressProvider::class,
            'countries' => ['VN'],
            'capabilities' => ['options'],
        ]]);
    }
}
