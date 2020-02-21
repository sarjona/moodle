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
 * Contains helper class for the H5P area.
 *
 * @package    core_h5p
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for the H5P area.
 *
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Store an H5P file.
     *
     * @param factory $factory The \core_h5p\factory object
     * @param stored_file $file Moodle file instance
     * @param stdClass $config Button options config
     * @param bool $onlyupdatelibs Whether new libraries can be installed or only the existing ones can be updated
     * @param bool $skipcontent Should the content be skipped (so only the libraries will be saved)?
     *
     * @return int|false|null The H5P identifier or null if there is an error when saving or false if it's not a valid H5P package
     */
    public static function save_h5p(factory $factory, \stored_file $file, \stdClass $config, bool $onlyupdatelibs = false,
            bool $skipcontent = false) {
        // This may take a long time.
        \core_php_time_limit::raise();

        $core = $factory->get_core();
        $core->h5pF->set_file($file);
        $path = $core->fs->getTmpPath();
        $core->h5pF->getUploadedH5pFolderPath($path);
        // Add manually the extension to the file to avoid the validation fails.
        $path .= '.h5p';
        $core->h5pF->getUploadedH5pPath($path);

        // Copy the .h5p file to the temporary folder.
        $file->copy_content_to($path);

        // Check if the h5p file is valid before saving it.
        $h5pvalidator = $factory->get_validator();
        if ($h5pvalidator->isValidPackage($skipcontent, $onlyupdatelibs)) {
            $h5pstorage = $factory->get_storage();

            $content = [
                'pathnamehash' => $file->get_pathnamehash(),
                'contenthash' => $file->get_contenthash(),
            ];
            $options = ['disable' => self::get_display_options($core, $config)];

            $h5pstorage->savePackage($content, null, $skipcontent, $options);

            return $h5pstorage->contentId;
        }
        return false;
    }

    /**
     * Get the H5P DB instance id for a H5P pluginfile URL. If it doesn't exist, it's not created.
     *
     * @param string $url H5P pluginfile URL.
     * @param bool $preventredirect Set to true in scripts that can not redirect (CLI, RSS feeds, etc.), throws exceptions
     *
     * @return array of [file, stdClass|false]:
     *             - file local file for this $url.
     *             - stdClass is an H5P object or false if there isn't any H5P with this URL.
     */
    public static function get_h5p_from_pluginfile_url(string $url, bool $preventredirect = true): array {
        global $DB;

        // Deconstruct the URL and get the pathname associated.
        $pathnamehash = self::get_pluginfile_hash($url, $preventredirect);
        if (!$pathnamehash) {
            return [false, false];
        }

        // Get the file.
        $fs = get_file_storage();
        $file = $fs->get_file_by_hash($pathnamehash);
        if (!$file) {
            return [false, false];
        }

        $h5p = $DB->get_record('h5p', ['pathnamehash' => $pathnamehash]);
        return [$file, $h5p];
    }

    /**
     * Get or create, if it doesn't exist, the H5P DB instance id for a H5P pluginfile URL.
     *
     * @param string $url H5P pluginfile URL.
     * @param stdClass $config Configuration for H5P buttons.
     * @param factory $factory The \core_h5p\factory object
     * @param stdClass $messages The error, exception and info messages, raised while preparing and running an H5P content.
     * @param bool $preventredirect Set to true in scripts that can not redirect (CLI, RSS feeds, etc.), throws exceptions
     *
     * @return array of [file, h5pid]:
     *             - file local file for this $url.
     *             - h5pid is the H5P identifier or false if there isn't any H5P with this URL.
     */
    public static function get_or_create_h5p_from_pluginfile_url(string $url, \stdClass $config, factory $factory,
        \stdClass &$messages, bool $preventredirect = true): array {
        global $DB, $USER;

        $core = $factory->get_core();
        list($file, $h5p) = self::get_h5p_from_pluginfile_url($url);

        if (!$file) {
            $core->h5pF->setErrorMessage(get_string('h5pfilenotfound', 'core_h5p'));
            return [false, false];
        }

        $contenthash = $file->get_contenthash();
        if ($h5p && $h5p->contenthash != $contenthash) {
            // The content exists and it is different from the one deployed previously. The existing one should be removed before
            // deploying the new version.
            api::delete_content($h5p, $factory);
            $h5p = false;
        }

        $context = \context::instance_by_id($file->get_contextid());
        if ($h5p) {
            // The H5P content has been deployed previously.
            $displayoptions = self::get_display_options($core, $config);
            // Check if the user can set the displayoptions.
            if ($displayoptions != $h5p->displayoptions && has_capability('moodle/h5p:setdisplayoptions', $context)) {
                // If the displayoptions has changed and the user has permission to modify it, update this information in the DB.
                $core->h5pF->updateContentFields($h5p->id, ['displayoptions' => $displayoptions]);
            }
            return [$file, $h5p->id];
        } else {
            // The H5P content hasn't been deployed previously.

            // Check if the user uploading the H5P content is "trustable". If the file hasn't been uploaded by a user with this
            // capability, the content won't be deployed and an error message will be displayed.
            if (!self::can_deploy_package($file)) {
                $core->h5pF->setErrorMessage(get_string('nopermissiontodeploy', 'core_h5p'));
                return [$file, false];
            }

            // The H5P content can be only deployed if the author of the .h5p file can update libraries or if all the
            // content-type libraries exist, to avoid users without the h5p:updatelibraries capability upload malicious content.
            $onlyupdatelibs = !self::can_update_library($file);

            // Validate and store the H5P content before displaying it.
            $h5pid = self::save_h5p($factory, $file, $config, $onlyupdatelibs, false);
            if (!$h5pid && $file->get_userid() != $USER->id && has_capability('moodle/h5p:updatelibraries', $context)) {
                // The user has permission to update libraries but the package has been uploaded by a different
                // user without this permission. Check if there is some missing required library error.
                $missingliberror = false;
                $messages = self::get_messages($messages, $factory);
                if (!empty($messages->error)) {
                    foreach ($messages->error as $error) {
                        if ($error->code == 'missing-required-library') {
                            $missingliberror = true;
                            break;
                        }
                    }
                }
                if ($missingliberror) {
                    // The message about the permissions to upload libraries should be removed.
                    $infomsg = "Note that the libraries may exist in the file you uploaded, but you're not allowed to upload " .
                        "new libraries. Contact the site administrator about this.";
                    if (($key = array_search($infomsg, $messages->info)) !== false) {
                        unset($messages->info[$key]);
                    }

                    // No library will be installed and an error will be displayed, because this content is not trustable.
                    $core->h5pF->setInfoMessage(get_string('notrustablefile', 'core_h5p'));
                }
                return [$file, false];

            }
            return [$file, $h5pid];
        }
    }

    /**
     * Get the error messages stored in our H5P framework.
     *
     * @param stdClass $messages The error, exception and info messages, raised while preparing and running an H5P content.
     * @param factory $factory The \core_h5p\factory object
     *
     * @return stdClass with framework error messages.
     */
    public static function get_messages(\stdClass $messages, factory $factory): \stdClass {
        $core = $factory->get_core();

        // Check if there are some errors and store them in $messages.
        if (empty($messages->error)) {
            $messages->error = $core->h5pF->getMessages('error') ?: false;
        } else {
            $messages->error = array_merge($messages->error, $core->h5pF->getMessages('error'));
        }

        if (empty($messages->info)) {
            $messages->info = $core->h5pF->getMessages('info') ?: false;
        } else {
            $messages->info = array_merge($messages->info, $core->h5pF->getMessages('info'));
        }

        return $messages;
    }

    /**
     * Get the pathnamehash from an H5P internal URL.
     *
     * @param  string $url H5P pluginfile URL poiting to an H5P file.
     * @param bool $preventredirect Set to true in scripts that can not redirect (CLI, RSS feeds, etc.), throws exceptions
     *
     * @return string|false pathnamehash for the file in the internal URL.
     */
    protected static function get_pluginfile_hash(string $url, bool $preventredirect = true) {
        global $USER, $CFG;

        // Decode the URL before start processing it.
        $url = new \moodle_url(urldecode($url));

        // Remove params from the URL (such as the 'forcedownload=1'), to avoid errors.
        $url->remove_params(array_keys($url->params()));
        $path = $url->out_as_local_url();

        $parts = explode('/', $path);
        $filename = array_pop($parts);
        // First is an empty row and then the pluginfile.php part. Both can be ignored.
        array_shift($parts);
        array_shift($parts);

        // Get the contextid, component and filearea.
        $contextid = array_shift($parts);
        $component = array_shift($parts);
        $filearea = array_shift($parts);

        // Ignore draft files, because they are considered temporary files, so shouldn't be displayed.
        if ($filearea == 'draft') {
            return false;
        }

        // Get the context.
        try {
            list($context, $course, $cm) = get_context_info_array($contextid);
        } catch (\moodle_exception $e) {
            throw new \moodle_exception('invalidcontextid', 'core_h5p');
        }

        // For CONTEXT_USER, such as the private files, raise an exception if the owner of the file is not the current user.
        if ($context->contextlevel == CONTEXT_USER && $USER->id !== $context->instanceid) {
            throw new \moodle_exception('h5pprivatefile', 'core_h5p');
        }

        // For CONTEXT_COURSECAT No login necessary - unless login forced everywhere.
        if ($context->contextlevel == CONTEXT_COURSECAT) {
            if ($CFG->forcelogin) {
                require_login(null, true, null, false, true);
            }
        }

        // For CONTEXT_BLOCK.
        if ($context->contextlevel == CONTEXT_BLOCK) {
            if ($context->get_course_context(false)) {
                // If block is in course context, then check if user has capability to access course.
                require_course_login($course, true, null, false, true);
            } else if ($CFG->forcelogin) {
                // No login necessary - unless login forced everywhere.
                require_login(null, true, null, false, true);
            } else {
                // Get parent context and see if user have proper permission.
                $parentcontext = $context->get_parent_context();
                if ($parentcontext->contextlevel === CONTEXT_COURSECAT) {
                    // Check if category is visible and user can view this category.
                    if (!core_course_category::get($parentcontext->instanceid, IGNORE_MISSING)) {
                        send_file_not_found();
                    }
                } else if ($parentcontext->contextlevel === CONTEXT_USER && $parentcontext->instanceid != $USER->id) {
                    // The block is in the context of a user, it is only visible to the user who it belongs to.
                    send_file_not_found();
                }
                if ($filearea !== 'content') {
                    send_file_not_found();
                }
            }
        }

        // For CONTEXT_MODULE and CONTEXT_COURSE check if the user is enrolled in the course.
        // And for CONTEXT_MODULE has permissions view this .h5p file.
        if ($context->contextlevel == CONTEXT_MODULE ||
                $context->contextlevel == CONTEXT_COURSE) {
            // Require login to the course first (without login to the module).
            require_course_login($course, true, null, !$preventredirect, $preventredirect);

            // Now check if module is available OR it is restricted but the intro is shown on the course page.
            if ($context->contextlevel == CONTEXT_MODULE) {
                $cminfo = \cm_info::create($cm);
                if (!$cminfo->uservisible) {
                    if (!$cm->showdescription || !$cminfo->is_visible_on_course_page()) {
                        // Module intro is not visible on the course page and module is not available, show access error.
                        require_course_login($course, true, $cminfo, !$preventredirect, $preventredirect);
                    }
                }
            }
        }

        // Some components, such as mod_page or mod_resource, add the revision to the URL to prevent caching problems.
        // So the URL contains this revision number as itemid but a 0 is always stored in the files table.
        // In order to get the proper hash, a callback should be done (looking for those exceptions).
        $pathdata = null;
        if ($context->contextlevel == CONTEXT_MODULE || $context->contextlevel == CONTEXT_BLOCK) {
            $pathdata = component_callback($component, 'get_path_from_pluginfile', [$filearea, $parts], null);
        }
        if (null === $pathdata) {
            // Look for the components and fileareas which have empty itemid defined in xxx_pluginfile.
            $hasnullitemid = false;
            $hasnullitemid = $hasnullitemid || ($component === 'user' && ($filearea === 'private' || $filearea === 'profile'));
            $hasnullitemid = $hasnullitemid || (substr($component, 0, 4) === 'mod_' && $filearea === 'intro');
            $hasnullitemid = $hasnullitemid || ($component === 'course' &&
                    ($filearea === 'summary' || $filearea === 'overviewfiles'));
            $hasnullitemid = $hasnullitemid || ($component === 'coursecat' && $filearea === 'description');
            $hasnullitemid = $hasnullitemid || ($component === 'backup' &&
                    ($filearea === 'course' || $filearea === 'activity' || $filearea === 'automated'));
            if ($hasnullitemid) {
                $itemid = 0;
            } else {
                $itemid = array_shift($parts);
            }

            if (empty($parts)) {
                $filepath = '/';
            } else {
                $filepath = '/' . implode('/', $parts) . '/';
            }
        } else {
            // The itemid and filepath have been returned by the component callback.
            [
                'itemid' => $itemid,
                'filepath' => $filepath,
            ] = $pathdata;
        }

        $fs = get_file_storage();
        $pathnamehash = $fs->get_pathname_hash($contextid, $component, $filearea, $itemid, $filepath, $filename);
        return $pathnamehash;
    }

    /**
     * Get the representation of display options as int.
     *
     * @param core $core The \core_h5p\core object
     * @param stdClass $config Button options config
     *
     * @return int The representation of display options as int
     */
    public static function get_display_options(core $core, \stdClass $config): int {
        $export = isset($config->export) ? $config->export : 0;
        $embed = isset($config->embed) ? $config->embed : 0;
        $copyright = isset($config->copyright) ? $config->copyright : 0;
        $frame = ($export || $embed || $copyright);
        if (!$frame) {
            $frame = isset($config->frame) ? $config->frame : 0;
        }

        $disableoptions = [
            core::DISPLAY_OPTION_FRAME     => $frame,
            core::DISPLAY_OPTION_DOWNLOAD  => $export,
            core::DISPLAY_OPTION_EMBED     => $embed,
            core::DISPLAY_OPTION_COPYRIGHT => $copyright,
        ];

        return $core->getStorableDisplayOptions($disableoptions, 0);
    }

    /**
     * Checks if the author of the .h5p file is "trustable". If the file hasn't been uploaded by a user with the
     * required capability, the content won't be deployed.
     *
     * @param  stored_file $file The .h5p file to be deployed
     * @return bool Returns true if the file can be deployed, false otherwise.
     */
    public static function can_deploy_package(\stored_file $file): bool {
        if (null === $file->get_userid()) {
            // If there is no userid, it is owned by the system.
            return true;
        }

        $context = \context::instance_by_id($file->get_contextid());
        if (has_capability('moodle/h5p:deploy', $context, $file->get_userid())) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the content-type libraries can be upgraded.
     * The H5P content-type libraries can only be upgraded if the author of the .h5p file can manage content-types or if all the
     * content-types exist, to avoid users without the required capability to upload malicious content.
     *
     * @param  stored_file $file The .h5p file to be deployed
     * @return bool Returns true if the content-type libraries can be created/updated, false otherwise.
     */
    public static function can_update_library(\stored_file $file): bool {
        if (null === $file->get_userid()) {
            // If there is no userid, it is owned by the system.
            return true;
        }

        // Check if the owner of the .h5p file has the capability to manage content-types.
        $context = \context::instance_by_id($file->get_contextid());
        if (has_capability('moodle/h5p:updatelibraries', $context, $file->get_userid())) {
            return true;
        }

        return false;
    }

    /**
     * Convenience to take a fixture test file and create a stored_file.
     *
     * @param string $filepath The filepath of the file
     * @param  int   $userid  The author of the file
     * @param  \context $context The context where the file will be created
     * @return stored_file The file created
     */
    public static function create_fake_stored_file_from_path(string $filepath, int $userid = 0,
            \context $context = null): \stored_file {
        if (is_null($context)) {
            $context = \context_system::instance();
        }
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'core_h5p',
            'filearea'  => 'unittest',
            'itemid'    => rand(),
            'filepath'  => '/',
            'filename'  => basename($filepath),
        ];
        if (!is_null($userid)) {
            $filerecord['userid'] = $userid;
        }

        $fs = get_file_storage();
        return $fs->create_file_from_pathname($filerecord, $filepath);
    }

    /**
     * Get information about different H5P tools and their status.
     *
     * @return array Data to render by the template
     */
    public static function get_h5p_tools_info(): array {
        $tools = array();

        // Getting information from available H5P tools one by one because their enabled/disabled options are totally different.
        // Check the atto button status.
        $link = \editor_atto\plugininfo\atto::get_manage_url();
        $status = strpos(get_config('editor_atto', 'toolbar'), 'h5p') > -1;
        $tools[] = self::convert_info_into_array('atto_h5p', $link, $status);

        // Check the Display H5P filter status.
        $link = \core\plugininfo\filter::get_manage_url();
        $status = filter_get_active_state('displayh5p', \context_system::instance()->id);
        $tools[] = self::convert_info_into_array('filter_displayh5p', $link, $status);

        // Check H5P scheduled task.
        $link = '';
        $status = 0;
        $statusaction = '';
        if ($task = \core\task\manager::get_scheduled_task('\core\task\h5p_get_content_types_task')) {
            $status = !$task->get_disabled();
            $link = new \moodle_url(
                '/admin/tool/task/scheduledtasks.php',
                array('action' => 'edit', 'task' => get_class($task))
            );
            if ($status && \tool_task\run_from_cli::is_runnable() && get_config('tool_task', 'enablerunnow')) {
                $statusaction = \html_writer::link(
                    new \moodle_url('/admin/tool/task/schedule_task.php',
                        array('task' => get_class($task))),
                    get_string('runnow', 'tool_task'));
            }
        }
        $tools[] = self::convert_info_into_array('task_h5p', $link, $status, $statusaction);

        return $tools;
    }

    /**
     * Convert information into needed mustache template data array
     * @param string $tool The name of the tool
     * @param \moodle_url $link The URL to management page
     * @param int $status The current status of the tool
     * @param string $statusaction A link to 'Run now' option for the task
     * @return array
     */
    static private function convert_info_into_array(string $tool,
        \moodle_url $link,
        int $status,
        string $statusaction = ''): array {

        $statusclasses = array(
            TEXTFILTER_DISABLED => 'badge badge-danger',
            TEXTFILTER_OFF => 'badge badge-warning',
            0 => 'badge badge-danger',
            TEXTFILTER_ON => 'badge badge-success',
        );

        $statuschoices = array(
            TEXTFILTER_DISABLED => get_string('disabled', 'admin'),
            TEXTFILTER_OFF => get_string('offbutavailable', 'core_filters'),
            0 => get_string('disabled', 'admin'),
            1 => get_string('enabled', 'admin'),
        );

        return [
            'tool' => get_string($tool, 'h5p'),
            'tool_description' => get_string($tool . '_description', 'h5p'),
            'link' => $link,
            'status' => $statuschoices[$status],
            'status_class' => $statusclasses[$status],
            'status_action' => $statusaction,
        ];
    }
}
