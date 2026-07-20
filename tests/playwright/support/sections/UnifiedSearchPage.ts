/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

/**
 * The unified search in the top header: the combobox input and the results
 * popover it controls. Desktop layout only (the mobile header collapses to a
 * button that opens a fullscreen modal without the combobox role).
 */
export class UnifiedSearchPage {
	constructor(private readonly page: Page) {}

	private get header(): Locator {
		return this.page.locator('header#header')
	}

	/**
	 * The header search field. Its accessible name is the placeholder text, and
	 * its role is combobox (it controls the results listbox).
	 */
	input(): Locator {
		return this.header.getByRole('combobox', { name: 'Apps, files, messages, and more' })
	}

	/**
	 * The results popover. Also the aria-controls target and the host of the rows
	 * aria-activedescendant points at. Absent from the DOM while the search is closed.
	 */
	panel(): Locator {
		return this.page.locator('#unified-search-results')
	}

	/** Every selectable result row, flattened across all provider sections. */
	options(): Locator {
		return this.panel().getByRole('option')
	}

	/**
	 * A single result row addressed by its DOM id (the value the input carries in
	 * aria-activedescendant). Attribute selector so provider ids with dots or colons
	 * can't break a `#id` lookup.
	 *
	 * @param id the row's element id
	 */
	option(id: string): Locator {
		return this.page.locator(`[id="${id}"]`)
	}

	/** Filter controls (Places, Date, People, …) in the open search panel. */
	filtersBar(): Locator {
		return this.panel().locator('[data-cy-unified-search-filters]')
	}

	/** Applied filter chips rendered below the filter controls. */
	appliedFilters(): Locator {
		return this.panel().locator('.unified-search-modal__filters-applied')
	}

	/** A single applied filter chip matching the given label text. */
	filterChip(text: string | RegExp): Locator {
		return this.appliedFilters().filter({ hasText: text })
	}

	/**
	 * Focus the header search and open the results panel.
	 * A non-empty query is required on desktop for the panel (and its filters) to render.
	 */
	async openWithQuery(query = 'test'): Promise<void> {
		await this.input().click()
		await this.input().fill(query)
		await this.panel().waitFor({ state: 'visible' })
	}

	/** Apply a quick date filter from the Date menu. */
	async applyDateFilter(label: 'Today' | 'Last 7 days' | 'Last 30 days' | 'This year' | 'Last year'): Promise<void> {
		await this.filtersBar()
			.locator('[data-cy-unified-search-filter="date"]')
			.getByRole('button', { name: 'Date' })
			.click()
		await this.page.getByRole('menuitem', { name: label }).click()
	}

	/**
	 * Remove an applied filter via its chip close control.
	 * NcChip exposes the close action as a button named "Remove filter: {text}".
	 */
	async removeFilterChip(filterText: string): Promise<void> {
		await this.filterChip(filterText)
			.getByRole('button', { name: `Remove filter: ${filterText}` })
			.click({ force: true })
	}
}
