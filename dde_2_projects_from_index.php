<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/
/* dde_2_projects_from_index.php - copied from ../redcap_v5.5.21/DataComparisonTool/index.php 
   and modified to work with second entry in second project */
/**
 * PLUGIN NAME: dde_2_projects.php
 * DESCRIPTION: This displays differences in data between 2 REDCap projects where the second project is
 *              being used for partial DDE for the first project, so it only compares data for
 *              records/events/forms that have data in the second project.
 * VERSION:     1.0
 * AUTHOR:      Sue Lowry - University of Minnesota
 */

// Call the REDCap Connect file in the main "redcap" directory
require_once "../redcap_connect.php";
require_once APP_PATH_DOCROOT . 'Reports/functions.php';

if (!isset($_GET['pid'])) {
    exit("Project ID is required");
}
$pid2 = $_GET['pid'];
$pid1 = 0;
if ($pid2 == 1979) { $pid1 = 732; } # Copy of MN Ambit Clinical Database for testing / MN Ambit Clinical Database
if ($pid2 == 2205) { $pid1 = 2011; } # CENIC Project 2 - Visit Data - Second Entry / CENIC Project 2 - Visit Data
if ($pid2 == 2204) { $pid1 = 872; } # COMET, Project 4 - Second Entry / COMET, Project 4
if ($pid2 == 2394) { $pid1 = 1204; } # Novel 2 - Second Entry / Novel 2
if ($pid2 == 2690) { $pid1 = 2120; } # Clearway 2014_secondentry / Clearway 2014
if ($pid2 == 0) {
    exit("Project # " . $_GET['pid'] . " has not been set up for this plugin");
}

#print "pid1: $pid1, pid2: $pid2</br>";

if (!SUPER_USER) {
    $sql = sprintf( "
            SELECT p.app_title
              FROM redcap_projects p
              LEFT JOIN redcap_user_rights u
                ON u.project_id = p.project_id
             WHERE p.project_id = %d AND (u.username = '%s' OR p.auth_meth = 'none')",
                     $_REQUEST['pid'], $userid);

    // execute the sql statement
    $result = $conn->query( $sql );
    if ( ! $result )  // sql failed
    {
        die( "Could not execute SQL: <pre>$sql</pre> <br />" .  mysqli_error($conn) );
    }

    if ( mysqli_num_rows($result) == 0 )
    {
        die( "You are not validated for project # $project_id ($app_title)<br />" );
    }
}

$compare_some_label = "Compare limited number of record/events";
$skip_recs = 0;
if (isset($_POST['skip_recs']) ) { $skip_recs = $_POST['skip_recs']; }
$first_rec_num = $skip_recs + 1;
$rec_limit = 100;
if (isset($_POST['rec_limit']) ) { $rec_limit = $_POST['rec_limit']; }
$compare_some = 0;
if (isset($_POST['submit']) and $_POST['submit'] == $compare_some_label) { $compare_some = 1; }
if ($compare_some == 1 and ($skip_recs > 0 or $rec_limit > 0)) {
	$limit_sql = "limit " . $skip_recs . ", $rec_limit";
} else {
	$limit_sql = "";
}




include APP_PATH_DOCROOT  . 'ProjectGeneral/header.php';
include APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

renderPageTitle("<img src='".APP_PATH_IMAGES."page_copy.png'> 2 Project Data Comparison Tool");


// Instructions
print "<p>This page may be used for comparing project records in this second entry project with the same records in the main project. <br/><br/>
Select a record from the list below and hit the 'Compare' button. A comparison table will then be displayed showing the differences between the records. Only records that have been entered in this second entry project will be displayed in the selection box below and in the comparison table.<br/><br/>
Alternatively, you can hit the 'Compare all records' button to compare all records.  If the project has too much data to compare all of the records at one time (e.g. when you try, it takes a long time and then you get an error message), then you can use the '$compare_some_label' button instead. You might need to try different numbers in the fields above that button to get a number that works well for you.";

//If user is in DAG, only show info from that DAG and give note of that
if ($user_rights['group_id'] != "") {
	print  "<p style='color:#800000;'>{$lang['global_02']}: {$lang['data_comp_tool_05']}</p>";
}
			
// Create array of checkbox fields with field_name as key and default value options of "0" as sub-array values
$chkbox_fields = array();
foreach ($Proj->metadata as $field=>$attr) {
	if (!$Proj->isCheckbox($field)) continue;
	foreach (parseEnum($attr['element_enum']) as $this_value=>$this_label) {
		$chkbox_fields[$field][$this_value] = "0";	
	}
}




// Set flag to compare ALL records/events instead of single pair of records/events
$compareAll = (isset($_POST['compare-all']) && $_POST['compare-all']);
if (isset($_GET['rec_limit']) ) {
   $compareAll = (isset($_GET['rec_limit']) );
}






//Decide which pulldowns to display for user to choose Study ID
if ($user_rights['group_id'] == "") {
	$group_sql  = ""; 
} else {
	$group_sql  = "and d2.record in (" . pre_query("select record from redcap_data where project_id = $project_id and field_name = '__GROUPID__' and value = '".$user_rights['group_id']."'") . ")"; 
}
#print "<br/>group_sql: $group_sql<br/>";
$rs_ids_sql = "select d2.record, d2.event_id, m1.event_id as event_id1 from redcap_data d2, redcap_events_metadata m2, redcap_events_arms a2, redcap_events_metadata m1, redcap_events_arms a1
			   where d2.project_id = $project_id and a2.project_id = d2.project_id and a2.arm_id = m2.arm_id and d2.field_name = '$table_pk' $group_sql 
			   and d2.event_id = m2.event_id and d2.event_id = m2.event_id and a1.project_id = $pid1 and m1.arm_id = a1.arm_id and m1.descrip = m2.descrip
                           order by abs(d2.record), d2.record, a2.arm_num, m2.day_offset, m2.descrip $limit_sql";
#print "<br/>rs_ids_sql: $rs_ids_sql<br/>";
$q = db_query($rs_ids_sql);
$record_events_found = 0;
// Collect record names into array
$records  = array();
while ($row = db_fetch_assoc($q)) 
{
	$record_events_found++;
	// Add to array
	$records[$row['record']][$row['event_id']] = $Proj->eventInfo[$row['event_id']]['name_ext'];
	$event2s[$row['record']][$row['event_id']] = $row['event_id1'];
}
// Loop through the record list and store as string for drop-down options
$id_dropdown = "";
foreach ($records as $this_record=>$this_event)
{
	if ($longitudinal) {
		$id_dropdown .= "<option value='{$this_record}[__EVTID__]all_events[__EVTID__]all_events'>" 
				  . $this_record . ($longitudinal ? " *** All events ***" : "") 
				  . "</option>";
	}
	foreach ($this_event as $this_event_id=>$this_event_name)
	{
		$id_dropdown .= "<option value='{$this_record}[__EVTID__]{$this_event_id}[__EVTID__]".$event2s[$this_record][$this_event_id]."'>" 
					  . $this_record . ($longitudinal ? " - $this_event_name" : "") 
					  . "</option>";
	}
}

// Give option to compare all DDE pairs of records on single page
$compareAllBtn = '';
$disableCompAllBtn = (empty($records)) ? "disabled" : "";
$compareAllBtn = RCView::div(array('style'=>'padding:5px 0;font-weight:normal;color:#777;'),
					"&mdash; {$lang['global_46']} &mdash;"
				 ) .
				 RCView::div('',
					RCView::input(array('type'=>'submit','name'=>'submit','value'=>$lang['data_comp_tool_45'],$disableCompAllBtn=>$disableCompAllBtn,'onclick'=>"$('#record1').val($('#record1 option:eq(1)').val()); $('input[name=\"compare-all\"]').val('1');"))
				 );
if ($compare_some == 1) {
	$new_skip = $skip_recs + $rec_limit;
	if (empty($records)) {
		$new_skip = 0;
		$disableCompAllBtn = "";
	}
} else {
	$new_skip = $skip_recs;
}
$compareSomeBtn = RCView::div(array('style'=>'padding:5px 0;font-weight:normal;color:#777;'),
					"&mdash; {$lang['global_46']} &mdash;"
				 ) .
			         "Compare up to <input name='rec_limit' value=$rec_limit size=6 class='x-form-text x-form-field' style-'padding-right:0;height:22px;'> record/events,<br>" .
			         "skipping the first <input name='skip_recs' value=$new_skip size=6 class='x-form-text x-form-field' style-'padding-right:0;height:22px;'> record/events" .
				 RCView::div('',
					RCView::input(array('type'=>'submit','name'=>'submit','value'=>$compare_some_label,$disableCompAllBtn=>$disableCompAllBtn,'onclick'=>"$('#record1').val($('#record1 option:eq(1)').val()); $('input[name=\"compare-some\"]').val('5-10');"))
				 );


// Table to choose record (show ONLY 1 pulldown for true Double Data Entry comparison)
print "<form action=\"".PAGE_FULL."?pid=$project_id\" method=\"post\" enctype=\"multipart/form-data\" name=\"datacomp\" target=\"_self\">";
print "<table class='form_border'>
		<tr>
			<td class='label_header' style='padding:10px;'>
				$table_pk_label
			</td>
			<td class='label_header' style='padding:10px;' rowspan='2'>
				<input name='submit' type='submit' value='".cleanHtml($double_data_entry ? $lang['data_comp_tool_44'] : $lang['data_comp_tool_02'])."' onclick=\"
					if ($('#record1').val().length < 1" . (!$double_data_entry ? " || $('#record2').val().length < 1" : "") . ") {
						simpleDialog('".cleanHtml($lang['data_comp_tool_06'])."');
						return false;
					}
				\">
				$compareAllBtn 
				<input type='hidden' name='compare-all' value='0'>
			</td>
			<td class='label_header' style='padding:10px;' rowspan='2'>
				$compareSomeBtn
			</td>
		</tr>
		<tr>
			<td class='data' align='center' style='padding:15px;'>
				<select name='record1' id='record1' class='x-form-text x-form-field' style='padding-right:0;height:22px;'>
					<option value=''>--- {$lang['data_comp_tool_43']} ---</option>
					$id_dropdown";						
print  "		</select></td>";
print  "</tr>
		</table>";
print  "</form><br><br>";	

// If sumbitted values, use javascript to select the dropdown values
if ($_SERVER['REQUEST_METHOD'] == 'POST') 
{
	if (!$compareAll) {
		// pre-select the drop-down(s), but not if user clicked "compare all" button
		print  "<script type='text/javascript'>
				$(function(){
					$('#record1').val('{$_POST['record1']}');
					$('#record2').val('{$_POST['record2']}');
				});
				</script>";
	}	
}












###############################################################################################
# When records are selected for comparison, display side-by-side comparison in table
if (isset($_POST['submit'])) 
{
	// Reset some CSS in table for consistent viewing when selecting which record value to merge
	$display_string .= "<style type='text/css'>
						.data { padding: 5px; }
						.header { font-size: 7.5pt; }
						</style>";
	
	// PRINT PAGE button
	print  "<div style='text-align:right;max-width:700px;'>
				<button class='jqbuttonmed' onclick='printpage(this)'><img src='".APP_PATH_IMAGES."printer.png' class='imgfix'> Print page</button>
			</div>";
	
	// If only comparing a single pair of records/events
	if (!$compareAll and !$compare_some) {
		list ($record1, $event_id1, $event_id2) = explode("[__EVTID__]", $_POST['record1']);
		if ($event_id1 == 'all_events') {
			$event2s = array($record1=>$event2s[$record1]);
			$records = array($record1=>$records[$record1]);
		} else {
			$records = array($record1=>array($event_id1=>1));
			$event2s = array($record1=>array($event_id1=>$event_id2));
		}
	}
			
	// Retrieve all validation types
	$valTypes = getValTypes();
	
	// Loop counter
	$loopNum = 0;
	
	//print_array($records);
	
	if ($compare_some == 1) 
	{
		print "<h1>Comparison of $record_events_found record/events starting with record/event # $first_rec_num:</h1>";
	}

	// Loop through records
	foreach ($records as $record1=>$evts) 
	{
		// Retrieve the submitted record names and their corresponding event_ids
		$record2 = $record1;
		
		// Loop through events for this record
		#foreach (array_keys($evts) as $event_id1) 
		foreach ($evts as $event_id1=>$event_name1) 
		{
			// Retrieve the submitted record names and their corresponding event_ids
			$event_id2 = $event2s[$record1][$event_id1];
			
			// Retrieve data values for record 1
			$sql = "select record, field_name, value from redcap_data where record = '".prep($record1)."' 
					and project_id = $project_id and event_id = $event_id1";
			$q = db_query($sql);
			$record1_data = eavDataArray($q, $chkbox_fields);
		
			// Retrieve data values for record 2
			$sql = "select r1.record, r1.field_name, r1.value from redcap_data r1 where r1.record = '".prep($record2)."' 
					and r1.project_id = $pid1 and r1.event_id = $event_id2 
                                        and exists (select 'x' from redcap_metadata r2 where r2.project_id = $project_id and r2.field_name = r1.field_name)";
			$q = db_query($sql);
			$record2_data = eavDataArray($q, $chkbox_fields);

			
			// Retrieve metadata fields that are only relevent here for data comparison (only get fields that we have data for)
			$metadata_fields_rec1rec2 = array_unique(array_merge(array_keys($record1_data[$record1]), array_keys($record2_data[$record2])));
			$metadata_fields = array();
			foreach ($Proj->metadata as $this_field=>$row) {
				if (!in_array($this_field, $metadata_fields_rec1rec2)) continue;
				$metadata_fields[$this_field] = $row;
			}
			
			// Initialize string to gather HTML for entire table display
			$display_string = "<hr size=1>";
			
			// Display comparison table instructions
			$display_string .= "<p style='color:#000066;'>
									<b>{$lang['data_comp_tool_46']} <span style='color:#800000;font-size:14px;'>$record1</span> " .
									($longitudinal ? " {$lang['global_108']} <span style='color:#800000;'>".$Proj->eventInfo[$event_id1]['name_ext']."</span>" : "") . 
									"{$lang['period']}</b><br><br>
									{$lang['data_comp_tool_16']} <b>$record1</b>".$lang['period']." 
									{$lang['data_comp_tool_17']}
								</p>";

			$display_string .= "<div style='max-width:700px;'>
								<form action='".PAGE_FULL."?pid=$project_id&event_id=$event_id1&create_new=1' method='post' enctype='multipart/form-data' name='create_new' target='_self'>
								<table class='form_border'>
								<tr>
									<td class='header' style='font-size:8pt;text-align:center;' rowspan=2>{$lang['data_comp_tool_26']} <i>{$lang['data_comp_tool_27']}</i></td>
									<td class='header' style='font-size:8pt;text-align:center;' rowspan=2>{$lang['global_12']}</td>";
			
				$display_string .= "<td class='header' style='font-size:8pt;text-align:center;' colspan='2'>
										<font color=#800000>$table_pk_label</font> $record1"
										. ($longitudinal ? "<br/><div style='font-size:11px;'>".$Proj->eventInfo[$event_id1]['name_ext']."</div>" : "") . "
									</td>
								</tr>";
			
			
			$display_string .= "<tr>
									<td class='data' valign='bottom' style='text-align:center;color:#000066;'>
										<b>First Entry</b>
									</td>
									<td class='data' style='text-align:center;color:#000066;'>
										<b>Second Entry</b>
									</td>";
			
			$display_string .= "</tr>";
			
			// Initialize string for capturing table row of HTML
			$diff = "";
			
			// print_array($record1_data);
			// print_array($record2_data);
			// print_array($metadata_fields);
			
			//Render rows: Loop through all fields being compared
			foreach ($metadata_fields as $field_name=>$attr) {
			//Begin building table and build headers
				
				// Skip record id field (not applicable)
				if ($field_name == $table_pk) continue;
				
				// Get field attributes
				$element_label = $attr['element_label'];
				$form_name = $attr['form_name'];
				$element_type = $attr['element_type'];
				$select_choices = $attr['element_enum'];
				
				// Create array for possible sub-looping through multiple values for single field
				$subloop = array();
				
				
				// If field has multiple values associated with a single field (e.g., checkbox), then causing multiple looping for that field
				if ($element_type == "checkbox") {
					// Create array to hold labels for this checkbox field
					$checkbox_labels = parseEnum($Proj->metadata[$field_name]['element_enum']);
					// Loop using record1's values (but doesn't matter because ALL checkboxes are added to record1 and record2 arrays by default (to cover default "0" values)
					foreach (array_keys($record1_data[$record1][$field_name]) as $this_code) {
						// Create new field name with triple underscore + coded value
						$this_field = $field_name . "___" . $this_code;
						// Set with multiple values
						$subloop[1][$this_field] = $record1_data[$record1][$field_name][$this_code];
						$subloop[2][$this_field] = $record2_data[$record2][$field_name][$this_code];	
					}
				// If field only has one data point (normal)
				} else {
					// Set with single values
					$subloop[1][$field_name] = $record1_data[$record1][$field_name];
					$subloop[2][$field_name] = $record2_data[$record2][$field_name];	
				}
				
				// Loop through all sub-fields, if a checkbox, else it'll just loop once
				foreach (array_keys($subloop[1]) as $sub_field_name) 
				{
					// Set values for this subloop
					$this_val1 = $subloop[1][$sub_field_name];
					$this_val2 = $subloop[2][$sub_field_name];	
								
					// If field is Text or Notes field type, then remove line breaks and minimize spaces for proper comparison of characters
					if ($element_type == 'text' || $element_type == 'textarea') {
						$this_val1 = remBr($this_val1);
						$this_val2 = remBr($this_val2);	
						// If a date[time][_seconds] field, then check if we need to reformat data before displaying (entered in other formats)
						if ($Proj->metadata[$sub_field_name]['element_type'] == 'text' && substr($Proj->metadata[$sub_field_name]['element_validation_type'], 0, 4) == 'date')
						{
							// Check type
							if (substr($Proj->metadata[$sub_field_name]['element_validation_type'], 0, 8) == 'datetime') {
								list ($thisdate1, $thistime1) = explode(" ", $this_val1);
								list ($thisdate2, $thistime2) = explode(" ", $this_val2);
							} else {
								$thisdate1 = $this_val1;
								$thistime1 = "";
								$thisdate2 = $this_val2;
								$thistime2 = "";
							}				
							if (substr($Proj->metadata[$sub_field_name]['element_validation_type'], -4) == '_dmy') {
								$this_val1 = trim(date_ymd2dmy($thisdate1) . " " . $thistime1);
								$this_val2 = trim(date_ymd2dmy($thisdate2) . " " . $thistime2);
							} elseif (substr($Proj->metadata[$sub_field_name]['element_validation_type'], -4) == '_mdy') {
								$this_val1 = trim(date_ymd2mdy($thisdate1) . " " . $thistime1);
								$this_val2 = trim(date_ymd2mdy($thisdate2) . " " . $thistime2);
							}
						}
					}
					
					//print out values if there is a difference bewteen data for each entered id				
					if ($this_val1 != $this_val2) {
							
						// Remove any illegal characters that can cause javascript to crash
						$this_val1 = $this_val1_orig = htmlspecialchars(html_entity_decode(html_entity_decode($this_val1, ENT_QUOTES), ENT_QUOTES), ENT_QUOTES);
						$this_val2 = $this_val2_orig = htmlspecialchars(html_entity_decode(html_entity_decode($this_val2, ENT_QUOTES), ENT_QUOTES), ENT_QUOTES);
						
						// For checkboxes, convert each choice to advcheckbox enum
						if ($element_type == 'checkbox') {
							$select_choices = $attr['element_enum'] = "0, Unchecked \n 1, Checked";
						}
						
						// Process values for SELECT boxes (and ADVCHECKBOXes) to display the text AND the numerical value
						if ($element_type == 'yesno' || $element_type == 'truefalse' || $element_type == 'select' || $element_type == 'checkbox' || $element_type == 'advcheckbox' || $element_type == 'radio') {
							
							// Parse the enum to store as array to pull the labels later
							$select_text = parseEnum($attr['element_enum']);
							// Set newly formatted values for display
							if ($this_val1 != "") $this_val1 = $select_text[$this_val1] . " <i>($this_val1)</i>";				
							if ($this_val2 != "") $this_val2 = $select_text[$this_val2] . " <i>($this_val2)</i>";	
						}			
						
						// For checkboxes, provide extra label of field_name + triple underscore + coding
						if ($element_type == 'checkbox')
						{
							$disp_field_name = strtolower($field_name . " &raquo; $sub_field_name");
							$disp_choice_code = substr($sub_field_name, strrpos($sub_field_name, "___")+3);
							$disp_element_label = $element_label . " (Choice =  <b>" . $checkbox_labels[$disp_choice_code] . "</b>)";
						}
						else
						{
							$disp_field_name = $field_name;
							$disp_element_label = $element_label;
						}
						
						// Render static row of two values
							
							$diff .=  	"<tr>
											<td class='data' style='padding:2px 5px;'>
												$disp_element_label <i>($disp_field_name)</i>
											</td>
											<td class='data' style='padding:2px 5px;'>
												".$Proj->forms[$form_name]['menu']."
											</td>
											<td valign='top' class='data' style='padding:2px 5px;cursor:pointer' onclick=\"window.open('" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=$pid1&page=$form_name&id=$record2&event_id=$event_id2&fldfocus=$field_name#$field_name-tr','_blank');\">
												<span class=\"compare\" style='color:#800000;'>$this_val2</span>
											</td>
											<td valign='top' class='data' style='padding:2px 5px;cursor:pointer;' onclick=\"window.open('" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=$project_id&page=$form_name&id=$record1&event_id=$event_id1&fldfocus=$field_name#$field_name-tr','_blank');\">
												<span class=\"compare\" style='color:#800000;'>$this_val1</span>
											</td>";
						
						$diff .= "</tr>";
					}
				
				}
				
			}
			
			
			// Display table if there were any differences.
			if ($diff != "") {
			
				$display_string .= $diff;
				
				$display_string .= "</table></form></div><br><br>";
				
				print $display_string;
			
			
			// If no differences, then give message.
			} else {
			    if ( !$record2_data ) {
				print  "<hr size=1><font color=#800000><b>The record named $record1 {$lang['global_108']} $event_name1
						does not exist in the main project.</b></font> ";
			    } else {
				print  "<hr size=1><font color=#800000><b>{$lang['data_comp_tool_34']} $record1 {$lang['global_108']} $event_name1
						{$lang['data_comp_tool_35']}</b></font> ";
			    }
			}
			// Increment counter
			$loopNum++;
		}
	}
}

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
