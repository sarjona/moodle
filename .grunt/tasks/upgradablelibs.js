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
/* jshint node: true, browser: false */
/* eslint-env node */

/**
 * @copyright  2023 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

module.exports = grunt => {

    /**
     * Generate upgradable third-party libraries (utilising thirdpartylibs.xml data)
     */
    // const handler = async () => {
    //     var done = this.async();
    //     const path = require('path');
    //     const ComponentList = require(path.join(process.cwd(), '.grunt', 'components.js'));

    //     // An array of paths to third party directories.
    //     const thirdPartyLibs = await ComponentList.getThirdPartyLibsUpgradable();
    //     console.log(thirdPartyLibs);

    //     // const getLatestTag = async(url) => {
    //     //   console.log('getLatestTag');
    //     //   const gtr = require('git-tags-remote');
    //     //   console.log(url);
    //     //   const tag = await gtr.latest(url);
    //     //   console.log('sara');
    //     // };

    //     // console.log('before');
    //     // const tag = await getLatestTag('git@github.com:sh0ji/git-tags-remote.git');
    //     // console.log('after');
    //     // console.log(tag);
    //     done();
    // };

    // grunt.registerTask('upgradablelibs', 'Generate upgradable third-party libraries', handler);

    grunt.registerTask('upgradablelibs', 'Generate upgradable third-party libraries', async function () {
      const done = this.async();

      const path = require('path');
      const ComponentList = require(path.join(process.cwd(), '.grunt', 'components.js'));

      // An array of paths to third party directories.
      const thirdPartyLibs = await ComponentList.getThirdPartyLibsUpgradable();
      console.log(thirdPartyLibs);

      done();
    });

    // return handler;
};
