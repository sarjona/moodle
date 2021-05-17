<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Core\Core;

/**
 * This class is initialized once in the SystemEnvironmentBuilder, and can then
 * be used throughout the application to access common variables
 * related to path-resolving and OS-/PHP-application specific information.
 *
 * It's main design goal is to remove any access to constants within TYPO3 code and to provide a static,
 * for TYPO3 core and extensions non-changeable information.
 *
 * This class does not contain any HTTP related information, as this is handled in NormalizedParams functionality.
 *
 * All path-related methods do return the realpath to the paths without (!) the trailing slash.
 *
 * This class only defines what is configured through the environment, does not do any checks if paths exist
 * etc. This should be part of the application or the SystemEnvironmentBuilder.
 *
 * In your application, use it like this:
 *
 * Instead of writing "TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_CLI" call "Environment::isCli()"
 */
class Environment
{
    /**
     * The folder where variable data like logs, sessions, locks, and cache files can be stored.
     * When project path = public path, then this folder is usually typo3temp/var/, otherwise it's set to
     * $project_path/var.
     *
     * @return string
     */
    public static function getVarPath(): string
    {
        return TYPO3_TEMP_PATH;
    }
}
