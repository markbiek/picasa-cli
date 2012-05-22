## Picasa Upload Utility ##
This is a simple PHP cli script that allows the creation of Picasa web albums and uploading of files.

Author: Mark Biek (info@biek.org, http://mark.biek.org)

### Configuration ###
If you specify the username/password from the command-line, no configuration is necessary.

Otherwise, you can create the file `.picasa.conf' in your home directory and store the username and password there:

    username=myusername@gmail.com
    password=mypassword

*Note: If you're using 2-factor authentication with Google, you'll need to create an application-specific password for this script.*

### Basic Usage ###

Run `picasa --help` for a full list of options.

* Creating an album

    picasa --create-album=ALBUM-NAME

* Uploading images to an existing album (wildcards can be used for image names)

    picasa --upload-image=FILE(s) --upload-album=ALBUM-NAME

* Uploading images to a new album

    picasa --upload-image=FILE(s) --create-album=ALBUM-NAME


There is an **experimental** "sync" feature that takes a path, creates albums for each folder in the path, and uploads all images found to the appropriate album. However it's not a true sync and running it multiple times will create duplicate folders.
