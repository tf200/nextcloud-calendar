/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import HttpClient from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

async function getGoogleSyncStatus() {
	const response = await HttpClient.get(generateUrl('/apps/calendar/google/status'))
	return response.data.data
}

async function disconnectGoogleSync() {
	await HttpClient.post(generateUrl('/apps/calendar/google/disconnect'))
}

function getGoogleConnectUrl() {
	return generateUrl('/apps/calendar/google/connect')
}

export {
	disconnectGoogleSync,
	getGoogleConnectUrl,
	getGoogleSyncStatus,
}
