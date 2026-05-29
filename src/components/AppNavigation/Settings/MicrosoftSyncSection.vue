<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->

<template>
	<section class="microsoft-sync-section">
		<div class="microsoft-sync-section__halo" aria-hidden="true" />
		<div class="microsoft-sync-section__header">
			<div class="microsoft-sync-section__mark" aria-hidden="true">
				<svg class="microsoft-sync-section__mark-icon" viewBox="0 0 23 23" focusable="false" aria-hidden="true">
					<rect x="1" y="1" width="10" height="10" fill="#F25022" />
					<rect x="12" y="1" width="10" height="10" fill="#7FBA00" />
					<rect x="1" y="12" width="10" height="10" fill="#00A4EF" />
					<rect x="12" y="12" width="10" height="10" fill="#FFB900" />
				</svg>
			</div>
			<div class="microsoft-sync-section__title-wrap">
				<h3 class="microsoft-sync-section__title">
					{{ t('calendar', 'Microsoft Outlook Calendar sync') }}
				</h3>
				<p class="microsoft-sync-section__subtitle">
					{{ statusText }}
				</p>
			</div>
			<span :class="['microsoft-sync-section__badge', badgeClass]">
				{{ badgeText }}
			</span>
		</div>

		<div v-if="loading" class="microsoft-sync-section__body">
			{{ t('calendar', 'Checking Microsoft Outlook connection …') }}
		</div>

		<div v-else class="microsoft-sync-section__body">
			<div v-if="!configured" class="microsoft-sync-section__notice">
				<strong>{{ t('calendar', 'Server setup required') }}</strong>
				<span>{{ t('calendar', 'An administrator needs to configure the Microsoft OAuth client ID and secret before users can connect.') }}</span>
			</div>

			<div v-if="connected" class="microsoft-sync-section__account">
				<span class="microsoft-sync-section__account-label">{{ t('calendar', 'Connected account') }}</span>
				<span class="microsoft-sync-section__account-email">{{ email }}</span>
			</div>

			<div class="microsoft-sync-section__actions">
				<NcButton
					v-if="!connected"
					type="button"
					variant="primary"
					:disabled="!configured"
					@click="connect">
					{{ t('calendar', 'Connect Microsoft account') }}
				</NcButton>
				<NcButton
					v-else
					type="button"
					variant="error"
					:disabled="disconnecting"
					@click="disconnect">
					{{ disconnecting ? t('calendar', 'Disconnecting …') : t('calendar', 'Disconnect') }}
				</NcButton>
			</div>

			<p class="microsoft-sync-section__footnote">
				{{ t('calendar', 'This only connects your account for now. Calendar event sync will be added separately.') }}
			</p>
		</div>
	</section>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { NcButton } from '@nextcloud/vue'
import {
	disconnectMicrosoftSync,
	getMicrosoftConnectUrl,
	getMicrosoftSyncStatus,
} from '../../../services/microsoftSyncService.js'

export default {
	name: 'MicrosoftSyncSection',
	components: {
		NcButton,
	},
	data() {
		return {
			loading: true,
			disconnecting: false,
			configured: false,
			connected: false,
			email: '',
		}
	},
	computed: {
		badgeText() {
			if (!this.configured) {
				return this.t('calendar', 'Not configured')
			}
			if (this.connected) {
				return this.t('calendar', 'Connected')
			}
			return this.t('calendar', 'Ready')
		},
		badgeClass() {
			if (!this.configured) {
				return 'microsoft-sync-section__badge--warning'
			}
			if (this.connected) {
				return 'microsoft-sync-section__badge--success'
			}
			return 'microsoft-sync-section__badge--ready'
		},
		statusText() {
			if (!this.configured) {
				return this.t('calendar', 'Configure Microsoft OAuth credentials to enable account linking.')
			}
			if (this.connected) {
				return this.t('calendar', 'Your Microsoft account is connected and ready for the next sync step.')
			}
			return this.t('calendar', 'Connect Microsoft Outlook Calendar to verify OAuth is set up correctly.')
		},
	},
	mounted() {
		this.loadStatus()
	},
	methods: {
		async loadStatus() {
			this.loading = true
			try {
				const status = await getMicrosoftSyncStatus()
				this.configured = status.configured
				this.connected = status.connected
				this.email = status.email || ''
			} catch (error) {
				showError(this.t('calendar', 'Could not load Microsoft Outlook Calendar sync status.'))
			} finally {
				this.loading = false
			}
		},
		connect() {
			if (!this.configured) {
				return
			}
			window.location.href = getMicrosoftConnectUrl()
		},
		async disconnect() {
			this.disconnecting = true
			try {
				await disconnectMicrosoftSync()
				showSuccess(this.t('calendar', 'Microsoft account disconnected.'))
				await this.loadStatus()
			} catch (error) {
				showError(this.t('calendar', 'Could not disconnect Microsoft account.'))
			} finally {
				this.disconnecting = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.microsoft-sync-section {
	position: relative;
	overflow: hidden;
	margin-block: 12px 18px;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background:
		linear-gradient(135deg, rgba(0, 164, 239, 0.14), transparent 34%),
		linear-gradient(160deg, var(--color-main-background), var(--color-background-hover));
	box-shadow: 0 10px 28px rgba(0, 0, 0, 0.07);
}

.microsoft-sync-section__halo {
	position: absolute;
	right: -50px;
	top: -70px;
	width: 170px;
	height: 170px;
	border-radius: 999px;
	background: conic-gradient(from 20deg, #f25022, #7fba00, #00a4ef, #ffb900, #f25022);
	opacity: 0.2;
	filter: blur(1px);
}

.microsoft-sync-section__header {
	position: relative;
	display: flex;
	align-items: center;
	gap: 12px;
}

.microsoft-sync-section__mark {
	display: grid;
	width: 44px;
	height: 44px;
	place-items: center;
	border-radius: 16px;
	background: #fff;
	box-shadow: 0 8px 18px rgba(0, 164, 239, 0.2);
}

.microsoft-sync-section__mark-icon {
	width: 32px;
	height: 32px;
}

.microsoft-sync-section__title-wrap {
	min-width: 0;
	flex: 1;
}

.microsoft-sync-section__title {
	margin: 0;
	font-size: 18px;
	font-weight: 700;
}

.microsoft-sync-section__subtitle {
	margin: 2px 0 0;
	color: var(--color-text-maxcontrast);
	line-height: 1.35;
}

.microsoft-sync-section__badge {
	padding: 4px 10px;
	border-radius: 999px;
	font-size: 12px;
	font-weight: 700;
	white-space: nowrap;
}

.microsoft-sync-section__badge--warning {
	background: rgba(251, 188, 5, 0.22);
	color: #7a5700;
}

.microsoft-sync-section__badge--ready {
	background: rgba(0, 164, 239, 0.16);
	color: #0078d4;
}

.microsoft-sync-section__badge--success {
	background: rgba(127, 186, 0, 0.18);
	color: #3a7a00;
}

.microsoft-sync-section__body {
	position: relative;
	margin-top: 16px;
}

.microsoft-sync-section__notice,
.microsoft-sync-section__account {
	display: flex;
	flex-direction: column;
	gap: 3px;
	margin-bottom: 14px;
	padding: 12px;
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
}

.microsoft-sync-section__account-label,
.microsoft-sync-section__footnote {
	color: var(--color-text-maxcontrast);
}

.microsoft-sync-section__account-email {
	font-weight: 700;
	word-break: break-word;
}

.microsoft-sync-section__actions {
	display: flex;
	margin-top: 16px;
}

.microsoft-sync-section__footnote {
	margin: 12px 0 0;
	font-size: 13px;
}

@media (max-width: 640px) {
	.microsoft-sync-section__header {
		align-items: stretch;
		flex-direction: column;
	}

	.microsoft-sync-section__badge {
		align-self: flex-start;
	}
}
</style>
