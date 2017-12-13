# autocopy
Mac command line utility to copy multiple volumes (SD cards, SSDs etc.) to multiple destinations (Hard drive, backup drive).

# Features
Autocopy sends commands to Finder (via AppleScript), so you monitor the progress in Finder (as if you clicked and dragged yourself).

Autocopy copies entire contents. It alternates destination drives (i.e. 1->1, 1->2; 2->2, 2->1; 3->1, 3->2; 4->2, 4->1 etc.). 

Unlike Finder, Autocopy verifies that all destination drives have enough free space before starting to copy.

# Usage
Open Terminal on a Mac.

Insert all sources (SD cards, SSD) and destinations (external hard drives)

Type "autocopy", and follow directions to copy multiples SD cards to multiple hard drives.


# Installation
Autocopy is written in PHP using the PHP-CLI - so you have to have the shell executable and the .php file somewhere in Terminal's PATH.
1. Open Finder, then type Shift + Command + G, enter "~" to go to your home folder.

2. Create a folder called "bin", if it doesn't exist
```sh
mkdir ~/bin
```
3. Go to "~/bin"

4. Put all of the files into this bin folder at ~/bin/Autocopy

5. Make autocopy executable
```sh
chmod 755 ~/bin/Autocopy/autocopy
```

6. Add Autocopy/ folder to Terminal's path
```sh
pico ~/.bash_profile
```
```sh
# add these lines
# this is to include Autocopy in the PATH
PATH=$PATH:~/bin/Autocopy
export PATH
```
> Control + X to save changes (in pico)

7. Future terminal windows will include the path.
```sh
logout
```
