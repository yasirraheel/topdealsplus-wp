# Freemius SDK 2.13.4 - Premium License Bypass

**Modified by GPL Times - https://www.gpltimes.com**

This is a modified version of the Freemius WordPress SDK (v2.13.4) that bypasses all licensing requirements and enables all premium features automatically. All API calls are intercepted at every layer and prevented, while mock objects simulate a premium license environment.

## Important Notice

This modified SDK is intended for **development and testing purposes only**. Please respect the original developers' work and consider purchasing legitimate licenses for production use.

## Features

- **Complete License Bypass** - All premium features enabled automatically
- **Dynamic Plan Detection** - Automatically uses the correct plan name required by each plugin (no hardcoding needed)
- **Full API Call Prevention** - No external API requests at ANY layer (instance, static, or direct HTTP)
- **Mock Objects** - Realistic user, site, license, and plan objects with ALL entity properties
- **Green Debug Status** - Shows "Connected" status in Freemius debug page
- **Zero PHP Errors** - All property accesses are guarded with defensive checks
- **Priority Loading** - Ensures this SDK loads first when multiple Freemius plugins are active
- **Account Page Support** - Full account page works with correct Site ID, User ID, plan info
- **License Key Acceptance** - "Change License" dialog accepts any key and shows success
- **Universal Compatibility** - Works with ANY Freemius-powered plugin without configuration
- **One-Time Legacy Account Reset** - Clears previously saved Freemius account data once on first load

## How to Use

1. Locate the plugin's `freemius` folder (usually at `wp-content/plugins/PLUGIN-NAME/freemius/`)
2. Replace the entire `freemius` folder with this modified SDK
3. Done! No configuration needed - plan names are auto-detected

## Key Improvement: Dynamic Plan Detection

Unlike previous versions that required hardcoding plan names in `config.php`, this version **automatically detects** the correct plan name from the plugin's own `is_plan()` calls at runtime.

When a plugin checks `is_plan('business')` or `is_plan('professional')`, the SDK:
1. Captures the plan name being checked
2. Always returns `true` (all plan checks pass)
3. Uses the captured name for the mock plan object displayed on the account page

If a plugin never calls `is_plan()` (e.g., single-plan plugins that only use `is_paying()`), the fallback plan name `professional` is displayed. This is purely cosmetic and does not affect functionality.

### Optional: Manual Plan Override

If you want to override the auto-detected plan name, define these constants before the SDK loads (e.g., in `wp-config.php`):
```php
define( 'WP_FS__MOCK_PLAN_NAME', 'your_plan_name' );
define( 'WP_FS__MOCK_PLAN_TITLE', 'Your Plan Title' );
```

Or edit `config.php` in the SDK folder directly.

---

## Complete Step-by-Step Reapplication Guide

Use this section to reapply all modifications to a newer version of the Freemius SDK.

### Step 1: SDK Version Override (`start.php`)

Find the line:
```php
$this_sdk_version = '2.13.4';
```
Change to:
```php
// Modified by GPL Times - https://www.gpltimes.com
// Override version to 999.99.99 to ensure this SDK always loads first
$this_sdk_version = '999.99.99';
```
**Purpose**: WordPress's Freemius loader always uses the highest SDK version among all active plugins. Version `999.99.99` guarantees our modified SDK takes precedence.

### Step 1b: Run-Once-Per-Plugin-Install Accounts Reset (`start.php`)

Add an automatic reset routine in `start.php` (after `require.php` is loaded) that:

1. Checks `WP_FS__GPLTIMES_ENABLE_AUTO_ACCOUNTS_RESET`
2. Builds a signature from detected Freemius plugins (`$fs_active_plugins->plugins`)
3. Compares it with stored signature: `fs_gpltimes_accounts_reset_signature_v1`
4. If signature changed, clears Freemius accounts storage (`fs_accounts`) using `FS_Options::clear(...)`
5. Deletes `fs_active_plugins` so SDK references are rebuilt cleanly
6. Stores the new signature so reset runs only once per plugin-set change

Implementation notes:
- Single site: signature stored with `get_option()`/`update_option()`.
- Multisite: signature stored with `get_site_option()`/`update_site_option()` and reset covers per-blog + network accounts storage.
- This is equivalent to debug page **Delete All Accounts**, but only when signature changes.

### Step 2: Configuration Constants (`config.php`)

After the existing plan constants (`WP_FS__PLAN_FREE`, `WP_FS__PLAN_TRIAL`), add:
```php
// Modified by GPL Times - https://www.gpltimes.com
// Default mock plan name and title (auto-detected from plugin at runtime)
if ( ! defined( 'WP_FS__MOCK_PLAN_NAME' ) ) {
    define( 'WP_FS__MOCK_PLAN_NAME', 'professional' );
}
if ( ! defined( 'WP_FS__MOCK_PLAN_TITLE' ) ) {
    define( 'WP_FS__MOCK_PLAN_TITLE', 'Professional' );
}

// Modified by GPL Times - https://www.gpltimes.com
// Enable/disable automatic Freemius accounts reset migration.
// true  = run reset once per detected Freemius plugin install/change.
// false = disable automatic reset logic.
if ( ! defined( 'WP_FS__GPLTIMES_ENABLE_AUTO_ACCOUNTS_RESET' ) ) {
    define( 'WP_FS__GPLTIMES_ENABLE_AUTO_ACCOUNTS_RESET', true );
}
```

### Step 3: Missing Class Loading (`require.php`)

After the line `require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-subscription.php';`, add:
```php
// Modified by GPL Times - https://www.gpltimes.com
// Ensure FS_Billing class is loaded to prevent "Class not found" errors
require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-billing.php';
```

### Step 4: API Manager Bypass (`includes/class-fs-api.php`)

#### 4a. Add `$_scope` property

After the `$_url` private property declaration, add:
```php
/**
 * Modified by GPL Times - https://www.gpltimes.com
 * @var string The API scope type (install, plugin, user, etc.)
 */
private $_scope;
```

#### 4b. Store scope in constructor

In the `__construct()` method, after `$this->_slug = $slug;`, add:
```php
$this->_scope = $scope; // Modified by GPL Times
```

#### 4c. Replace the entire `_call()` method

Replace the `_call()` method body with scope-aware and path-aware mock responses.

**CRITICAL**: All `id` values MUST be strings (e.g., `'1'` not `1`) because the account page template uses `is_string($site->id)` to display the Site ID. Integer IDs cause "No ID" display.

```php
private function _call( $path, $method = 'GET', $params = array(), $in_retry = false ) {
    // Modified by GPL Times - https://www.gpltimes.com
    // Return context-aware mock responses based on endpoint type and API scope
    $path_lower = strtolower( $path );
    $now = gmdate( 'Y-m-d H:i:s' );

    // Helper: build a full mock install/site entity
    // NOTE: IDs must be strings to match Freemius API response format
    $mock_install = (object) array(
        'id'              => '1',
        'site_id'         => '1',
        'blog_id'         => '1',
        'plugin_id'       => '1',
        'user_id'         => '1',
        'license_id'      => '1',
        'plan_id'         => '1',
        'trial_plan_id'   => null,
        'trial_ends'      => null,
        'title'           => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : 'WordPress Site',
        'url'             => function_exists( 'home_url' ) ? home_url() : 'http://localhost',
        'version'         => '1.0.0',
        'is_premium'      => true,
        'is_active'       => true,
        'is_uninstalled'  => false,
        'is_disconnected' => false,
        'is_beta'         => false,
        'public_key'      => 'pk_f3b8c2a7e9d1f4a6c3e8b2d5a9f1c4e7',
        'secret_key'      => 'sk_2d5a9f1c4e7b3a6c8f2e1d4a7c0e3f6b',
        'created'         => $now,
        'updated'         => $now,
    );

    // Helper: build a full mock license entity
    $mock_license = (object) array(
        'id'                => '1',
        'plugin_id'         => '1',
        'plan_id'           => '1',
        'user_id'           => '1',
        'secret_key'        => 'sk_b5e0b5f8dd8689e6aca49dd6e6e1a930',
        'quota'             => null,
        'activated'         => 1,
        'activated_local'   => 0,
        'expiration'        => null,
        'is_cancelled'      => false,
        'is_block_features' => false,
        'is_whitelabeled'   => false,
        'is_free_localhost'  => true,
        'created'           => $now,
        'updated'           => $now,
    );

    // Helper: build a full mock user entity
    $mock_user = (object) array(
        'id'          => '1',
        'email'       => 'noreply@gmail.com',
        'first'       => 'Premium',
        'last'        => 'User',
        'is_verified' => true,
        'public_key'  => 'pk_4a7c9e2f8b3d1a6e5c8f2b9d4a7c0e3f',
        'secret_key'  => 'sk_8f3d1a6e5c2b9d4a7c0e3f6b8a1d4e7c',
        'created'     => $now,
        'updated'     => $now,
    );

    $plan_name = defined( 'WP_FS__MOCK_PLAN_NAME' ) ? WP_FS__MOCK_PLAN_NAME : 'professional';
    $plan_title = defined( 'WP_FS__MOCK_PLAN_TITLE' ) ? WP_FS__MOCK_PLAN_TITLE : 'Professional';

    // Installs collection endpoint (for multi-site license activation)
    if ( false !== strpos( $path_lower, 'installs.json' ) ) {
        return (object) array( 'installs' => array( $mock_install ) );
    }

    // Site/install endpoints
    if ( false !== strpos( $path_lower, '/install' ) || false !== strpos( $path_lower, '/site' ) ) {
        return $mock_install;
    }

    // License endpoints
    if ( false !== strpos( $path_lower, '/license' ) ) {
        return $mock_license;
    }

    // User endpoints
    if ( false !== strpos( $path_lower, '/user' ) ) {
        return $mock_user;
    }

    // Plan endpoints
    if ( false !== strpos( $path_lower, '/plan' ) ) {
        return (object) array(
            'plans' => array(
                (object) array(
                    'id' => '1', 'plugin_id' => '1',
                    'name' => $plan_name, 'title' => $plan_title,
                    'is_block_features' => false, 'license_type' => 'paid',
                    'created' => $now, 'updated' => $now,
                ),
            ),
        );
    }

    // Updates/versions endpoint - return no update available
    // Without this, _fetch_newer_version() gets a mock install object that lacks
    // 'release_mode' property, causing "Undefined property: stdClass::$release_mode" warning.
    // Returning a 404 error makes _fetch_latest_version() return false (no update).
    if ( false !== strpos( $path_lower, '/updates/' ) || false !== strpos( $path_lower, 'latest.json' ) ) {
        return (object) array(
            'error' => (object) array(
                'type'    => 'VersionNotFound',
                'message' => 'No update available.',
                'code'    => 'version_not_found',
                'http'    => 404,
            ),
        );
    }

    // Ping endpoint
    if ( false !== strpos( $path_lower, '/ping' ) ) {
        return (object) array( 'api' => 'pong', 'timestamp' => $now, 'is_active' => true );
    }

    // Root path "/" or "/?show_pending=true" (used for license activation PUT calls)
    // Determine response based on stored API scope
    $clean_path = preg_replace( '/\?.*$/', '', $path_lower );
    $clean_path = rtrim( $clean_path, '/' );
    if ( empty( $clean_path ) || '/' === $clean_path ) {
        if ( isset( $this->_scope ) && 'user' === $this->_scope ) {
            return $mock_user;
        }
        return $mock_install; // install/plugin scope
    }

    // Default: return valid entity (prevents var_export errors in license activation)
    return $mock_install;
}
```

**Why root path handling matters**: When a user clicks "Change License" on the plugins page and enters any key, the SDK calls `$api->call('/?show_pending=true', 'put', $params)` via the site-scope API. Without the root path handler, the response lacks an `id` property, causing `is_api_result_entity()` to fail and displaying a raw `var_export()` dump instead of success.

#### 4d. Replace `remote_request()` static method

Replace the body with:
```php
static function remote_request( $url, $remote_args ) {
    // Modified by GPL Times - https://www.gpltimes.com
    // Return mock HTTP response to prevent any outbound API calls
    return array(
        'headers'  => array( 'x-api-server' => 'mock', 'content-type' => 'application/json' ),
        'body'     => json_encode( array( 'success' => true ) ),
        'response' => array( 'code' => 200, 'message' => 'OK' ),
        'cookies'  => array(),
        'filename' => null,
    );
}
```

### Step 5: WordPress SDK Bypass (`includes/sdk/FreemiusWordPress.php`)

#### 5a. Replace `MakeRequest()` instance method body
```php
public function MakeRequest( $pCanonizedPath, $pMethod = 'GET', $pParams = array(), $pWPRemoteArgs = null ) {
    // Modified by GPL Times - https://www.gpltimes.com
    return (object) array( 'success' => true, 'api' => 'success' );
}
```

#### 5b. Replace `RemoteRequest()` static method body
```php
static function RemoteRequest( $pUrl, $pWPRemoteArgs ) {
    // Modified by GPL Times - https://www.gpltimes.com
    return array(
        'headers'  => array( 'x-api-server' => 'mock', 'content-type' => 'application/json' ),
        'body'     => json_encode( array( 'success' => true ) ),
        'response' => array( 'code' => 200, 'message' => 'OK' ),
        'cookies'  => array(),
        'filename' => null,
    );
}
```

#### 5c. Replace `Ping()` static method body
```php
public static function Ping() {
    // Modified by GPL Times - https://www.gpltimes.com
    return (object) array( 'api' => 'pong', 'timestamp' => gmdate( 'Y-m-d H:i:s' ), 'is_active' => true );
}
```

### Step 6: License Entity Modifications (`includes/entities/class-fs-plugin-license.php`)

Replace these three method bodies:
```php
function is_expired() {
    // Modified by GPL Times - License never expires
    return false;
}

function is_active() {
    // Modified by GPL Times - License always active
    return true;
}

function is_features_enabled() {
    // Modified by GPL Times - All features always enabled
    return true;
}
```

### Step 7: Core Class Modifications (`includes/class-freemius.php`)

This is the largest file with the most changes. Each modification is described below with the original method signature to locate it.

#### 7a. Add dynamic plan detection property and `_ensure_mock_objects()` method

Before the `/* Ctor */` section, add:
```php
/* Modified by GPL Times - https://www.gpltimes.com
 * Dynamic plan name detection: captured from is_plan() calls
 */
private $_gpl_detected_plan_name = null;

/**
 * Modified by GPL Times - https://www.gpltimes.com
 * Ensures mock user, site, license, and plan objects exist.
 */
private function _ensure_mock_objects() {
    $plan_name = ! empty( $this->_gpl_detected_plan_name )
        ? $this->_gpl_detected_plan_name
        : WP_FS__MOCK_PLAN_NAME;
    $plan_title = ucwords( str_replace( array( '_', '-' ), ' ', $plan_name ) );
    $now = date( 'Y-m-d H:i:s' );
    $plugin_id = is_object( $this->_plugin ) ? $this->_plugin->id : '1';

    // CRITICAL: All IDs must be STRINGS (not integers)
    // The account page template uses is_string($site->id) to display Site ID.
    // Integer IDs cause "No ID" display.

    if ( ! is_object( $this->_user ) ) {
        $this->_user = new FS_User();
        $this->_user->id = '1';
        $this->_user->email = 'noreply@gmail.com';
        $this->_user->first = 'Premium';
        $this->_user->last = 'User';
        $this->_user->is_verified = true;
        $this->_user->customer_id = null;
        $this->_user->gross = 0.0;
        $this->_user->public_key = 'pk_4a7c9e2f8b3d1a6e5c8f2b9d4a7c0e3f';
        $this->_user->secret_key = 'sk_8f3d1a6e5c2b9d4a7c0e3f6b8a1d4e7c';
        $this->_user->created = $now;
        $this->_user->updated = $now;
    }

    if ( ! is_object( $this->_site ) ) {
        $this->_site = new FS_Site();
        $this->_site->id = '1';
        $this->_site->site_id = '1';
        $this->_site->blog_id = function_exists('get_current_blog_id') ? get_current_blog_id() : 1;
        $this->_site->plugin_id = $plugin_id;
        $this->_site->user_id = '1';
        $this->_site->title = function_exists('get_bloginfo') ? get_bloginfo('name') : 'WordPress Site';
        $this->_site->url = function_exists('home_url') ? home_url() : 'http://localhost';
        $this->_site->version = is_object($this->_plugin) ? $this->_plugin->version : '1.0.0';
        $this->_site->language = function_exists('get_locale') ? get_locale() : 'en_US';
        $this->_site->platform_version = function_exists('get_bloginfo') ? get_bloginfo('version') : '6.0';
        $this->_site->sdk_version = $this->version;
        $this->_site->programming_language_version = phpversion();
        $this->_site->plan_id = '1';
        $this->_site->license_id = '1';
        $this->_site->trial_plan_id = null;
        $this->_site->trial_ends = null;
        $this->_site->is_premium = true;
        $this->_site->is_disconnected = false;
        $this->_site->is_active = true;
        $this->_site->is_uninstalled = false;
        $this->_site->is_beta = false;
        $this->_site->public_key = 'pk_f3b8c2a7e9d1f4a6c3e8b2d5a9f1c4e7';
        $this->_site->secret_key = 'sk_2d5a9f1c4e7b3a6c8f2e1d4a7c0e3f6b';
        $this->_site->created = $now;
        $this->_site->updated = $now;
    }

    if ( ! is_object( $this->_license ) ) {
        $this->_license = new FS_Plugin_License();
        $this->_license->id = '1';
        $this->_license->plugin_id = $plugin_id;
        $this->_license->user_id = '1';
        $this->_license->plan_id = '1';
        $this->_license->parent_license_id = null;
        $this->_license->parent_plan_name = null;
        $this->_license->parent_plan_title = null;
        $this->_license->products = null;
        $this->_license->pricing_id = null;
        $this->_license->quota = null;
        $this->_license->activated = 1;
        $this->_license->activated_local = 0;
        $this->_license->expiration = null;
        $this->_license->secret_key = 'sk_b5e0b5f8dd8689e6aca49dd6e6e1a930';
        $this->_license->is_free_localhost = true;
        $this->_license->is_block_features = false;
        $this->_license->is_cancelled = false;
        $this->_license->is_whitelabeled = false;
        $this->_license->created = $now;
        $this->_license->updated = $now;
    }

    if ( ! is_array( $this->_plans ) || empty( $this->_plans ) ) {
        $this->_plans = array();
        $mock_plan = new FS_Plugin_Plan();
        $mock_plan->id = '1';
        $mock_plan->plugin_id = $plugin_id;
        $mock_plan->name = $plan_name;
        $mock_plan->title = $plan_title;
        $mock_plan->description = '';
        $mock_plan->is_free_localhost = true;
        $mock_plan->is_block_features = false;
        $mock_plan->license_type = 0;
        $mock_plan->is_https_support = true;
        $mock_plan->trial_period = null;
        $mock_plan->is_require_subscription = false;
        $mock_plan->support_kb = '';
        $mock_plan->support_forum = '';
        $mock_plan->support_email = '';
        $mock_plan->support_phone = '';
        $mock_plan->support_skype = '';
        $mock_plan->is_success_manager = false;
        $mock_plan->is_featured = false;
        $mock_plan->is_hidden = false;
        $mock_plan->pricing = null;
        $mock_plan->features = null;
        $mock_plan->created = $now;
        $mock_plan->updated = $now;
        $this->_plans[] = $mock_plan;
    } else {
        if ( ! empty( $this->_gpl_detected_plan_name ) && isset( $this->_plans[0] ) ) {
            $this->_plans[0]->name = $plan_name;
            $this->_plans[0]->title = $plan_title;
        }
    }
}
```

#### 7b. Override all these methods (replace method bodies)

Each method below should have its body replaced. The original method signature is provided to locate it.

```php
// --- License/Plan Status Methods ---
function is_registered( $ignore_anonymous_state = false ) { return true; }
function is_paying() { return true; }
function is_free_plan() { return false; }
function is_trial() { return false; }
function is_trial_utilized() { return false; }
function is_trial_plan( $plan, $exact = false ) { return false; }
function has_features_enabled_license() { return true; }
function can_use_premium_code() { return true; }
function has_active_valid_license( $check_expiration = true ) { return true; }
function has_active_license() { return true; }
function has_any_license( $including_foreign = true ) { return true; }
function _has_premium_license() { return true; }
private static function is_active_valid_license( $license, $check_expiration = true ) { return true; }

// --- Dynamic Plan Check ---
function is_plan( $plan, $exact = false ) {
    $plan = strtolower( $plan );
    if ( 'free' !== $plan && 'trial' !== $plan ) {
        if ( ! isset( $this->_gpl_detected_plan_name ) ) {
            $this->_gpl_detected_plan_name = $plan;
        }
    }
    return true;
}

// --- Getter Methods (add _ensure_mock_objects() call) ---
function get_user() { $this->_ensure_mock_objects(); return $this->_user; }
function get_site() { $this->_ensure_mock_objects(); return $this->_site; }
function _get_license() { $this->_ensure_mock_objects(); return $this->_license; }
function get_plan() { $this->_ensure_mock_objects(); return is_array($this->_plans) && !empty($this->_plans) ? $this->_plans[0] : false; }
function get_plan_id() { $this->_ensure_mock_objects(); return $this->_site->plan_id; }
function get_plan_name() { $this->_ensure_mock_objects(); $plan = $this->get_plan(); return is_object($plan) ? $plan->name : WP_FS__MOCK_PLAN_NAME; }
function get_plan_title() { $this->_ensure_mock_objects(); $plan = $this->get_plan(); return is_object($plan) ? $plan->title : 'Professional'; }

// --- Plan/License Lookup (no API sync) ---
function _get_plan_by_id( $id, $allow_sync = true ) {
    $this->_ensure_mock_objects();
    if ( is_array( $this->_plans ) ) { foreach ( $this->_plans as $plan ) { if ( $id == $plan->id ) return $plan; } }
    return is_array( $this->_plans ) && !empty( $this->_plans ) ? $this->_plans[0] : false;
}
// Same pattern for get_plan_by_name()

// --- Sync Methods (bypass completely) ---
function _sync_plans() { $this->_ensure_mock_objects(); return $this->_plans; }
function _sync_licenses( $site_license_id = false, $blog_id = null ) { $this->_ensure_mock_objects(); return; }
private function _sync_license( ... ) { return; }
private function _sync_plugin_license( ... ) { return; }  // Add `return;` as first line

// --- API/Fetch Methods ---
function has_api_connectivity( $flush_if_no_connectivity = false ) { $this->_has_api_connection = true; $this->_is_on = true; return true; }
function _fetch_payments( ... ) { return array(); }
function _fetch_billing( ... ) { return null; }

// --- API Scope Methods (defensive checks) ---
function get_api_user_scope( $flush = false ) {
    if ( ! isset( $this->_user_api ) || $flush ) {
        if ( ! is_object( $this->_user ) ) { $this->_ensure_mock_objects(); }
        $this->_user_api = $this->get_api_user_scope_by_user( $this->_user );
    }
    return $this->_user_api;
}
// get_api_site_scope(): add `if (!is_object($this->_site)) { $this->_ensure_mock_objects(); }` before FS_Api::instance()
// get_api_plugin_scope(): add `if (!is_object($this->_plugin)) { return new FS_Api(...mock...); }` before FS_Api::instance()

// --- Menu/UI Control ---
function has_addons() { return false; }
function is_pricing_page_visible() { return false; }

// --- Defensive Check ---
function get_account_addons() {
    // Add `if (!is_object($this->_plugin)) { return false; }` before existing logic
}
```

---

## CRITICAL Implementation Notes

### 1. All IDs Must Be Strings

The Freemius API returns all IDs as strings (e.g., `"12345"`). The account page template at `templates/account.php` line 424 checks:
```php
'value' => is_string( $site->id ) ? $site->id : fs_text_inline( 'No ID', ... )
```
If you use integer IDs (`1`), `is_string(1)` returns `false` and "No ID" is displayed.

**Always use `'1'` (string) not `1` (integer) for all mock IDs.**

### 2. Root Path License Activation

When "Change License" is clicked, the SDK calls `$api->call('/', 'put', $params)` via the site-scope API. The `_call()` method must handle the root path `/` (and `/?show_pending=true`) by checking `$this->_scope` to return the correct entity type. Without this, the response lacks an `id` property, causing `is_api_result_entity()` to fail and displaying a raw `var_export()` dump.

### 3. Dynamic Plan Detection Limitation

The plan name is captured from the first `is_plan('plan_name')` call. Plugins that never call `is_plan()` (single-plan plugins using only `is_paying()`) will show the fallback "Professional" name. This is cosmetic only - all features work regardless.

---

## Multi-Layer API Interception

All network communication is intercepted at **every possible layer**:

| Layer | Method | Interception |
|-------|--------|-------------|
| API Manager | `FS_Api::_call()` | Scope-aware mock responses with valid entity IDs |
| API Manager | `FS_Api::call()` | Routes through `_call()` |
| API Manager | `FS_Api::remote_request()` | Returns mock HTTP response array |
| WordPress SDK | `Freemius_Api_WordPress::MakeRequest()` | Returns mock success object |
| WordPress SDK | `Freemius_Api_WordPress::RemoteRequest()` | Returns mock HTTP response array |
| WordPress SDK | `Freemius_Api_WordPress::Ping()` | Returns pong response |
| Freemius Core | `_sync_license()` | Bypassed (immediate return) |
| Freemius Core | `_sync_plugin_license()` | Bypassed (immediate return) |
| Freemius Core | `_sync_plans()` | Returns mock plans |
| Freemius Core | `_sync_licenses()` | Bypassed (immediate return) |
| Freemius Core | `_fetch_payments()` | Returns empty array |
| Freemius Core | `_fetch_billing()` | Returns null |
| Freemius Core | `_fetch_newer_version()` | Returns false (updates endpoint returns 404) |

**Note**: Two `wp_remote_*` calls are intentionally NOT intercepted:
- `class-fs-plugin-updater.php`: `wp_remote_get()` to `api.wordpress.org` (WP.org plugin info, not Freemius)
- `class-fs-clone-manager.php`: `wp_remote_post()` to `admin-post.php` (local self-request for async processing)

## Issues Resolved

1. Red Debug Status -> Green "Connected"
2. "Unknown" Connectivity -> "Connected"
3. PHP foreach errors -> Fixed with null array checks
4. Account page errors -> Fixed with comprehensive mock objects (ALL properties)
5. Undefined property errors -> Fixed by bypassing sync methods
6. Plan display "FREE" -> Shows auto-detected or fallback plan name
7. SDK loading conflicts -> Fixed with version 999.99.99
8. API timeout errors -> ALL API calls intercepted at every layer
9. Sync-related errors -> ALL sync methods bypassed
10. Payment/billing errors -> Safe return values
11. **"No ID" Site ID display** -> Fixed by using string IDs (`'1'` not `1`)
12. Email not verified -> Mock user has `is_verified = true`
13. Missing site properties -> ALL FS_Site properties set
14. "Property on false" errors -> Defensive object checks
15. Dynamic property warnings -> ALL entity properties explicitly set
16. API scope creation errors -> Mock fallbacks with object validation
17. "Class FS_Billing not found" -> Added require in require.php
18. Unwanted addons tab -> `has_addons()` returns false
19. Unwanted pricing menu -> `is_pricing_page_visible()` returns false
20. Wrong plan name for plugins -> Dynamic detection from `is_plan()` calls
21. Direct HTTP calls bypassing API -> `RemoteRequest()` and `remote_request()` overridden
22. `_user->id` on false errors -> `get_api_user_scope()` ensures mock objects first
23. `_plans` iteration on non-array -> `_sync_plans()` and `is_trial_plan()` overridden
24. `get_plan_id()` crash -> `_ensure_mock_objects()` call added
25. License sync accessing unset objects -> `_sync_licenses()` bypassed
26. **"Change License" shows raw object dump** -> Root path `/` handling with scope-aware responses
27. **License key rejected** -> All API responses return valid entities, any key accepted
28. **`Undefined property: stdClass::$release_mode`** -> Updates endpoint returns 404 error so `_fetch_newer_version()` returns false before accessing `release_mode`
29. **Old SDK account records remain in DB** -> Auto-reset runs once per detected Freemius plugin install/change and clears legacy Freemius account data

## Files Modified

| File | Lines Changed | Purpose |
|------|--------------|---------|
| `start.php` | 1 + reset routine | SDK version `999.99.99` + run-once-per-plugin-set accounts reset |
| `config.php` | 13 | Mock plan constants + reset feature toggle constant |
| `require.php` | 2 | FS_Billing class loading |
| `includes/class-freemius.php` | ~40 methods | Core bypass, mock objects, plan detection, sync bypass, defensive checks |
| `includes/entities/class-fs-plugin-license.php` | 3 methods | `is_active()`, `is_expired()`, `is_features_enabled()` |
| `includes/sdk/FreemiusWordPress.php` | 3 methods | `MakeRequest()`, `RemoteRequest()`, `Ping()` |
| `includes/class-fs-api.php` | 3 methods + 1 property | `_call()`, `remote_request()`, `$_scope` property, constructor |

### Additional `start.php` changes

- Added helper: `fs_gpltimes_maybe_reset_accounts_once()`
- Added reset signature option: `fs_gpltimes_accounts_reset_signature_v1`
- Added run-once-per-plugin-set reset logic for `fs_accounts` + `fs_active_plugins`
- Added toggle constant in `config.php`: `WP_FS__GPLTIMES_ENABLE_AUTO_ACCOUNTS_RESET`

## Compatibility

- WordPress 5.0+
- PHP 7.0+ (including PHP 8.0, 8.1, 8.2, 8.3)
- All Freemius-powered plugins and themes
- Single site and multisite installations
- Based on Freemius SDK version 2.13.4

## Attribution

**Original Freemius SDK**: https://github.com/Freemius/wordpress-sdk
**Modified by**: GPL Times - https://www.gpltimes.com
**License**: GPL v3.0

---

*This modified SDK enables all premium features without requiring license activation. Use responsibly and consider supporting original developers.*
