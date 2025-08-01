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

namespace core_courseformat\external;

/**
 * Tests for overviewaction_exporter.
 *
 * @package    core_courseformat
 * @category   test
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_courseformat\external\overviewaction_exporter
 */
final class overviewaction_exporter_test extends \advanced_testcase {
    /**
     * Test the export returns the right structure when the content is a string.
     *
     * @dataProvider provider_test_export
     * @covers ::export
     * @param string|null $badgevalue The value of the badge.
     * @param string|null $badgetitle The title of the badge.
     * @param \core\output\local\properties\badge|null $badgestyle The style of the badge.
     */
    public function test_export_string(
        ?string $badgevalue = null,
        ?string $badgetitle = null,
        ?\core\output\local\properties\badge $badgestyle = null,
    ): void {
        $renderer = \core\di::get(\core\output\renderer_helper::class)->get_core_renderer();

        $url = new \core\url('/some/url');
        $text = 'My information';
        $icon = new \core\output\pix_icon('i/warning', 'sample');
        $icondata = $icon->get_exporter()->export($renderer);
        $attributes = ['class' => 'me-0 pb-1'];
        $overviewaction = new \core_courseformat\output\local\overview\overviewaction(
            url: $url,
            text: $text,
            icon: $icon,
            attributes: $attributes,
            badgevalue: $badgevalue,
            badgetitle: $badgetitle,
            badgestyle: $badgestyle,
        );

        $exporter = new overviewaction_exporter($overviewaction, ['context' => \context_system::instance()]);
        $data = $exporter->export($renderer);

        $this->assertObjectHasProperty('linkurl', $data);
        $this->assertObjectHasProperty('content', $data);
        $this->assertObjectHasProperty('icondata', $data);
        $this->assertObjectHasProperty('classes', $data);
        $this->assertObjectHasProperty('contenttype', $data);
        $this->assertObjectHasProperty('contentjson', $data);
        $this->assertObjectHasProperty('badge', $data);
        $this->assertObjectHasProperty('onlytext', $data);
        $this->assertCount(8, get_object_vars($data));

        $this->assertEquals($url->out(false), $data->linkurl);
        $this->assertStringContainsString($text, $data->content);
        if ($badgevalue !== null && $badgetitle !== null) {
            $this->assertStringContainsString($badgevalue, $data->content);
        }
        $this->assertEquals($icondata, $data->icondata);
        $this->assertEquals($attributes['class'], $data->classes);
        $this->assertEquals('string', $data->contenttype);
        $this->assertNull($data->contentjson);
        $this->assertEquals($text, $data->onlytext);
        if ($badgevalue !== null) {
            $this->assertEquals($badgevalue, $data->badge['value']);
            $this->assertEquals($badgetitle, $data->badge['title']);
            if ($badgestyle == null) {
                // If no style is provided, overviewaction defaults to PRIMARY.
                $badgestyle = \core\output\local\properties\badge::PRIMARY;
            }
            $this->assertEquals($badgestyle, $data->badge['style']);
        } else {
            $this->assertNull($data->badge);
        }
    }

    /**
     * Test the export returns the right structure when the content is a renderable.
     *
     * @dataProvider provider_test_export
     * @covers ::export
     * @param string|null $badgevalue The value of the badge.
     * @param string|null $badgetitle The title of the badge.
     * @param \core\output\local\properties\badge|null $badgestyle The style of the badge.
     */
    public function test_export_renderable(
        ?string $badgevalue = null,
        ?string $badgetitle = null,
        ?\core\output\local\properties\badge $badgestyle = null,
    ): void {
        $renderer = \core\di::get(\core\output\renderer_helper::class)->get_core_renderer();

        $url = new \core\url('/some/url');
        // We use help_icon as text to simulate a renderable content.
        $text = new \core\output\help_icon('search', 'core');
        $icon = new \core\output\pix_icon('i/warning', 'sample');
        $icondata = $icon->get_exporter()->export($renderer);
        $attributes = ['class' => 'me-0 pb-1'];

        $overviewaction = new \core_courseformat\output\local\overview\overviewaction(
            url: $url,
            text: $text,
            icon: $icon,
            attributes: $attributes,
            badgevalue: $badgevalue,
            badgetitle: $badgetitle,
            badgestyle: $badgestyle,
        );

        $exporter = new overviewaction_exporter(
            $overviewaction,
            ['context' => \context_system::instance()],
        );
        $data = $exporter->export($renderer);

        $this->assertObjectHasProperty('linkurl', $data);
        $this->assertObjectHasProperty('content', $data);
        $this->assertObjectHasProperty('icondata', $data);
        $this->assertObjectHasProperty('classes', $data);
        $this->assertObjectHasProperty('contenttype', $data);
        $this->assertObjectHasProperty('contentjson', $data);
        $this->assertObjectHasProperty('badge', $data);
        $this->assertObjectHasProperty('onlytext', $data);
        $this->assertCount(8, get_object_vars($data));

        $this->assertEquals($url->out(false), $data->linkurl);
        $this->assertStringContainsString($renderer->render($text), $data->content);
        if ($badgevalue !== null && $badgetitle !== null) {
            $this->assertStringContainsString($badgevalue, $data->content);
        }
        $this->assertEquals($icondata, $data->icondata);
        $this->assertEquals($attributes['class'], $data->classes);
        // Since help_icon is not externable, we expect the contenttype to be 'string', contentjson to be null and onlytext empty.
        $this->assertEquals('string', $data->contenttype);
        $this->assertNull($data->contentjson);
        $this->assertEquals('', trim($data->onlytext));
        if ($badgevalue !== null) {
            $this->assertEquals($badgevalue, $data->badge['value']);
            $this->assertEquals($badgetitle, $data->badge['title']);
            if ($badgestyle == null) {
                // If no style is provided, overviewaction defaults to PRIMARY.
                $badgestyle = \core\output\local\properties\badge::PRIMARY;
            }
            $this->assertEquals($badgestyle, $data->badge['style']);
        } else {
            $this->assertNull($data->badge);
        }
    }

    /**
     * Provider for test_export.
     *
     * @return array
     */
    public static function provider_test_export(): array {
        return [
            'All badge fields' => [
                'badgevalue' => '5',
                'badgetitle' => 'New items',
                'badgestyle' => \core\output\local\properties\badge::SUCCESS,
            ],
            'No badge' => [
            ],
            'Badge without value (equivalent to no badge)' => [
                'badgetitle' => 'New items',
                'badgestyle' => \core\output\local\properties\badge::SUCCESS,
            ],
            'Badge without title' => [
                'badgevalue' => '5',
                'badgestyle' => \core\output\local\properties\badge::SUCCESS,
            ],
            'Badge without style (defaults to PRIMARY)' => [
                'badgevalue' => '5',
                'badgetitle' => 'New items',
            ],
        ];
    }
}
