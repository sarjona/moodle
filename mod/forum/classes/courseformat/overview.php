<?php
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

namespace mod_forum\courseformat;

use action_link;
use cm_info;
use core\url;
use core_calendar\output\humandate;
use core_courseformat\local\overview\overviewitem;
use core\output\local\properties\button;
use core\output\local\properties\text_align;

/**
 * Forum overview integration.
 *
 * @package    mod_forum
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends \core_courseformat\activityoverviewbase {

    /** @var \stdClass|null $forum The forum instance. */
    private ?\stdClass $forum;

    /**
     * Constructor.
     *
     * @param cm_info $cm the course module instance.
     * @param \core\output\renderer_helper $rendererhelper the renderer helper.
     */
    public function __construct(
        cm_info $cm,
        /** @var \core\output\renderer_helper $rendererhelper the renderer helper */
        protected readonly \core\output\renderer_helper $rendererhelper,
        /** @var \core_string_manager $stringmanager the string manager */
        protected readonly \core_string_manager $stringmanager
    ) {

        parent::__construct($cm);

        // TODO: Load forum instance.
    }

    #[\Override]
    public function get_extra_overview_items(): array {
        return [
            'forumtype' => $this->get_extra_forumtype_overview(),
            'submitted' => $this->get_extra_track_overview(),
            'subscribed' => $this->get_extra_subscribed_overview(),
            'emaildigest' => $this->get_extra_emaildigest_overview(),
            'discussions' => $this->get_extra_discussions_overview(),
        ];
    }

    #[\Override]
    public function get_actions_overview(): ?overviewitem {

        $forum = $this->get_forum_instance();
        if (empty($forum)) {
            // User cannot view this forum.
            return null;
        }

        $totaldiscussions = forum_count_discussions($forum, $this->cm, $this->course);
        $discussionposts = forum_count_discussion_replies($forum->id);
        $totalreplies = array_reduce($discussionposts, function($sum, $post) {
            return $sum + $post->replies; // Add 1 to count the discussion post too.
        }, 0); // The '0' is the initial value of $sum
        $totalreplies += $totaldiscussions; // Add the discussions to the replies count.

        // if ($totalreplies === 0) {
        //     // No posts, no overview item.
        //     return new overviewitem(
        //         name: get_string('posts', 'mod_forum'),
        //         value: null,
        //         content: '-',
        //     );
        // }

        // TODO: Replace with the proper string.
        $alertlabel = get_string('numberofsubmissionsneedgrading', 'assign');

        $badge = '';
        // TODO: Get the unread posts count.
        // TODO: Decide CTA when there are no posts.
        $unread = forum_tp_count_forum_unread_posts($this->cm, $this->course);
        if ($totalreplies > 0 && $unread > 0) {
            $renderer = $this->rendererhelper->get_core_renderer();
            $badge = $renderer->notice_badge(
                contents: $unread,
                title: $alertlabel,
            );
        }

        $content = new action_link(
            url: new url('/mod/forum/view.php', ['id' => $this->cm->id]),
            text: $totalreplies .' ' . $badge,
            attributes: ['class' => button::SECONDARY_OUTLINE->classes()],
        );

        return new overviewitem(
            name: get_string('posts', 'mod_forum'),
            value: $totalreplies ? : '-',
            content: $content,
            textalign: text_align::CENTER,
            alertcount: $unread,
            alertlabel: $alertlabel,
        );
    }

    #[\Override]
    public function get_due_date_overview(): ?overviewitem {

        if ($this->is_teacher()) {
            return null;
        }

        $duedate = null;
        if (isset($this->cm->customdata['duedate'])) {
            $duedate = $this->cm->customdata['duedate'];
        }

        if (empty($duedate)) {
            return new overviewitem(
                name: get_string('duedate', 'mod_forum'),
                value: null,
                content: '-',
            );
        }
        return new overviewitem(
            name: get_string('duedate', 'mod_forum'),
            value: $duedate,
            content: humandate::create_from_timestamp($duedate),
        );
    }

    /**
     * Get the forum type item.
     *
     * @return overviewitem|null The overview item (or null if the user cannot enable/disable track).
     */
    private function get_extra_forumtype_overview(): ?overviewitem {

        if (!$this->is_teacher()) {
            return null;
        }

        $forum = $this->get_forum_instance();
        if (empty($forum)) {
            // User cannot view this forum.
            return null;
        }

        $allforumtypesnames = forum_get_forum_types_all();

        return new overviewitem(
            name: get_string('forumtype', 'mod_forum'),
            value: $allforumtypesnames[$forum->type],
        );
    }

    /**
     * Get the track toggle item.
     *
     * @return overviewitem|null The overview item (or null if the user cannot enable/disable track).
     */
    private function get_extra_track_overview(): ?overviewitem {
        global $CFG, $USER, $OUTPUT;

        $forum = $this->get_forum_instance();
        if (empty($forum)) {
            // User cannot view this forum.
            return null;
        }

        $disabled = true;
        $tracked = false;
        $label = null;
        if ((intval($forum->trackingtype) == FORUM_TRACKING_FORCED) && ($CFG->forum_allowforcedreadtracking)) {
            $label = $this->stringmanager->get_string(
                'labelvalue',
                'core',
                [
                    'label' => $this->stringmanager->get_string('trackforum', 'mod_forum'),
                    'value' => $this->stringmanager->get_string('trackingon', 'mod_forum'),
                ],
            );
            $disabled = true;
            $tracked = true;
        } else if (intval($forum->trackingtype) === FORUM_TRACKING_OFF) {
            $label = $this->stringmanager->get_string(
                'labelvalue',
                'core',
                [
                    'label' => $this->stringmanager->get_string('trackforum', 'mod_forum'),
                    'value' => $this->stringmanager->get_string('trackingoff', 'mod_forum'),
                ],
            );
            $disabled = true;
        } else if (forum_tp_can_track_forums($forum)) {
            $tracked = forum_tp_is_tracked($forum);
            $disabled = false;
            $label = $this->stringmanager->get_string('trackforum', 'mod_forum');
        }

        $renderer = $this->rendererhelper->get_renderer('mod_forum');
        $attributes = [
            ['name' => 'type', 'value' => 'forum-track-toggle'],
            ['name' => 'action', 'value' => 'toggle'],
            ['name' => 'forumid', 'value' => $forum->id],
            ['name' => 'targetstate', 'value' => !$tracked],
        ];
        $content = $renderer->render_from_template('core/toggle', [
                    'id' => 'forum-track-toggle-' . $forum->id,
                    'checked' => $tracked,
                    'disabled' => $disabled,
                    'dataattributes' => $attributes,
                    'label' => $label,
                    'labelclasses' => 'visually-hidden',
                ]);

        $renderer->get_page()->requires->js_call_amd(
            'mod_forum/forum_overview_toggle',
            'init',
            ['forum-track-toggle-' . $forum->id],
        );

        return new overviewitem(
            name: get_string('tracking', 'mod_forum'),
            value: $content,
        );
    }

    /**
     * Get the subscribed toggle item.
     *
     * @return overviewitem|null The overview item (or null if the user cannot subscribe).
     */
    private function get_extra_subscribed_overview(): ?overviewitem {
        global $USER;

        $forum = $this->get_forum_instance();
        if (empty($forum)) {
            // User cannot view this forum.
            return null;
        }

        $disabled = false;
        $subscribed = false;
        $label = null;
        if (\mod_forum\subscriptions::is_forcesubscribed($forum)) {
            $disabled = true;
            $subscribed = true;
            $label = $this->stringmanager->get_string('subscribed', 'mod_forum');
        } else if (\mod_forum\subscriptions::subscription_disabled($forum) &&
                !has_capability('mod/forum:managesubscriptions', $this->context)) {
            $disabled = true;
            $label = $this->stringmanager->get_string('unsubscribed', 'mod_forum');
        } else if (!is_enrolled($this->context, $USER, '', true)) {
            $disabled = true;
            $label = $this->stringmanager->get_string('unsubscribed', 'mod_forum');
        } else {
            $subscribed = \mod_forum\subscriptions::is_subscribed($USER->id, $forum);
            if ($subscribed) {
                $label = $this->stringmanager->get_string('unsubscribe', 'mod_forum');
            } else {
                $label = $this->stringmanager->get_string('subscribe', 'mod_forum');
            }
        }

        $renderer = $this->rendererhelper->get_renderer('mod_forum');
        $attributes = [
            ['name' => 'type', 'value' => 'forum-subscription-toggle'],
            ['name' => 'action', 'value' => 'toggle'],
            ['name' => 'forumid', 'value' => $forum->id],
            ['name' => 'targetstate', 'value' => !$subscribed],
        ];
        $content = $renderer->render_from_template('core/toggle', [
                    'id' => 'forum-subscription-toggle-' . $forum->id,
                    'checked' => $subscribed,
                    'disabled' => $disabled,
                    'dataattributes' => $attributes,
                    'label' => $label,
                    'labelclasses' => 'visually-hidden',
                ]);

        $renderer->get_page()->requires->js_call_amd(
            'mod_forum/forum_overview_toggle',
            'init',
            ['forum-subscription-toggle-' . $forum->id],
        );

        return new overviewitem(
            name: get_string('subscribed', 'mod_forum'),
            value: $content,
        );
    }

    /**
     * Get the email digest selector item.
     *
     * @return overviewitem|null The overview item (or null if the user cannot subscribe).
     */
    private function get_extra_emaildigest_overview(): ?overviewitem {
        global $USER;

        $forum = $this->get_forum_instance();
        if (empty($forum)) {
            // User cannot view this forum.
            return null;
        }

        $value = '-';
        $cansubscribe = \mod_forum\subscriptions::is_subscribable($forum);
        $canmanage = has_capability('mod/forum:managesubscriptions', $this->context);
        $issubscribed = \mod_forum\subscriptions::is_subscribed($USER->id, $forum, null, $this->cm);
        if ($cansubscribe || $canmanage || $issubscribed) {
            if ($forum->maildigest === false) {
                $forum->maildigest = -1;
            }

            $renderer = $this->rendererhelper->get_renderer('mod_forum');
            $value = $renderer->render($renderer->render_digest_options($forum, $forum->maildigest));
        }

        return new overviewitem(
            name: get_string('digesttype', 'mod_forum'),
            value: $value,
            textalign: text_align::CENTER,
        );
    }

    /**
     * Get the discussions item.
     *
     * @return overviewitem|null The overview item (or null if there).
     */
    private function get_extra_discussions_overview(): ?overviewitem {

        $forum = $this->get_forum_instance();
        if (empty($forum)) {
            // User cannot view this forum.
            return null;
        }

        $totaldiscussions = forum_count_discussions($forum, $this->cm, $this->course);

        return new overviewitem(
            name: get_string('discussions', 'mod_forum'),
            value: $totaldiscussions,
            textalign: text_align::CENTER,
        );
    }

    private function get_forum_instance(): ?\stdClass {
        global $DB, $USER;

        if (isset($this->forum)) {
            return $this->forum;
        }

        // TODO: Should this check stay here?
        if (!has_capability('mod/forum:viewdiscussion', $this->context)) {
            // User can't view this forum.
            return null;
        }
        $this->forum = $this->cm->get_instance_record();

        $this->forum->maildigest = $DB->get_field(
            'forum_digests',
            'maildigest',
            ['userid' => $USER->id, 'forum' => $this->forum->id],
        );

        return $this->forum;
    }

    private function is_teacher(): bool {
        return has_capability('mod/forum:rate', $this->context);
    }
}
