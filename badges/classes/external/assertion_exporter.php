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

/**
 * Contains class for displaying a assertion.
 *
 * @package   core_badges
 * @copyright 2019 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_badges\external;

defined('MOODLE_INTERNAL') || die();

use core\external\exporter;
use renderer_base;
use stdClass;

/**
 * Class for displaying a badge competency.
 *
 * @package   core_badges
 * @copyright 2019 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assertion_exporter extends exporter {

    /**
     * Either map version 1 data to version 2 or return it untouched.
     *
     * @param stdClass $data The remote data.
     * @param string $apiversion The backpack version used to communicate remotely.
     * @return stdClass
     */
    public static function map_external_data($data, $apiversion) {
        if ($apiversion == OPEN_BADGES_V1) {
            $result = new \stdClass();
            die('who knows?');
            return $result;
        }
        $mapped = new \stdClass();
        if (isset($data->entityType)) {
            $mapped->type = $data->entityType;
        } else {
            $mapped->type = $data->type;
        }
        if (isset($data->entityId)) {
            $mapped->id = $data->entityId;
        } else {
            $mapped->id = $data->id;
        }
        if (isset($data->issuedOn)) {
            $mapped->issuedOn = $data->issuedOn;
        }
        if (isset($data->recipient)) {
            $mapped->recipient = $data->recipient;
        }
        if (isset($data->badgeclass)) {
            $mapped->badgeclass = $data->badgeclass;
        }
        $propname = '@context';
        $mapped->$propname = 'https://w3id.org/openbadges/v2';
        return $mapped;
    }

    protected static function define_other_properties() {
        return array(
            'badge' => array(
                'type' => badgeclass_exporter::read_properties_definition(),
                'optional' => true
            ),
            'recipient' => array(
                'type' => recipient_exporter::read_properties_definition(),
                'optional' => true
            )
        );
    }

    /**
     * We map from related data passed as data to this exporter to clean exportable values.
     */
    protected function get_other_values(renderer_base $output) {
        global $DB;
        $result = [];
    
        if (array_key_exists('related_badge', $this->data)) {
            $exporter = new badgeclass_exporter($this->data['related_badge'], $this->related);
            $result['badge'] = $exporter->export($output);
        }
        if (array_key_exists('related_recipient', $this->data)) {
            $exporter = new recipient_exporter($this->data['related_recipient'], $this->related);
            $result['recipient'] = $exporter->export($output);
        }
        return $result;
    }

    /**
     Here is an example of the data we get fed to map.
    
array(7) {
  ["recipient"]=>
  array(3) {
    ["identity"]=>
    string(23) "damyon+badgr@moodle.com"
    ["type"]=>
    string(5) "email"
    ["hashed"]=>
    bool(true)
  }
  ["verify"]=>
  array(2) {
    ["type"]=>
    string(6) "hosted"
    ["url"]=>
    string(117) "http://damyon-desktop.per.in.moodle.com/stable_master/badges/assertion.php?b=7c7418bb62b9f700740b15dd57c9ace84b63dd2e"
  }
  ["issuedOn"]=>
  string(25) "2019-03-01T14:57:51+08:00"
  ["@context"]=>
  string(30) "https://w3id.org/openbadges/v2"
  ["type"]=>
  string(9) "Assertion"
  ["id"]=>
  string(129) "http://damyon-desktop.per.in.moodle.com/stable_master/badges/assertion.php?b=7c7418bb62b9f700740b15dd57c9ace84b63dd2e&obversion=2"
  ["badge"]=>
  array(10) {
    ["name"]=>
    string(7) "Fireman"
    ["description"]=>
    string(7) "Flames."
    ["image"]=>
    string(31306) "data:image/png;base64,iVBORw0KGgo...(long stuff)"
    ["criteria"]=>
    array(2) {
      ["id"]=>
      string(116) "http://damyon-desktop.per.in.moodle.com/stable_master/badges/badge.php?hash=7c7418bb62b9f700740b15dd57c9ace84b63dd2e"
      ["narrative"]=>
      string(151) "Users are awarded this badge when they complete the following requirement:
 * This badge has to be awarded by a user with the following role:
Teacher

"
    }
    ["issuer"]=>
    array(6) {
      ["name"]=>
      string(24) "Stable Master PostgreSQL"
      ["url"]=>
      string(39) "http://damyon-desktop.per.in.moodle.com"
      ["email"]=>
      string(28) "damyon+badgr-site@moodle.com"
      ["@context"]=>
      string(30) "https://w3id.org/openbadges/v2"
      ["id"]=>
      string(138) "http://damyon-desktop.per.in.moodle.com/stable_master/badges/assertion.php?b=7c7418bb62b9f700740b15dd57c9ace84b63dd2e&action=0&obversion=2"
      ["type"]=>
      string(6) "Issuer"
    }
    ["@context"]=>
    string(30) "https://w3id.org/openbadges/v2"
    ["id"]=>
    string(138) "http://damyon-desktop.per.in.moodle.com/stable_master/badges/assertion.php?b=7c7418bb62b9f700740b15dd57c9ace84b63dd2e&action=1&obversion=2"
    ["type"]=>
    string(10) "BadgeClass"
    ["version"]=>
    string(0) ""
    ["@language"]=>
    string(2) "en"
  }
}
     */

    /**
     * Return the list of properties.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'type' => [
                'type' => PARAM_ALPHA,
                'description' => 'Issuer',
            ],
            'id' => [
                'type' => PARAM_URL,
                'description' => 'Unique identifier for this assertion',
            ],
            'badgeclass' => [
                'type' => PARAM_RAW,
                'description' => 'Identifier of the badge for this assertion',
                'optional' => true,
            ],
            'issuedOn' => [
                'type' => PARAM_RAW,
                'description' => 'Date this badge was issued',
            ],
            '@context' => [
                'type' => PARAM_URL,
                'description' => 'Badge version',
            ],
        ];
    }

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related() {
        return array(
            'context' => 'context'
        );
    }
}
