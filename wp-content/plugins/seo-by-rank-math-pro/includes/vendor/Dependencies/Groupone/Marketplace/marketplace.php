<?php
namespace RankMathPro\Dependencies\Groupone\Marketplace;
use RankMathPro\Dependencies\Groupone\Marketplace\Controllers\MarketplaceController;

/**
 * Market Place Embeddable Module
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Marketplace {
	/**
	 * Boots the Marketplace with given config.
	 *
	 * @param array $config Configuration options for the marketplace module.
	 */
	private static $booted_locations = [];
	 
	public static function run( array $config = [] ) {
	    
        // Prevent duplicate Marketplace boot for same menu location
	    $parent = $config['parent_menu_slug'] ?? '';
        $slug   = $config['menu_slug']        ?? '';
        $key = $parent . '::' . $slug;
        
        if ( isset(self::$booted_locations[$key]) ) {
            return;
        }
        self::$booted_locations[$key] = true;
        
		try {
			MarketplaceController::boot($config);
		} catch (\Exception $e) {
			error_log($e->getMessage());
		}
	}
}