/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import HttpClient from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

async function getMicrosoftSyncStatus() {
	const response = await HttpClient.get(generateUrl('/apps/calendar/microsoft/status'))
	return response.data.data
}

async function disconnectMicrosoftSync() {
	await HttpClient.post(generateUrl('/apps/calendar/microsoft/disconnect'))
}

function getMicrosoftConnectUrl() {
	return generateUrl('/apps/calendar/microsoft/connect')
}

export {
	disconnectMicrosoftSync,
	getMicrosoftConnectUrl,
	getMicrosoftSyncStatus,
}
