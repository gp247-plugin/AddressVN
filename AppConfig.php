<?php
/**
 * Plugin format 2.1
 */
#App\GP247\Plugins\AddressVN\AppConfig.php
namespace App\GP247\Plugins\AddressVN;

use GP247\Core\ExtensionConfigDefault;
use GP247\Core\Models\AdminConfig;
use GP247\Core\Models\AdminHome;

/**
 * Vietnam address plugin lifecycle.
 *
 * The plugin owns no table and no setting of its own: which address fields the province
 * and the ward feed is kept by the shop (Address settings tab), and the administrative
 * units ship as a data file inside the plugin. Install / update only write the plugin's
 * on/off row, so a 1-click update has nothing to lose.
 *
 * @aidlc-unit plugin-address-vn
 * @aidlc-story US-address-vn-lifecycle
 */
class AppConfig extends ExtensionConfigDefault
{
    /** Name of the shop's address settings route, when the shop has one. */
    private const SETTINGS_ROUTE = 'admin_address_mapping.index';

    public function __construct()
    {
        $config = file_get_contents(__DIR__.'/gp247.json');
        $config = json_decode($config, true);
        $this->configGroup = $config['configGroup'];
        $this->configKey = $config['configKey'];
        $this->configCode = $config['configCode'] ?? $this->configKey;
        $this->requireCore = $config['requireCore'] ?? [];
        $this->requireComposerPackages = $config['requireComposerPackages'] ?? [];
        $this->requireGp247Extensions = $config['requireGp247Extensions'] ?? [];
        $this->appPath = $this->configGroup . '/' . $this->configKey;
        $this->title = trans($this->appPath.'::lang.title');
        $this->image = $this->appPath.'/'.$config['image'];
        $this->version = $config['version'];
        $this->auth = $config['auth'];
        $this->link = $config['link'];
    }

    /**
     * @return array{error: int, msg: string}
     */
    public function install()
    {
        if (AdminConfig::where('key', $this->configKey)->where('group', $this->configGroup)->exists()) {
            return ['error' => 1, 'msg' => gp247_language_render('admin.extension.plugin_exist')];
        }
        // WHY: requireComposerPackages only checks that gp247/shop is present, not that its
        // version has the address layer this plugin plugs into.
        if (!interface_exists(\GP247\Shop\Address\Contracts\AddressProvider::class)) {
            return ['error' => 1, 'msg' => trans($this->appPath.'::lang.requires_shop_address')];
        }
        try {
            AdminConfig::insert([
                'group' => $this->configGroup,
                'code' => $this->configCode,
                'key' => $this->configKey,
                'sort' => 0,
                'store_id' => GP247_STORE_ID_GLOBAL,
                'value' => self::ON,
                'detail' => $this->appPath.'::lang.title',
            ]);

            return ['error' => 0, 'msg' => gp247_language_render('admin.extension.install_success')];
        } catch (\Throwable $e) {
            return ['error' => 1, 'msg' => $e->getMessage()];
        }
    }

    /**
     * Nothing to converge in 1.0 (no setting rows, no table); kept so later versions add
     * their changes here, checking the current state rather than the version.
     *
     * @param string|null $fromVersion Version installed before this update (null when unknown).
     * @return array{error: int, msg: string}
     */
    public function update(?string $fromVersion = null)
    {
        return ['error' => 0, 'msg' => ''];
    }

    /**
     * Removes the plugin's on/off rows (every store). The address mapping the owner saved
     * on the shop's Address tab is the shop's and is kept, so a reinstall finds it again.
     *
     * @return array{error: int, msg: string}
     */
    public function uninstall()
    {
        try {
            AdminConfig::where('group', $this->configGroup)->where('key', $this->configKey)->delete();
            AdminHome::where('extension', $this->appPath)->delete();

            return ['error' => 0, 'msg' => gp247_language_render('admin.extension.uninstall_success')];
        } catch (\Throwable $e) {
            return ['error' => 1, 'msg' => $e->getMessage()];
        }
    }

    /**
     * @return array{error: int, msg: string}
     */
    public function enable()
    {
        $process = AdminConfig::where('group', $this->configGroup)->where('key', $this->configKey)->update(['value' => self::ON]);
        AdminHome::where('extension', $this->appPath)->update(['status' => 1]);
        if (!$process) {
            return ['error' => 1, 'msg' => gp247_language_render('admin.extension.action_error', ['action' => 'Enable'])];
        }

        return ['error' => 0, 'msg' => gp247_language_render('admin.extension.enable_success')];
    }

    /**
     * @return array{error: int, msg: string}
     */
    public function disable()
    {
        $process = AdminConfig::where('group', $this->configGroup)->where('key', $this->configKey)->update(['value' => self::OFF]);
        AdminHome::where('extension', $this->appPath)->update(['status' => 0]);
        if (!$process) {
            return ['error' => 1, 'msg' => gp247_language_render('admin.extension.action_error', ['action' => 'Disable'])];
        }

        return ['error' => 0, 'msg' => ''];
    }

    /**
     * @param string|int|null $storeId Store being removed.
     * @return void
     */
    public function removeStore($storeId = null)
    {
        // Store on/off rows are plain admin_config rows handled by core.
    }

    /**
     * @param string|int|null $storeId Store being created.
     * @return void
     */
    public function setupStore($storeId = null)
    {
        // A new store inherits the GLOBAL on/off row.
    }

    /**
     * The settings button opens the shop's Address tab, where the owner maps the province
     * and ward onto the shop's address fields. Without that tab there is nothing to open.
     *
     * @return \Illuminate\Http\RedirectResponse|null
     */
    public function clickApp()
    {
        if (!\Illuminate\Support\Facades\Route::has(self::SETTINGS_ROUTE)) {
            return null;
        }

        return redirect(gp247_route_admin(self::SETTINGS_ROUTE, ['country' => 'VN']));
    }

    /**
     * @return array<string, mixed>
     */
    public function getInfo()
    {
        return [
            'title' => $this->title,
            'key' => $this->configKey,
            'code' => $this->configCode,
            'image' => $this->image,
            'permission' => self::ALLOW,
            'version' => $this->version,
            'auth' => $this->auth,
            'link' => $this->link,
            'value' => 0,
            'appPath' => $this->appPath,
        ];
    }
}
