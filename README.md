# REDCap_dde_2_projects
This plugin allows users to use 2 separate projects for doing double data entry and 
allows them to compare the data from the second entry project to the data in the first entry project.
Using this plugin allows the users to remove some fields and/or instruments from the second project
for instruments or fields for which they do not need to do double data entry, such as survey instruments
or free form text fields which often have small differences when entered, such as spacing and punctuation.

It displays differences in data between 2 REDCap projects where the second project is
being used for partial DDE for the first project, so it only compares data for
records/events/forms that have data in the second project.

Information about which projects are set up to use this plugin are stored in the REDCap project
titled "Lowry, Sue - REDCap 2 project DDE projects" (PID 2816). This project has fields for 
name of requester, email address, the first entry REDCap project and the second entry REDCap project.

Sample settings for the project bookmark in the second entry project:
Link Label: DDE Comparison
Link URL / Destination: /plugins/dde_2_projects_from_index.php
Link Type: Simple Link
Opens new window: checked
Append record info to URL: unchecked
Append project ID to URL: checked
