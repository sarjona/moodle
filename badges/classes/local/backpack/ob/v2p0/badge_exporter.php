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

namespace core_badges\local\backpack\ob\v2p0;

use moodle_url;
use core_badges\badge;
use core_badges\local\backpack\ob\badge_exporter_interface;
use core_badges\local\backpack\ob\exporter_base;

/**
 * Class that represents badgeclass to be exported to a backpack.
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badge_exporter extends exporter_base implements badge_exporter_interface {
    /** @var badge BadgeClass */
    protected $_badge;

    /**
     * Constructs with badge identifier.
     *
     * @param int $id Badge identifier.
     */
    public function __construct(
        string $id,
    ) {
        $this->_badge = new badge($id);
    }

    /**
     * Get badgeclass.
     *
     * @param bool $issued Include the nested badge issued information.
     * @return array Badge assertion.
     */
    public function export(
        // TODO: Can the $issued parameter be removed?
        bool $issued = true,
    ): array {
        $data = [];

        $badgeid = $this->_badge->id;
        $obversion = $this->get_version_from_namespace();
        $context = $this->_badge->get_context();
        $classurl = new moodle_url(
            '/badges/json/badge.php',
            [
                'id' => $badgeid,
                'obversion' => $obversion,
            ],
        );

        // Required.
        $data = [
            '@context' => OPEN_BADGES_V2_CONTEXT,
            'id' => $classurl->out(false),
            'type' => OPEN_BADGES_V2_TYPE_BADGE,
            'name' => $this->_badge->name,
            'description'=> $this->_badge->description,
            // 'criteria' => $this->get_criteria_badge_class(),
            // 'issuer' => $this->get_issuer(),
        ];

        if (!empty($this->_badge->version)) {
            $data['version'] = $this->_badge->version;
        }
        if (!empty($this->_badge->language)) {
            $data['@language'] = $this->_badge->language;
        }

        // Image.
        $urlimage = moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badgeid, '/', 'f3')->out(false);
        if ($this->_badge->imageauthorname || $this->_badge->imageauthoremail || $this->_badge->imageauthorurl ||
                $this->_badge->imagecaption) {
            $data['image']['id'] = $urlimage;
            if ($this->_badge->imageauthorname || $this->_badge->imageauthoremail || $this->_badge->imageauthorurl) {
                $authorimage = new moodle_url('/badges/image_author_json.php', ['id' => $badgeid]);
                $data['image']['author'] = $authorimage->out(false);
            }
            if ($this->_badge->imagecaption) {
                $data['image']['caption'] = $this->_badge->imagecaption;
            }
        } else {
            $data['image'] = $urlimage;
        }

        // Criteria.
        $data['criteria'] = $this->export_criteria();

        // Issuer.
        $issuer = self::create_issuer_exporter_from_id($badgeid, $obversion);
        $data['issuer'] = $issuer->export();


        // Tags.
        $tags = $this->_badge->get_badge_tags();
        if (is_array($tags) && count($tags) > 0) {
            $data['tags'] = $tags;
        }

        // Related badges.
        $relateds = $this->export_related_badges();
        if (!empty($relateds)) {
            $data['related'] = $relateds;
        }

        // Alignments.
        $alignments = $this->export_alignments();
        if (!empty($alignments)) {
            $data['alignment'] = $alignments;
        }

        return $data;
    }

    public function export_related_badges(): array {
        $data = [];
        $relatedbadges = $this->_badge->get_related_badges(true);
        if (!empty($relatedbadges)) {
            foreach ($relatedbadges as $related) {
                $relatedurl = new moodle_url('/badges/json/badge.php', ['id' => $related->id]);
                $data[] = [
                    'id' => $relatedurl->out(false),
                    'version' => $related->version,
                    '@language' => $related->language,
                ];
            }
        }
        return $data;
    }

    public function export_criteria(): array|string {
        $params = ['id' => $this->_badge->id];
        $badgeurl = new moodle_url('/badges/badgeclass.php', $params);
        $narrative = $this->_badge->markdown_badge_criteria();
        if (!empty($narrative)) {
            return [
                'id' => $badgeurl->out(false),
                'narrative' => $narrative,
            ];
        } else {
            return $badgeurl->out(false);
        }
    }

    public function export_alignments(): array {
        $alignments = $this->_badge->get_alignments();
        if (empty($alignments)) {
            return [];
        }

        $data = [];
        foreach ($alignments as $item) {
            $alignment = [
                'targetName' => $item->targetname,
                'targetUrl' => $item->targeturl,
            ];
            if ($item->targetdescription) {
                $alignment['targetDescription'] = $item->targetdescription;
            }
            if ($item->targetframework) {
                $alignment['targetFramework'] = $item->targetframework;
            }
            if ($item->targetcode) {
                $alignment['targetCode'] = $item->targetcode;
            }
            $data[] = $alignment;
        }
        return $data;
    }
}
