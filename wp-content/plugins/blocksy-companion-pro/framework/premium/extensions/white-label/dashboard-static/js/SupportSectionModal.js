import { createElement } from '@wordpress/element'
import { __, sprintf } from 'ct-i18n'
import { Overlay } from 'blocksy-options'

const SupportSectionModal = ({
	activeSupportSection,
	setActiveSupportSection,
	whiteLabelSettings,
	setWhiteLabelSettings,
}) => {
	return (
		<Overlay
			items={!!activeSupportSection}
			className="ct-white-label-section-modal"
			onDismiss={() => setActiveSupportSection(null)}
			render={() => {
				if (!activeSupportSection) {
					return null
				}

				const { key } = activeSupportSection

				const sectionValues = activeSupportSection.values || {
					title: '',
					description: '',
					buttonText: '',
					link: '',
				}

				return (
					<div className="ct-modal-content">
						<h2>
							{sprintf(
								__('%s Content', 'blocksy-companion'),
								activeSupportSection.label
							)}
						</h2>

						<div className="ct-modal-scroll">
							<div className="ct-white-label-option">
								<label htmlFor={`ct-white-label-${key}-title`}>
									{__('Title', 'blocksy-companion')}
								</label>
								<input
									type="text"
									id={`ct-white-label-${key}-title`}
									value={sectionValues.title}
									onChange={({ target: { value: title } }) => {
										setActiveSupportSection((current) => ({
											...current,
											values: {
												...current.values,
												title,
											},
										}))
									}}
								/>
							</div>

							<div className="ct-white-label-option">
								<label
									htmlFor={`ct-white-label-${key}-description`}>
									{__('Description', 'blocksy-companion')}
								</label>
								<textarea
									id={`ct-white-label-${key}-description`}
									rows="3"
									value={sectionValues.description}
									onChange={({
										target: { value: description },
									}) => {
										setActiveSupportSection((current) => ({
											...current,
											values: {
												...current.values,
												description,
											},
										}))
									}}
								/>
							</div>

							<div className="ct-white-label-option">
								<label
									htmlFor={`ct-white-label-${key}-button-text`}>
									{__('Button Text', 'blocksy-companion')}
								</label>
								<input
									type="text"
									id={`ct-white-label-${key}-button-text`}
									value={sectionValues.buttonText}
									onChange={({
										target: { value: buttonText },
									}) => {
										setActiveSupportSection((current) => ({
											...current,
											values: {
												...current.values,
												buttonText,
											},
										}))
									}}
								/>
							</div>

							<div className="ct-white-label-option">
								<label htmlFor={`ct-white-label-${key}-link`}>
									{__('Button Link', 'blocksy-companion')}
								</label>
								<input
									type="text"
									id={`ct-white-label-${key}-link`}
									value={sectionValues.link}
									onChange={({ target: { value: link } }) => {
										setActiveSupportSection((current) => ({
											...current,
											values: {
												...current.values,
												link,
											},
										}))
									}}
								/>
							</div>
						</div>

						<div className="ct-modal-actions has-divider">
							<button
								type="button"
								className="ct-ui-button ct-ui-button-primary"
								onClick={() => {
									if (key === 'support') {
										setWhiteLabelSettings({
											...whiteLabelSettings,
											[key]: {
												...whiteLabelSettings[key],
												title: sectionValues.title,
												description:
													sectionValues.description,
												buttonText:
													sectionValues.buttonText,
												link: sectionValues.link,
											},
										})
									} else {
										setWhiteLabelSettings({
											...whiteLabelSettings,
											[key]: {
												...whiteLabelSettings[key],
												...sectionValues,
											},
										})
									}
									setActiveSupportSection(null)
								}}>
								{__('Save Options', 'blocksy-companion')}
							</button>
						</div>
					</div>
				)
			}}
		/>
	)
}

export default SupportSectionModal
