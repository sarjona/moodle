Description of Typo3 libraries (v 4.7.19) import into Moodle

Changes:
1/ hacked relative include of class.t3lib_utility_debug.php

Procedure:
1/ Download latest version form http://typo3.org/download/
2/ Copy csconvtbl/*, unidata/* from typo3/sysext/core/Resources/Private/Charsets into the 'data' folder with
    the same file tree starting from 'Resources'
3/ Copy the following from typo3 into src keeping the same file tree starting after Classes:
    * Charset/CharsetConverter.php
    * Charset/UnknownCharsetException.php
    * Exception.php
    * SingletonInterface.php
4/ Copy the following files from the current version of Moodle. These have been customised to fit Moodle.
    * Core/Environment.php
    * Utility/ExtensionManagementUtility.php
    * Utility/GeneralyUtility.php
    * autoload.php
4/ Remove all the functions in the Utility and Core classes that are NOT used in CharsetConverter.php.
5/ Remove all use/define/include statements for files/globals not included in the list above
6/ Run the full suite of phpunit tests (NOT just text_test.php)

Local changes (to verify/apply with new imports):

- MDL-67316: PHP 7.4 compatibility. Wrong chars in hexdec() operations.
    Ensure that all the calls to hexdec() are passing exclusively
    correct hex chars. Before php74 they were silently discarded but
    with php74 a deprecation warning is produced. We haven't looked how
    this is fixed upstream because plans include to remove this
    library from core (see MDL-65809)

- MDL-67017: PHP 7.4 compatibility. Curly brackets.
    Remove all the deprecated curly bracket uses {} to access to strings/arrays
    by key. We haven't looked how this is fixed upstream because plans include
    to remove this library from core (see MDL-65809)

- MDL-63967: PHP 7.3 compatibility.
    lib/typo3/class.t3lib_div.php: FILTER_FLAG_SCHEME_REQUIRED is deprecated and
    implied with FILTER_VALIDATE_URL. This is fixed upstream since Typo 6, with
    the file class now under \TYPO3\CMS\Core\Utility\GeneralUtility.

skodak, stronk7, moodler
