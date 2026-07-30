import { createElement, Fragment } from '@wordpress/element'
import { __ } from 'ct-i18n'
import { Switch } from 'blocksy-options'

const AdvancedTab = ({
	whiteLabelSettings,
	setWhiteLabelSettings,
	setActiveSupportSection,
}) => {
	return (
		<div className="ct-white-label-advanced">
			{[
				...(!ctDashboardLocalizations.plugin_data.is_multisite
					? [
							{
								id: 'hide_billing_account',
								text: __(
									'Account Menu Item',
									'blocksy-companion'
								),
							},
					  ]
					: []),

				{
					id: 'hide_demos',
					text: __('Starter Sites Tab', 'blocksy-companion'),
				},
				{
					id: 'hide_plugins_tab',
					text: __('Useful Plugins Tab', 'blocksy-companion'),
				},

				{
					id: 'hide_changelogs_tab',
					text: __('Changelog Tab', 'blocksy-companion'),
				},

				{
					id: 'hide_docs_section',
					text: __('Extension Documentation Link', 'blocksy-companion'),
				},

				{
					id: 'hide_video_section',
					text: __('Extension Video Link', 'blocksy-companion'),
				},
			].map(({ id, text }) => (
				<div
					key={id}
					className="ct-white-label-switch"
					onClick={() =>
						setWhiteLabelSettings({
							...whiteLabelSettings,
							[id]: !whiteLabelSettings[id],
						})
					}>
					<span>{text}</span>
					<Switch
						option={{
							behavior: 'boolean',
						}}
						value={!whiteLabelSettings[id]}
						onChange={() => {}}
					/>
				</div>
			))}

			{[
				{
					key: 'support',
					label: __('Support Section', 'blocksy-companion'),
					hideKey: 'hide_support_section',
				},
				{
					key: 'knowledge_base',
					label: __('Knowledge Base Section', 'blocksy-companion'),
					hideKey: 'hide_support_docs_section',
				},
				{
					key: 'video_tutorials',
					label: __('Video Tutorials Section', 'blocksy-companion'),
					hideKey: 'hide_support_video_section',
				},
				{
					key: 'facebook',
					label: __('Facebook Section', 'blocksy-companion'),
					hideKey: 'hide_support_facebook_section',
				},
			].map(({ key, label, hideKey }) => (
				<div className="ct-white-label-switch">
					<span>{label}</span>

					{!whiteLabelSettings[hideKey] && (
						<button
							type="button"
							onClick={(event) => {
								event.preventDefault()
								event.stopPropagation()
								setActiveSupportSection({
									key,
									label,
									values: {
										title:
											whiteLabelSettings[key]?.title ||
											'',
										description:
											whiteLabelSettings[key]
												?.description || '',
										buttonText:
											whiteLabelSettings[key]
												?.buttonText || '',
										link:
											key === 'support'
												? whiteLabelSettings[key]
														?.link ||
												  whiteLabelSettings.author
														?.support ||
												  ''
												: whiteLabelSettings[key]
														?.link || '',
									},
								})
							}}>
							<svg width="13px" height="13px"  viewBox="0 0 20 20"><path d="M20 11.765h-2.565a7.5 7.5 0 0 1-.953 2.27l1.812 1.812-2.47 2.47-1.812-1.811a7.6 7.6 0 0 1-2.247.93V20h-3.53v-2.565a7.6 7.6 0 0 1-2.247-.93l-1.812 1.813-2.494-2.494 1.812-1.812a7.6 7.6 0 0 1-.93-2.247H0V8.27h2.553a7.7 7.7 0 0 1 .941-2.283L1.682 4.176l2.471-2.47 1.812 1.812a7.6 7.6 0 0 1 2.27-.953V0h3.53v2.565a7.6 7.6 0 0 1 2.247.93l1.812-1.813 2.494 2.494-1.812 1.812c.423.694.753 1.46.941 2.283H20zm-10 1.764c1.953 0 3.53-1.576 3.53-3.529S11.952 6.47 10 6.47 6.47 8.048 6.47 10s1.577 3.53 3.53 3.53"/></svg>
						</button>
					)}

					<Switch
						option={{
							behavior: 'boolean',
						}}
						value={!whiteLabelSettings[hideKey]}
						onChange={() => {
							setWhiteLabelSettings({
								...whiteLabelSettings,
								[hideKey]: !whiteLabelSettings[hideKey],
							})
						}}
					/>
				</div>
			))}

			<div
				className="ct-white-label-switch"
				onClick={() =>
					setWhiteLabelSettings({
						...whiteLabelSettings,
						locked: !whiteLabelSettings.locked,
					})
				}>
				<span>{__('White Label Extension', 'blocksy-companion')}</span>
				<Switch
					option={{
						behavior: 'boolean',
					}}
					value={!whiteLabelSettings.locked}
					onChange={() => {}}
				/>
			</div>

			{whiteLabelSettings.locked && (
				<div className="extension-notice">
					{__(
						'Please note that disabling this option will hide the white-label extension. To restore it, press the SHIFT key while clicking the dashboard logo.',
						'blocksy-companion'
					)}
				</div>
			)}
		</div>
	)
}

export default AdvancedTab
