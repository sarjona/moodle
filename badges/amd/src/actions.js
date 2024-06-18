// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Various actions on badges - enabling, disabling, etc.
 *
 * @module      core_badges/actions
 * @copyright   2024 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import selectors from 'core_badges/selectors';
import Notification from 'core/notification';
import {prefetchStrings} from 'core/prefetch';
import {getString} from 'core/str';
import Ajax from 'core/ajax';

/**
 * Initialize module
 */
export const init = () => {
    prefetchStrings('core_badges', [
        'reviewconfirm',
    ]);
    prefetchStrings('core', [
        'confirm',
        'enable',
    ]);

    registerEventListeners();
};

/**
 * Register events for delete preset option in action menu.
 */
const registerEventListeners = () => {
    document.addEventListener('click', (event) => {
        const enableOption = event.target.closest(selectors.actions.enablebadge);
        if (enableOption) {
            event.preventDefault();
            enableBadgeConfirm(enableOption);
        }

        const disableOption = event.target.closest(selectors.actions.disablebadge);
        if (disableOption) {
            event.preventDefault();
            const badgeId = disableOption.getAttribute('data-badgeid');
            disableBadge(badgeId);
        }
    });
};

/**
 * Show the confirmation modal to enable badge.
 *
 * @param {HTMLElement} enableOption the badge to enable.
 */
const enableBadgeConfirm = (enableOption) => {
    const badgeId = enableOption.getAttribute('data-badgeid');
    const badgeName = enableOption.getAttribute('data-badgename');

    Notification.saveCancelPromise(
        getString('confirm', 'core'),
        getString('reviewconfirm', 'core_badges', badgeName),
        getString('enable', 'core'),
    ).then(() => {
        return enableBadge(badgeId);
    }).catch(() => {
        return;
    });
};

/**
 * Enable the badge.
 *
 * @param {int} badgeId The id of the badge to enable.
 * @return {promise} Resolved with the result and warnings of enabling a badge.
 */
async function enableBadge(badgeId) {
    var request = {
        methodname: 'core_badges_enable_badges',
        args: {
            badgeids: {
                badgeid: badgeId,
            },
        }
    };
    try {
        await Ajax.call([request])[0];
        document.location.reload();
    } catch (error) {
        Notification.exception(error);
    }
}

/**
 * Disable the badge.
 *
 * @param {int} badgeId The id of the badge to disable.
 * @return {promise} Resolved with the result and warnings of disabling a badge.
 */
async function disableBadge(badgeId) {
    var request = {
        methodname: 'core_badges_disable_badges',
        args: {
            badgeids: {
                badgeid: badgeId,
            },
        }
    };
    try {
        await Ajax.call([request])[0];
        document.location.reload();
    } catch (error) {
        Notification.exception(error);
    }
}
