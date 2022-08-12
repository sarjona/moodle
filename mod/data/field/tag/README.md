# README #

The database tags field creates tags from comma separated text, similar to the way that Moodle generates tags from the 'Interests' field in the User Profile, but specifically for the database activity. When a record is displayed these are hyperlinked and when clicked will display a filtered search result based on the tag value.

block_databasetags, filter_databasetagcloud, datafield_linkedcheckbox and datafield_tag were created specifically for a resource database which can be seen at http://practicelearning.info/course/view.php?id=10. It was commissioned and funded by Focused on Learning, and coded and maintained by Andrew Hancox. We are certain this will be of use to others so we are contributing this development back in to the Moodle community.

### How do I get set up? ###

Place the files into mod/data/field/tag and run notifications.

Due to limitations in the database activity you will need to run the installhacks script which copies an image and language string into the core mod_data code. You can do this by typing:

php mod/data/field/tag/cli/installhacks.php

at the command line.

One the plugin is installed if you go to MOODLESITEROOT/mod/data/field/tag/migrate/migrate.php you will be able to choose checkbox database fields that you wish to migrate over.
