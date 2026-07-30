<?php

namespace Blocksy;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The Freemius SDK injects the PRO update into the `update_plugins` site
 * transient through its FS_Plugin_Updater pre-set saving filter, but skips
 * doing so whenever `upgrader_process_complete` is the current action — the
 * exact context WordPress uses to forcibly rebuild the transient after every
 * upgrade. Completing any unrelated update (e.g. the Blocksy theme) therefore
 * drops the PRO entry from the updates list until the next scheduled check
 * (up to 12h) or a manual "Check again".
 *
 * Re-saving the transient on `shutdown` re-runs the saving filters in a clean
 * context, letting the SDK itself restore the entry. Nothing is discarded and
 * no extra update-check request is made.
 */
class CompanionUpdateTransientFix {
	public function __construct() {
		add_action(
			'upgrader_process_complete',
			[$this, 'schedule_update_plugins_reinjection'],
			10,
			2
		);
	}

	public function schedule_update_plugins_reinjection($upgrader, $hook_extra) {
		if (! function_exists('blocksy_companion_fs')) {
			return;
		}

		// When PRO itself was updated its absence from the transient is
		// correct — re-running the filter from the still-loaded old code
		// would advertise a phantom update for the version already on disk.
		if ($this->upgrade_includes_companion($hook_extra)) {
			return;
		}

		add_action('shutdown', [$this, 'reinject_update_plugins_transient']);
	}

	public function reinject_update_plugins_transient() {
		$transient = get_site_transient('update_plugins');

		if (! is_object($transient)) {
			return;
		}

		if ($this->transient_has_companion($transient)) {
			return;
		}

		set_site_transient('update_plugins', $transient);
	}

	/**
	 * When the SDK filter runs it always files the plugin under exactly one
	 * of the two buckets, so missing from both means it was skipped.
	 */
	private function transient_has_companion($transient) {
		if (
			isset($transient->response)
			&&
			isset($transient->response[BLOCKSY_PLUGIN_BASE])
		) {
			return true;
		}

		if (
			isset($transient->no_update)
			&&
			isset($transient->no_update[BLOCKSY_PLUGIN_BASE])
		) {
			return true;
		}

		return false;
	}

	private function upgrade_includes_companion($hook_extra) {
		if (! is_array($hook_extra)) {
			return false;
		}

		if (
			isset($hook_extra['plugin'])
			&&
			$hook_extra['plugin'] === BLOCKSY_PLUGIN_BASE
		) {
			return true;
		}

		if (
			isset($hook_extra['plugins'])
			&&
			is_array($hook_extra['plugins'])
			&&
			in_array(BLOCKSY_PLUGIN_BASE, $hook_extra['plugins'], true)
		) {
			return true;
		}

		return false;
	}
}
