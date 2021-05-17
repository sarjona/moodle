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

namespace TYPO3\CMS\Core\Utility;

/**
 * Custom Moodle modification: This stripped down class is brought across from Typo3 and modified to suit our needs.
 *
 * Extension Management functions
 *
 * This class is never instantiated, rather the methods inside is called as functions like
 * \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('my_extension');
 */
class ExtensionManagementUtility
{
    /**
     * Custom Moodle modification: All resources are stored within the lib/typo3 folder.
     * Following is the original documentation of Typo3's extPath.
     *
     * Returns the absolute path to the extension with extension key $key.
     *
     * @param string $key Extension key
     * @param string $script $script is appended to the output if set.
     * @throws \BadFunctionCallException
     * @return string
     */
    public static function extPath($key, $script = '')
    {
        return TYPO3_DATA_PATH;
    }
}
