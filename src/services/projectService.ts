/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosResponse } from '@nextcloud/axios'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export interface ProjectOption {
	id: number
	name: string | null
	number: string | null
	talk_conversation_token: string | null
	talk_url: string | null
}

class ProjectService {
	async listMyProjects(): Promise<ProjectOption[]> {
		const response: AxiosResponse<ProjectOption[]> = await axios.get(generateUrl('/apps/projectcreatoraio/api/v1/projects/mine'), {
			headers: {
				'OCS-APIRequest': 'true',
				'Content-Type': 'application/json',
			},
			withCredentials: true,
		})

		return response.data ?? []
	}
}

export const projectService = new ProjectService()
