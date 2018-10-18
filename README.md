# test-bank-to-blackboard
Converts Thames &amp; Hudson Test Banks to the Blackboard Import Format

## Description
Converts Thames &amp; Hudson (and possibly W.W. Norton &amp; Company) test banks into a format importable by Blackboard Learn.  Currently, this will allow the importing of Multiple Choice, Essay, and True/False questions.  Please let me know if you need another question type added and I will do my best.

## Prerequisites
You must have PHP 5+ installed and some sort of bash shell (linux, bash for windows, cygwin, mingw etc...) in order to use this. If there is enough interest perhaps I will port this to another format, but for now it was just written to help my wife convert all her tests for her Art History classes.

## Installation &amp; Use
Put the php file in the top level instructor resource folder, and then simply run the command:

bash create_import_files.sh

It will automatically go through all PDF files in its directory and recursively through all subdirectories and convert them from PDF to TXT.  It will then reformat the text files following the Blackboard Learn formatting guidelines and save them in the output folder.
