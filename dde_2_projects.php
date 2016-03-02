<?php
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
if ($pid2 == 0) {
    exit("Project # " . $_GET['pid'] . " has not been set up for this plugin");
}

#print "pid1: $pid1, pid2: $pid2</br>";

// OPTIONAL: Your custom PHP code goes here. You may use any constants/variables listed in redcap_info().

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

$skip_recs = 0;
if (isset($_GET['skip_recs']) ) {
  $skip_recs = $_GET['skip_recs'];
}
$first_rec_num = $skip_recs + 1;
$rec_limit = 100;
if (isset($_GET['rec_limit']) ) {
  $rec_limit = $_GET['rec_limit'];
}
// OPTIONAL: Display the project header
if ($action_button != 'Export Data') { require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php'; }

// Your HTML page content goes here
if ($action_button != 'Export Data') { 
?>
        <style type="text/css">
                table {border-collapse:collapse;}
                table.param {
                  border-right:none;
                  border-left:none;
                  border-right:1pt solid white;
                  border-bottom:1pt solid white;
                  font-size:17px;
                  font-size:13px;
                  font-family:helvetica,arial,sans-serif;
                }
                .param th,
                .param td {
                  border-top: none;
                  border-left: none;
                  border-top: 1pt solid white;
                  border-left: 1pt solid white;
                  padding: 3px 4px;
                  font-weight:normal;
                }
        </style>
<?php
}

print "<h1>Comparison of $rec_limit records starting with record # $first_rec_num:</h1>";
print '<table class="form_border">
<tr>
<td class="header" style="font-size:8pt;text-align:center;"> Label <i>(field name)</i> </td>
<td class="header" style="font-size:8pt;text-align:center;">Event</td>
<td class="header" style="font-size:8pt;text-align:center;">Form Name</td>
<td class="header" style="font-size:8pt;text-align:center;">ID</td>
<td class="header" style="font-size:8pt;text-align:center;">Main</td>
<td class="header" style="font-size:8pt;text-align:center;">DDE</td>
<td class="header" style="font-size:8pt;text-align:center;">Rec #</td>
</tr>
';

$sql = sprintf( "
        SELECT distinct d.record
          FROM redcap_data d 
          LEFT JOIN redcap_user_rights u
            ON u.project_id = d.project_id
         WHERE d.project_id = %d
           AND (u.username = '%s' or '%s' = 'sklowry')
           AND (u.group_id is null or
                  exists (select 'x' from redcap_data dag
                           where dag.project_id = d.project_id and dag.record = d.record and dag.field_name = '__GROUPID__' and dag.value = u.group_id))
         ORDER BY d.record limit %d, %d",
                 $pid2, $userid, $userid, $first_rec_num - 1, $rec_limit);
#print "sql: $sql<br/>";

// execute the sql statement
$records_result = $conn->query( $sql );
if ( ! $records_result )  // sql failed
{
      die( "Could not execute SQL: <pre>$sql</pre> <br />" .  mysqli_error($conn) );
}
$rownum = $skip_recs;
while ($rrec = $records_result->fetch_assoc( ))
{
  #print "record: ".$rrec['record']."</br>";
  $rownum++;
  #print '<tr><td colspan=99>'.$rownum.": ".$rrec['record'].'</td></tr>';
  $sql = sprintf( "
          SELECT distinct r.event_id as event_id_2, rem1.event_id as event_id_1, rem2.descrip as event_descrip, 
                 f.form_name, fn.form_menu_description
            FROM redcap_data r, redcap_metadata f, redcap_metadata fn, redcap_events_metadata rem2, 
                 redcap_events_arms rea1, redcap_events_metadata rem1
           WHERE r.record = '%s'
             AND r.project_id = %d
             AND f.project_id = r.project_id
             AND f.field_name = r.field_name
             AND fn.project_id = f.project_id
             AND fn.field_order = (select min(field_order) from redcap_metadata ff where ff.project_id = f.project_id and ff.form_name = f.form_name)
             AND rem2.event_id = r.event_id
             AND rea1.project_id = %d
             AND rem1.arm_id = rea1.arm_id
             AND rem1.descrip = rem2.descrip
           ORDER BY rea1.arm_num, rem2.day_offset, rem2.descrip, fn.field_order",
                   $rrec['record'], $pid2, $pid1);
  #print "sql: $sql<br/><br/>";

  // execute the sql statement
  $events_result = $conn->query( $sql );
  if ( ! $events_result )  // sql failed
  {
        die( "Could not execute SQL: <pre>$sql</pre> <br />" .  mysqli_error($conn) );
  }
  while ($erec = $events_result->fetch_assoc( ))
  {
    $sql = sprintf( "
            select p2.*, p1.value_1 
              from (select p2.form_name, p2.field_order, p2.field_name, p2.element_label, p2.element_type, p2.element_enum, 
                           group_concat(d2.value order by d2.value separator ', ') as value_2
                      from redcap_metadata p1, redcap_metadata p2
                      left join redcap_data d2
                        on d2.project_id = %d
                       and d2.field_name = p2.field_name
                       and d2.record = '%s'
                       and d2.event_id = %d
                     where p1.project_id = %d
                       and p2.project_id = %d
                       and p2.field_name = p1.field_name
                       and p2.form_name = '%s'
                     group by p2.form_name, p2.field_order, p2.field_name, p2.element_label, p2.element_type, p2.element_enum) p2, 
                   (select p2.form_name, p2.field_order, p2.field_name, p2.element_label, p2.element_type, p2.element_enum, 
                           group_concat(d1.value order by d1.value separator ', ') as value_1
                      from redcap_metadata p1, redcap_metadata p2
                      left join redcap_data d1
                        on d1.project_id = %d
                       and d1.field_name = p2.field_name
                       and d1.record = '%s'
                       and d1.event_id = %d
                     where p1.project_id = %d
                       and p2.project_id = %d
                       and p2.field_name = p1.field_name
                       and p2.form_name = '%s'
                     group by p2.form_name, p2.field_order, p2.field_name, p2.element_label, p2.element_type, p2.element_enum) p1
             where p1.field_name = p2.field_name
               and (p1.value_1 <> p2.value_2 or (p1.value_1 is null and p2.value_2 is not null) or (p1.value_1 is not null and p2.value_2 is null))
             order by p2.form_name, p2.field_order",
                     $pid2, $rrec['record'], $erec['event_id_2'], $pid1, $pid2, $erec['form_name'], $pid1, $rrec['record'], $erec['event_id_1'], $pid1, $pid2, $erec['form_name']);
    #print "sql: $sql<br/><br/>";

    // execute the sql statement
    $data_results = $conn->query( $sql );
    if ( ! $data_results )  // sql failed
    {
          die( "Could not execute SQL: <pre>$sql</pre> <br />" .  mysqli_error($conn) );
    }
    while ($drec = $data_results->fetch_assoc( ))
    {
      if ($drec['value_1'] > ' ') { $value_1 = $drec['value_1']; } else { $value_1 = '<i>blank</i>'; }
      if ($drec['value_2'] > ' ') { $value_2 = $drec['value_2']; } else { $value_2 = '<i>blank</i>'; }
      #print "<tr><td colspan=99>value_1: ".$drec['value_1'].", value_2: ".$drec['value_2'].", new value1: ".$value_1.", new value2: ".$value_2."</td></tr>";
      print '<tr>
<td class="data" style="padding:2px 5px;">' . $drec['element_label'] . ' (<i>' . $drec['field_name'] . '</i>)</td>
<td class="data" style="padding:2px 5px;">' . $erec['event_descrip'] . '</td>
<td class="data" style="padding:2px 5px;">' . $erec['form_menu_description']. '</td>
<td class="data" style="padding:2px 5px;">' . $rrec['record']. '</td>
<td class="data" valign="top" onclick="window.open('."'".APP_PATH_WEBROOT."DataEntry/index.php?pid=".$pid1.".&page=".$erec['form_name']."&id=".$rrec['record']."&event_id=".$erec['event_id_1']."&fldfocus=".$drec['field_name']."#".$drec['field_name']."-tr','_blank');".'" style="padding:2px 5px;cursor:pointer"><span class="compare" style="color:#800000;">'.$value_1.'</span></td>
<td class="data" valign="top" onclick="window.open('."'".APP_PATH_WEBROOT."DataEntry/index.php?pid=".$pid2.".&page=".$erec['form_name']."&id=".$rrec['record']."&event_id=".$erec['event_id_2']."&fldfocus=".$drec['field_name']."#".$drec['field_name']."-tr','_blank');".'" style="padding:2px 5px;cursor:pointer"><span class="compare" style="color:#800000;">'.$value_2.'</span></td>
<td class="data" style="padding:2px 5px;text-align:right;">' . $rownum. '</td>
</tr>
';
    }
  }
}
print '</table>';
print "<br/>Records ".$first_rec_num. " - ".$rownum."<br />";

print "<a href='".$_SERVER["PHP_SELF"]."?pid=".$pid2."&skip_recs=".$rownum."&rec_limit=".$rec_limit."'>Next set of records</a>";
// OPTIONAL: Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

