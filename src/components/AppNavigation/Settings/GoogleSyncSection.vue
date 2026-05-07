<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section class="google-sync-section">
		<div class="google-sync-section__halo" aria-hidden="true" />
		<div class="google-sync-section__header">
			<div class="google-sync-section__mark" aria-hidden="true">
				<span class="google-sync-section__mark-letter">G</span>
			</div>
			<div class="google-sync-section__title-wrap">
				<h3 class="google-sync-section__title">
					{{ t('calendar', 'Google Calendar sync') }}
				</h3>
				<p class="google-sync-section__subtitle">
					{{ statusText }}
				</p>
			</div>
			<span :class="['google-sync-section__badge', badgeClass]">
				{{ badgeText }}
			</span>
		</div>

		<div v-if="loading" class="google-sync-section__body">
			{{ t('calendar', 'Checking Google Calendar connection …') }}
		</div>

		<div v-else class="google-sync-section__body">
			<div v-if="!configured" class="google-sync-section__notice">
				<strong>{{ t('calendar', 'Server setup required') }}</strong>
				<span>{{ t('calendar', 'An administrator needs to configure the Google OAuth client ID and secret before users can connect.') }}</span>
			</div>

			<div v-if="connected" class="google-sync-section__account">
				<span class="google-sync-section__account-label">{{ t('calendar', 'Connected account') }}</span>
				<span class="google-sync-section__account-email">{{ email }}</span>
			</div>

			<div class="google-sync-section__callback">
				<label class="google-sync-section__callback-label" for="google-sync-callback-url">
					{{ t('calendar', 'Google Console redirect URI') }}
				</label>
				<div class="google-sync-section__callback-row">
					<input
						id="google-sync-callback-url"
						class="google-sync-section__callback-input"
						readonly
						:value="callbackUrl">
					<NcButton type="button" variant="tertiary" @click="copyCallbackUrl">
						{{ t('calendar', 'Copy') }}
					</NcButton>
				</div>
			</div>

			<div class="google-sync-section__actions">
				<NcButton
					v-if="!connected"
					type="button"
					variant="primary"
					:disabled="!configured"
					@click="connect">
					{{ t('calendar', 'Connect Google account') }}
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

			<p class="google-sync-section__footnote">
				{{ t('calendar', 'This only connects your account for now. Calendar event sync will be added separately.') }}
			</p>
		</div>
	</section>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { NcButton } from '@nextcloud/vue'
import {
	disconnectGoogleSync,
	getGoogleConnectUrl,
	getGoogleSyncStatus,
} from '../../../services/googleSyncService.js'

export default {
	name: 'GoogleSyncSection',
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
			callbackUrl: '',
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
				return 'google-sync-section__badge--warning'
			}
			if (this.connected) {
				return 'google-sync-section__badge--success'
			}
			return 'google-sync-section__badge--ready'
		},
		statusText() {
			if (!this.configured) {
				return this.t('calendar', 'Configure Google OAuth credentials to enable account linking.')
			}
			if (this.connected) {
				return this.t('calendar', 'Your Google account is connected and ready for the next sync step.')
			}
			return this.t('calendar', 'Connect Google Calendar to verify OAuth is set up correctly.')
		},
	},
	mounted() {
		this.loadStatus()
	},
	methods: {
		async loadStatus() {
			this.loading = true
			try {
				const status = await getGoogleSyncStatus()
				this.configured = status.configured
				this.connected = status.connected
				this.email = status.email || ''
				this.callbackUrl = status.callbackUrl || ''
			} catch (error) {
				showError(this.t('calendar', 'Could not load Google Calendar sync status.'))
			} finally {
				this.loading = false
			}
		},
		connect() {
			if (!this.configured) {
				return
			}
			window.location.href = getGoogleConnectUrl()
		},
		async disconnect() {
			this.disconnecting = true
			try {
				await disconnectGoogleSync()
				showSuccess(this.t('calendar', 'Google account disconnected.'))
				await this.loadStatus()
			} catch (error) {
				showError(this.t('calendar', 'Could not disconnect Google account.'))
			} finally {
				this.disconnecting = false
			}
		},
		async copyCallbackUrl() {
			if (!this.callbackUrl) {
				return
			}
			try {
				await navigator.clipboard.writeText(this.callbackUrl)
				showSuccess(this.t('calendar', 'Redirect URI copied.'))
			} catch (error) {
				showError(this.t('calendar', 'Could not copy redirect URI.'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.google-sync-section {
	position: relative;
	overflow: hidden;
	margin-block: 12px 18px;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background:
		linear-gradient(135deg, rgba(66, 133, 244, 0.14), transparent 34%),
		linear-gradient(160deg, var(--color-main-background), var(--color-background-hover));
	box-shadow: 0 10px 28px rgba(0, 0, 0, 0.07);
}

.google-sync-section__halo {
	position: absolute;
	right: -50px;
	top: -70px;
	width: 170px;
	height: 170px;
	border-radius: 999px;
	background: conic-gradient(from 20deg, #4285f4, #34a853, #fbbc05, #ea4335, #4285f4);
	opacity: 0.2;
	filter: blur(1px);
}

.google-sync-section__header {
	position: relative;
	display: flex;
	align-items: center;
	gap: 12px;
}

.google-sync-section__mark {
	display: grid;
	width: 44px;
	height: 44px;
	place-items: center;
	border-radius: 16px;
	background: conic-gradient(from 20deg, #4285f4, #34a853, #fbbc05, #ea4335, #4285f4);
	box-shadow: 0 8px 18px rgba(66, 133, 244, 0.2);
}

.google-sync-section__mark-letter {
	display: grid;
	width: 32px;
	height: 32px;
	place-items: center;
	border-radius: 50%;
	background: white;
	color: #1f1f1f;
	font-weight: 800;
	font-size: 20px;
}

.google-sync-section__title-wrap {
	min-width: 0;
	flex: 1;
}

.google-sync-section__title {
	margin: 0;
	font-size: 18px;
	font-weight: 700;
}

.google-sync-section__subtitle {
	margin: 2px 0 0;
	color: var(--color-text-maxcontrast);
	line-height: 1.35;
}

.google-sync-section__badge {
	padding: 4px 10px;
	border-radius: 999px;
	font-size: 12px;
	font-weight: 700;
	white-space: nowrap;
}

.google-sync-section__badge--warning {
	background: rgba(251, 188, 5, 0.22);
	color: #7a5700;
}

.google-sync-section__badge--ready {
	background: rgba(66, 133, 244, 0.16);
	color: #1558c0;
}

.google-sync-section__badge--success {
	background: rgba(52, 168, 83, 0.18);
	color: #1f7a3a;
}

.google-sync-section__body {
	position: relative;
	margin-top: 16px;
}

.google-sync-section__notice,
.google-sync-section__account {
	display: flex;
	flex-direction: column;
	gap: 3px;
	margin-bottom: 14px;
	padding: 12px;
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
}

.google-sync-section__account-label,
.google-sync-section__callback-label,
.google-sync-section__footnote {
	color: var(--color-text-maxcontrast);
}

.google-sync-section__account-email {
	font-weight: 700;
	word-break: break-word;
}

.google-sync-section__callback-row {
	display: flex;
	gap: 8px;
	margin-top: 6px;
}

.google-sync-section__callback-input {
	min-width: 0;
	flex: 1;
	padding-inline: 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-text-maxcontrast);
}

.google-sync-section__actions {
	display: flex;
	margin-top: 16px;
}

.google-sync-section__footnote {
	margin: 12px 0 0;
	font-size: 13px;
}

@media (max-width: 640px) {
	.google-sync-section__header,
	.google-sync-section__callback-row {
		align-items: stretch;
		flex-direction: column;
	}

	.google-sync-section__badge {
		align-self: flex-start;
	}
}
</style>
