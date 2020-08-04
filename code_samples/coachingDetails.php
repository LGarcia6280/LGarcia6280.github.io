<?php
// This script creates the page seen when an instructor selects a specific coaching occasion from the Coaching Center matrix for a particular student.  It accepts a student id passed from views/faculty/rollcall.php and displays the coaching occasion details
//
// Created by Luis Garcia 20191115
//


// Each coaching occasion is constituted of a specific number of coaching sessions. The parentIDs correspond to those interventions that kick off an instance of a specific coaching occasion. These parentIDs then correspond to the coaching sessions that end with 1 (e.g. 51, 91, 101). They are designated parentIDs because from these interventions other interventions are born. For example a coaching session 51 will lead to a session 52 which in turn leads to a session 53. Thus, the intervention associated with the session 51 is designated as the parent of the other two. These parentIDs also correspond to the number of times a user has triggered a particular coaching occasion.

// In this script, the collection of needIDs that are related to the parent needID are know as Family Members. For example, the needIDs for the aforementioned sessions 52 and 53 are part of the session 51 family. Thus, the session 51 family would consist of the original session 51, plus the session 52 and session 53.

// This family organization then guides the logic in this script. The parentIDs are identified and stored in 3 different arrays: 1) the parentIDs array; 2) the .Family array; 3) the .FamilyHistory array. The parentID array contains only the needIDs for parent sessions. The .Family array contains all the needIDs for a specific family. The .FamilyHistory array contains all of the history associated with each of the family members.

// Assuming that popups are enabled in the user's browser and that the user is using Chapters the way it was designed then for each coaching session there will be at least one entry in the coachingincidentlisting table in the database. This table stores data associated with every opening of the coaching pop up. Each of the entries in this table corresponds to a specific entry in the interventionqueue table.

// Should the user decide to complete the coaching session, the results of the interaction are recorded in the coachingincidentdata table. Each of the entries in this table refer back to an entry in the coachingincidentlisting table which in turn refers back to an entry in the interventionqueue table.

// The first two arrays mentioned above are used primarily to create the .FamilyHistory and to then display the information. The .FamilyHistory array is formatted identically each time and it's meant to facilitate data extraction. The format is as follows:

  // <parent needID>.FamilyHistory (Lvl 0)
    // <parent needID> (Lvl 1)
      // [0] (Lvl 2) The [0] index at the this level always contains the information from the interventionqueue table for the needID from Lvl 1.
      // [1] (Lvl 2) The [1] index is a container for the data from the coachingincidentlisting and coachingincidentdata tables associated with the needID from Lvl 1.
        // [0]...[n] (Lvl 3) These are the sub-arrays inside the Lvl 2 [1] index with all of the data from the coachingincidentlisting and coachingincidentdata tables associated with the needID from Lvl 1.
    // <family member needID> (Lvl 1)
      // [0] (Lvl 2) The [0] index at the this level always contains the information from the interventionqueue table for the needID from Lvl 1.
      // [1] (Lvl 2) The [1] index is a container for the data from the coachingincidentlisting and coachingincidentdata tables associated with the needID from Lvl 1.
        // [0]...[n] (Lvl 3) These are the sub-arrays inside the Lvl 2 [1] index with all of the data from the coachingincidentlisting and coachingincidentdata tables associated with the needID from Lvl 1.

// The pattern repeats for each member of the family. As currently (Apr 2020), the maximum amount of family members in a family is 3. Thus, the .FamilyHistory array will contain a maximum of 3 Lvl 1 entries.

// See below for an actual example:

/*
The 3169 familyHistory contains:
array(3)   (*********Lvl 0***********)
{
  [3169]=> array(2) (********Lvl 1 - This is the Family Member*********)
  {
    [0]=> array(12) (*********Lvl 2***********)
    {
      [0]=> string(4) "3169" ["needID"]=> string(4) "3169" [1]=> string(2) "-1" ["parent_needID"]=> string(2) "-1" [2]=> string(2) "51" ["coaching_session_id"]=> string(2) "51" [3]=> string(10) "2020-02-12" ["date_placed_into_queue"]=> string(10) "2020-02-12" [4]=> string(8) "09:30:20" ["time_placed_into_queue"]=> string(8) "09:30:20" [5]=> string(2) "E1" ["eID"]=> string(2) "E1"
    }
    [1]=> array(2) (*********Lvl 2***********)
    {
      [0]=> array(12) (*********Lvl 3***********)
      {
        ["listing_coaching_incident_id"]=> string(4) "4894" ["needID"]=> string(4) "3169" ["listing_coaching_session_id"]=> string(2) "51" ["listing_date"]=> string(10) "2020-02-12" ["listing_time"]=> string(8) "09:35:17" ["data_coaching_incident_id"]=> NULL ["data_coaching_session_id"]=> NULL ["data_date"]=> NULL ["data_time"]=> NULL ["st_interaction_response_chosen"]=> NULL ["st_challenge_response_chosen"]=> NULL ["session_complete"]=> NULL
      }
      [1]=> array(12) (*********Lvl 3***********)
      {
        ["listing_coaching_incident_id"]=> string(4) "4895" ["needID"]=> string(4) "3169" ["listing_coaching_session_id"]=> string(2) "51" ["listing_date"]=> string(10) "2020-02-12" ["listing_time"]=> string(8) "09:35:40" ["data_coaching_incident_id"]=> string(4) "4895" ["data_coaching_session_id"]=> string(2) "51" ["data_date"]=> string(10) "2020-02-12" ["data_time"]=> string(8) "09:36:03" ["st_interaction_response_chosen"]=> string(61) "Yes, I just wanted to get through this, so I powered through." ["st_challenge_response_chosen"]=> string(0) "" ["session_complete"]=> string(0) ""
      }
    }
  }
*/

require_once('library/Auth.php');
require_once('services/InstructorMessage.php');
require_once('library/Database.php');
require_once('library/Debug.php');
require_once('library/Courses.php');
require_once('classes/Coaching.php');
//require_once('services/intervention.php');

//	Includes    **************************************************************************

Auth::gateway();

//  Data ***************************************************************

$dbc = Database::connect();

$stUserID	= $_REQUEST['i'];
$snumber = $_REQUEST['sn'];
$coaching_occasion_id = $_REQUEST['ca'];

// Log Out Students from Others Profiles

if( $current_user->snumber != $snumber && $current_user->user_group > 1 ) die( 'No Access' );


//	Build Student	******************************************************

$student = new Student;
$student->get_by_id($stUserID);

// Retrieving Occasion Information from Database ***********************

$occasion_demographics = Coaching::getOccasionInfo_by_OccasionID($coaching_occasion_id);


// Build list of interventions listed in the interventionqueue table for the particular coaching occasion

$interventionList = new Chapters\Services\Intervention();

$interventionNeedIDList = $interventionList->find_intervention_by_coaching_occasion_id($stUserID,$coaching_occasion_id);

$needIDLength = count($interventionNeedIDList);

// The following foreach block is then responsible for gathering and organizing all the data associated with each intervention stored in the $interventionNeedIDList array. It creates the ${'needID'.(int)$NeedIDValue['needID'].'FamilyHistory'} multi-dimensional array that contains all the information required to recreate a user's actions associated with each entry in the intervention queue. The multi-dimensional array is organized based on the parentIDs. Each parentID then contains sub-arrays with information on each of the coaching sessions and the user actions associated with those sessions (data from the coachingincidentlisting and coachingincidentdata tables). It is this multi-dimensional array that has most of the information needed to create the coaching occasion history view.

foreach ($interventionNeedIDList as $NeedIDValue)
{ // begin $interventionNeedIDList foreach

  //flag used to direct execution flow
  $directSibling = TRUE;

  //The entries from the interventionqueue table that have a parent_needID =-1 correspond to the parent interventions
  if ((int)$NeedIDValue['parent_needID'] === -1)
  { // begin $NeedIDValue['parent_needID'] === -1 if

    // The ${'needID'.$NeedIDValue['needID']."Family"} array contains the needIDs that form part of the same cluster
    ${'needID'.$NeedIDValue['needID']."Family"}[] = (int)$NeedIDValue['needID'];

    // The $parentsIDs array contains the needIDs of entries from interventionqueue table that are deemed parent entries
    $parentIDs [] = (int)$NeedIDValue['needID'];

    //The needID is passed as a parameter to the getInterventionHistory function. In this particular case the needID = parentID
    $needID = (int)$NeedIDValue['needID'];

    // Adding the needID of the parent entry into the FamilyHistory array. This is the multi-dimensional array that will store most of the information needed for the view.
    ${'needID'.(int)$NeedIDValue['needID'].'FamilyHistory'}[$needID][] = $NeedIDValue;

    //Retrieving the history data associated with this particular needID
    $historyData = $interventionList->getInterventionHistory($stUserID, $needID,$coaching_occasion_id,$needID);

    //Storing the data just retrieved from the coachingincidentlisting and coachingincidentdata tables
    ${'needID'.(int)$NeedIDValue['needID'].'FamilyHistory'}[$needID][] = $historyData;

    unset($historyData);

  } // end $NeedIDValue['parent_needID'] === -1 if
  else
  { // begin $NeedIDValue['parent_needID'] === -1 else

    //If the flow reaches this point the needID being evaluated as part of the foreach is not a parentID. The goal now is to determine if it's an older or younger sibling. An older sibling corresponds to the entry for the second coaching occasion in the cluster. The younger sibling would be the third coaching session. Since no coaching occasion has four sessions each cluster will have a parent, older sibling and possibly a younger sibling (several coaching occasions only have two coaching sessions)

    $index = count($parentIDs);

    while ($index)
    { // begin $index while
      if ($index > 0)
      { // begin $index > 0 if
        $parentID = $parentIDs[$index-1];
      } // end $index > 0 if
      else
      { // begin $index > 0 else
        $parentID = $parentIDs[$index];
      } // end $index > 0 else

      // The determination between a younger and older sibling is done by checking if the parent_ID value of the current NeedIDValue is the same as the current parentID. If they are the same, the current NeedIDValue is an older sibling.

      if ((int)$NeedIDValue['parent_needID'] === $parentID)
      { // begin $NeedIDValue['parent_needID'] === $parentID if

        //Storing the need id in the Family array
        ${'needID'.$NeedIDValue['parent_needID']."Family"}[] = (int)$NeedIDValue['needID'];

        //Storing the needID in the olderSiblingIDs array. This array will be used to determine the younder siblings.
        $olderSiblingIDs [] = (int)$NeedIDValue['needID'];

        $needID = (int)$NeedIDValue['needID'];

        //Storing the needID in the FamilyHistory array
        ${'needID'.$parentID.'FamilyHistory'}[$needID][] = $NeedIDValue;

        //Retrieving the history data associated with this particular needID
        $historyData = $interventionList->getInterventionHistory($stUserID, $needID,$coaching_occasion_id,$parentID);

        //Storing the just retrieved data from coachingincidentlisting and coachingincidentdata tables in the FamilyHistory array
        ${'needID'.$parentID.'FamilyHistory'}[$needID][] = $historyData;

        unset($historyData);

        //Flag directing code flow
        $directSibling = TRUE;
        break;
      } // end $NeedIDValue['parent_needID'] === $parentID if
      else
      { // begin $NeedIDValue['parent_needID'] === $parentID else

        //If the code reaches the point the current NeedIDValue isn't an older sibling. The $directSibling variable is set to false and the flow enters the section meant to determine if the NeedIDValue is a younger sibling

        $directSibling = FALSE;
      } // end $NeedIDValue['parent_needID'] === $parentID else
      $index -= 1;
    } // end $index while
  } // end $NeedIDValue['parent_needID'] === -1 else

  if ($directSibling === FALSE)
  { // begin $directSibling === FALSE if

    // Since the current NeedIDValue isn't an older sibling the code compares the current NeedIDValue's parent_needID value against the values stored in the $olderSiblingsIDs array. The reason for this is that a younger sibling will have an older sibling's needID as its parent_needID in the interventionqueue table. By comparing the two values the relationship can be established and the NeedIDValue information can be stored in the correct array

    foreach($olderSiblingIDs as $olderSiblingID)
    { // begin $olderSiblingIDs as $olderSiblingID foreach

      // Determining if the NeedIDValue is a younger sibling

      if ((int)$NeedIDValue['parent_needID'] === $olderSiblingID)
      { // begin $NeedIDValue['parent_needID'] === $olderSiblingID if

        //Now that it has been determined that the NeedIDValue is a younger sibling, it needs to be placed in the correct family array. The code loops through each of the values in the parentIDs array to access the correct Family array

        foreach($parentIDs as $parentID)
        { // begin $parentIDs as $parentID foreach

          $familyName = ${'needID'.$parentID.'Family'};

          foreach ($familyName as $familyMember)
          { // begin $familyName as $familyMember foreach

            // It now determines if the needIDValue['parent_needID'] is inside the current Family array.

            if ($familyMember === (int)($NeedIDValue['parent_needID']))
            { // begin $familyMember === (int)($NeedIDValue['parent_needID']) if

              //Storing the NeedIDValue's need id in the Family array
              ${'needID'.$parentID.'Family'}[] = (int)$NeedIDValue['needID'];

              $needID = (int)$NeedIDValue['needID'];

              //Storing all of the NeedIDValue data in the FamilyHistory array

              ${'needID'.$parentID.'FamilyHistory'}[$needID][] = $NeedIDValue;

              //Retrieving the history data associated with this particular needID
              $historyData = $interventionList->getInterventionHistory($stUserID, $needID,$coaching_occasion_id,$parentID);

              // Storing the just retrieved data from the coachingincidentlisting and coachingincidentdata tables in the familyHistory array.

              ${'needID'.$parentID.'FamilyHistory'}[$needID][] = $historyData;

              unset($historyData);

            } // end $familyMember === (int)($NeedIDValue['parent_needID']) if
          } // end $familyName as $familyMember foreach
        } // end $parentIDs as $parentID foreach
      } // end $NeedIDValue['parent_needID'] === $olderSiblingID if
    } // end $olderSiblingIDs as $olderSiblingID foreach
  } // end $directSibling === FALSE if
} // end $interventionNeedIDList foreach

// counting the number of entries in the parentIDs array if one exists
if ($parentIDs)
{
  $parentIDsLength = count($parentIDs);
}

//clearing some memory space
unset($NeedIDValue,$directSibling,$olderSiblingsIDs,$olderSiblingID,$parentID,$familyName,$familyMember,$index);

// Determining the length of the FamilyHistory arrays
foreach($parentIDs as $parentID)
{

  if (${'needID'.$parentID.'FamilyHistory'})
  {
    ${'series'.$parentID.'HistoryLength'} = count(${'needID'.$parentID.'FamilyHistory'});
  }
}

unset($parentID);

if (!empty($interventionNeedIDList))
{
  $numInterventionsFound = count($interventionNeedIDList);
}

?>
<!-- ******************* VIEW *********************-->
<html>

<head>
  <link rel="stylesheet" type="text/css" href="css/coachingDetails.css">
  <meta charset='utf-8'>
</head>

<body>
<!--This is the header of the modal-->
  <div id = "modalBod" class = "topOfModal">
    <div class = "coaching-history-top">
      <div class = "Warning-container">
        <img class = "Warning-sign" src = "/images/book_stack.png" alt = "A book stack" style = "max-width:100%; height:auto;">
      </div>
      <h3 class = "history-header">
        <p style = 'font-size:1.3em;'>&nbsp;<strong><?=$occasion_demographics['coaching_occasion_description']?> History&nbsp;</p>
      </h3>
      <div class = "container-fluid">
        <div class = "panel panel-default studentInfoPanel">
          <div class = "row">
            <div class = "col-xs-5 col-xs-offset-1">
              <div class = "panel-body" style="text-align:center;">
                <span>Student:</span><span style="color:brown;"> <?= $student->last_name ?>, <?= $student->first_name?></span>
                <br>
                <span>Student User Name:</span><span style="color:brown;"> <?= $student->snumber?></span>
                <br>
                <span>CRN:</span><span style="color:brown;"> <?=$student->crn?></strong></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!--End of header code -->
    <!--Creating the navigation buttons toolbar-->
    <div id="pageNavigationContainer" class="container-fluid">
      <div class = "row row-page-nav">
        <div class = "col-xs-12" style="margin:2px;">
          <div class = "panel panel-nav">
            <div class = "btn-toolbar" role = "toolbar">
              <div class = "btn-group" role = "group">
                <button type="button" class = "btn btn-default notAtStart" onclick="returnToTop('tops')">Top</button>
              </div>
              <div class = 'btn-group' role = 'group'>
                <?php

                  // The following block of code is meant to determine the color of the cluster button. A red color for incomplete clusters and a green button for complete

                  for ($z=0; $z<$parentIDsLength; $z++)
                  { // begin $z for
                    $clusterNum = $z+1;

                    $historyLength = ${'series'.$parentIDs[$z].'HistoryLength'};

                    $completeCheck = 0; // this flag is used to determine if each of the family members sessions have been completed. If the final value is equal to the number of entries in the historyLength variable the button will be set to green

                    for ($b=0;$b<$historyLength;$b++)
                    { // begin $b for

                      $familyMember = ${'needID'.$parentIDs[$z].'Family'}[$b];

                      if (${'needID'.$parentIDs[$z].'FamilyHistory'}[$familyMember][0]['completed']==='Y')
                      {
                        $completeCheck = $completeCheck + 1;
                      }

                    } // end $b for

                    if ($completeCheck===$historyLength)
                    { // begin $completeCheck===$historyLength if

                      // taking advantage of heredoc formatting
                      echo <<<EOD
                      <button type='button' class='btn clusters Cluster$clusterNum' style='background-color:#29a319;' onclick="returnToTop('Cluster$clusterNum')">Cluster #$clusterNum</button>
EOD;
                    } // end $completeCheck===$historyLength if
                    else
                    { // begin $completeCheck===$historyLength else

                      // taking advantage of heredoc formatting
                      echo <<<EOD
                        <button type='button' class='btn clusters Cluster$clusterNum' style='background-color:#c21b2c;' onclick="returnToTop('Cluster$clusterNum')">Cluster #$clusterNum</button>
EOD;
                    } // end $completeCheck===$historyLength else
                  } // end $z for

                  unset ($z,$b, $historyLength,$completeCheck,$familyMember,$familyMemberLength);

                ?>
              </div>
              <div class = "btn-group" role = "group">
                <button type="button" class = "btn btn-default notAtStart" onclick="vaporize('mass-coll')">Collapse All</button>
              </div>
              <div class = "btn-group" role = "group">
                <button id = "helpButton" type="button" class = "btn btn-default notAtStart" onclick="helpSideBar('helpBox')">Help</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div> <!--End of the navigation button toolbar section -->
  </div> <!--End of code formatting the top of the modal-->

  <!--Beginning of the section of the page containing the Cluster Information-->
  <div id="historyData" class="container content">
    <div class = "tops">
      <span id = "tops"></span>
    </div>
    <?php

      // The following code extracts the information from the FamilyHistory array and displays it in a series of tables. Each cluster can have up to three SessionID sections. Each SessionID can have multiple sub-tables labeled Mentoring Jar Events that contain the data originally extracted from the coachingincidentlisting and coachingincidentdata tables. These sub-tables also have information extracted from the CoachPrompt and CoachMessage tables to present enough information for an instructor/administrator to recreate a student's actions.

      if ($parentIDs)
      { // begin parentIDs if

        // The first level of this block of nested fors sets the value for the $parentID
        for ($j=0;$j < $parentIDsLength; $j++)
        { //begin parentIDsLength for
          $seriesCount = $j + 1;

          $historyLength = ${'series'.$parentIDs[$j].'HistoryLength'};

          echo "<div id = 'Cluster".$seriesCount."' class = 'row'>"; // Beginning of Coaching History Table Section
            echo "<div class = 'col-xs-12'>";
              echo "<div class = 'panel panel-default'>";
                echo "<div class = 'panel-heading'>";
                  echo "<h3 style = 'text-align:left'><strong>Coaching Session Cluster #$seriesCount</strong></h3>";
                echo "</div>";
                echo "<div class='panel-body'>";
                  echo "<table class='table table-striped table-responsive table-condensed' style='table-layout:fixed; width:100%'>";
                    echo "<tbody>";

                    //The second level of this block of nested fors sets Family array value
                    for ($u=0;$u< $historyLength;$u++)
                    { // begin $u=0 for

                      $familyMember = ${'needID'.$parentIDs[$j].'Family'}[$u];

                      $familyMemberLength = count(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember]);

                      //The third level of this block of nested fors sets the actual family member value

                      for($l=0;$l<$familyMemberLength;$l++)
                      { // begin $l for
                        $g = 0;
                        $t = 0;

                        // The $l value is used to determine if the entry corresponds to a session triggering event in which case a special heading is created in the table. If not a triggering event, a different table format is followed. This format applies to the Mentoring Jar Event information blocks.

                        if ($l === 0)
                        { // begin $l === 0 if

                          // The unique id generated is used to create unique ids for each coaching session's button. This id is then used in the class definition for each of the rows containing the each jar visits's details associated with the specific coaching_session_id

                          $button_id = uniqid();

                          echo "<tr id = 'section-$button_id' style = 'font-size:1.1vw; background-color:#5b8abe; color:white;'>";
                          echo "<th style='width:20%;'>Session ID</th>";
                          echo "<th style='text-align:center; width: 16%;'>Event Description</th>";
                          echo "<th style='text-align:center; width: 16%;'>Event Date</th>";
                          echo "<th style='text-align:center; width: 16%;'>Event Time</th>";
                          echo "<th style='text-align:center; width: 16%;'>Triggering Enclave</th>";
                          echo "<th style='text-align:center; width: 16%;'>Status</th>";
                          echo "</tr>";
                          echo "<tr style = 'font-size:1vw;'>";
                          $coaching_session_id = ${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l]['coaching_session_id'];
                          echo "<td>&nbsp;".${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l]['coaching_session_id']."</td>";
                          echo "<td style='text-align:center;'>Triggering Event</td>";
                          echo "<td style='text-align:center;'>".${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l]['date_placed_into_queue']."</td>";
                          echo "<td style='text-align:center;'>".${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l]['time_placed_into_queue']."</td>";
                          echo "<td style='text-align:center;'>".${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l]['eID']."</td>";

                          // Checking if the coaching session was completed or aborted. A completed session gets a different final entry on the Mentoring Jar Event section of the table
                          if (${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l]['completed']==='Y')
                          {
                            // This is the last entry for sessions that have been completed
                            echo "<td class = 'success' style='text-align:center;'>Complete</td>";
                            echo "</tr>";
                            echo "<tr style='font-size:1vw;'>";
                            //echo "<td style='padding:10px;' colspan='2'>&nbsp;</td>";
                            echo "<td style='padding:10px; text-align:center;' colspan='6'>";

                            //This is the button definition to see the jar visit details associated with a coaching session

                            echo "<button type = 'button' id='#details-$button_id mass-coll' class='btn active coll-btn' onclick='vaporize(this.id)'>Press Here To Toggle Details</button>";
                            echo "</td>";
                            echo "<td style='padding:10px;' colspan='2'>&nbsp;</td>";
                            echo "</tr>";
                          }
                          else
                          {
                            // This is the last entry for sessions that have been aborted
                            echo "<td class = 'danger' style='text-align:center;'>Incomplete</td>";
                          }

                        } // end $l === 0 if
                        else
                        { // begin $l === 0 else

                          // If the code reaches this point then the entry doesn't correspond to an triggering event and instead it's a history detail entry

                          $listingEventsLength = count (${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l]);

                          for ($k=0;$k<$listingEventsLength;$k++)
                          { // begin $k for

                            // This is the code that formats and populates the Mentoring Jar Event section of the table

                            $g += 1; // used for the table header identifier

                            echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1.1vw; background-color:#be815b; color:white; display:none;'>";
                            echo "<th colspan='6'>Mentoring Jar Event #$g</th>";
                            echo "</tr>";
                            echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                            echo "<th style='border-right:1px solid #ddd;'>Event Description: </th>";
                            echo "<td colspan='5'>&nbsp;Mentoring Jar Opened</td>";
                            echo "</tr>";
                            echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                            echo "<th style='border-right:1px solid #ddd;'>Event Date: </th>";
                            echo "<td colspan='5'>&nbsp;".${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k]['listing_date']."</td>";
                            echo "</tr>";
                            echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                            echo "<th style='border-right:1px solid #ddd;'>Event Time: </th>";
                            echo "<td colspan='5'>&nbsp;".${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k]['listing_time']."</td>";
                            echo "</tr>";

                            if (${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k]['data_coaching_incident_id'] == NULL )
                            { // begin ['data_coaching_incident_id'] == NULL ) if

                              //This section formats the Mentoring Jar Event section when the user aborts a coaching session

                              echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                              echo "<th style='border-right:1px solid #ddd;'>Event Status:</th>";
                              echo "<td class='danger' colspan='5'>&nbsp;Mentoring Jar Closed -- Session Aborted</td>";
                              echo "</tr>";
                              echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                              echo "<td colspan='6'>&nbsp;</td>";
                              echo "</tr>";
                            } // end ['data_coaching_incident_id'] == NULL ) if
                            else
                            { // begin ['data_coaching_incident_id'] == NULL ) else

                              //This section formats the Mentoring Jar Event section when the student partially completes or finishes a coaching session.

                              $t += 1;
                              echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1.1vw; background-color:#be815b; color:white; display:none;'>";
                              echo "<th colspan='6'>Mentoring Jar Event #$g-$t</th>";
                              echo "</tr>";
                              echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                              echo "<th style='width:20%; border-right:1px solid #ddd;'>Event Description: </th>";
                              echo "<td colspan='5'>&nbsp;Coaching Interaction Started</td>";
                              echo "</tr>";
                              echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                              echo "<th style='border-right:1px solid #ddd;'>Event Date: </th>";
                              echo "<td colspan='5'>&nbsp;".${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k]['data_date']."</td>";
                              echo "</tr>";
                              echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                              echo "<th style='border-right:1px solid #ddd;'>Event Time: </th>";
                              echo "<td colspan='5'>&nbsp;".${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k]['data_time']."</td>";
                              echo "</tr>";
                              //extracting the text of Diogenes's Prompt
                              $coachingAccess = new Coaching;
                              $diogenesPromptText = $coachingAccess->getCoachPromptText($coaching_session_id);
                              echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                              echo "<th style='border-right:1px solid #ddd;'>Diogenes's Prompt: </th>";
                              echo "<td colspan='5'>&nbsp;".nl2br($diogenesPromptText['coach_prompt_text'])."</td>";
                              echo "</tr>";
                              echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                              echo "<th style='border-right:1px solid #ddd;'>Student Response to Diogenes's Prompt:</th>";
                              echo "<td colspan='5'>&nbsp;".${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k]['st_interaction_response_chosen']."</td>";
                              echo "</tr>";
                              if (!empty(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k]['st_interaction_response_chosen']))
                              { // begin !empty ['st_interaction_response_chosen'])) if

                                //This section adds the information regarding a Coach Message and the student's response (if one exists)

                                // Getting Diogenes's Coaching Message
                                $interaction_choice = ${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k]['st_interaction_response_chosen'];
                                $challenge_choice="";
                                $params = array("coaching_session_id"=>"$coaching_session_id","interaction_choice"=>"$interaction_choice","challenge_choice"=>"$challenge_choice");
                                $whatTrigger = $coachingAccess->checkTrigger($params);
                                $trigger_type = $whatTrigger[0];
                                $coachMessage = $coachingAccess->getCoachMessage($coaching_session_id,$trigger_type);

                              } // end !empty ['st_interaction_response_chosen'])) if
                              echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                              echo "<th style='border-right:1px solid #ddd;'>Diogenes's Coaching Message: </th>";

                              if (!empty($coachMessage))
                              {

                                echo "<td colspan='5'>&nbsp;".nl2br($coachMessage[0])."</td>";
                                echo "</tr>";
                                echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                echo "<th style='border-right:1px solid #ddd;'>Student Response to Diogenes's Message:</th>";

                                // This set of nested if blocks determines if the session was completed and appends the correct final entry to the sub-table

                                // The first thing it does is determine if the current array element is the last one and whether it belongs to the same family.
                                if (($k != $listingEventsLength-1) && (${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k]['data_coaching_incident_id'] == ${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['data_coaching_incident_id']))
                                { // begin [$familyMember][$l][$k]['data_coaching_incident_id'] == [$familyMember][$l][$k+1]['data_coaching_incident_id'] if

                                  // ************* Not the last entry in the array and from the same coachingincidentlisting/coachingincidentdata family ***********************

                                  //  Checking if there's a student interaction associated with the Coach Message. If there is an interaction it will be printed. Otherwise, it's necessary to determine if the session was complete at this stage.
                                  if (!empty(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['st_challenge_response_chosen']))
                                  { // begin !empty(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['st_challenge_response_chosen']) if

                                    // ************* A user response to the Coach Message exists ***********************

                                    // printing the student interaction associated with the Coach Message
                                    echo "<td colspan='5'>&nbsp;".${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['st_challenge_response_chosen']."</td>";
                                    echo "</tr>";

                                    $inFamily = ${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k]['data_coaching_incident_id'] == ${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['data_coaching_incident_id'];

                                    // Checking if the session is complete
                                    if ((${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+2]['session_complete']==='Y') && $inFamily)
                                    { // begin !empty(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['session_complete']) if

                                      // ************* The session was completed ***********************
                                      echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                      echo "<th style='border-right:1px solid #ddd;'>Event Status:</th>";
                                      echo "<td class='success' colspan='5'>&nbsp;Session Complete</td>";
                                      echo "</tr>";
                                      $k = $k + 2; // necessary for the session complete entry not to show up as an additional row
                                    } // end !empty(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['session_complete']) if
                                    else
                                    { // begin !empty(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['session_complete']) else

                                      // ************* The session wasn't completed ***********************
                                      echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                      echo "<th style='border-right:1px solid #ddd;'>Event Status:</th>";
                                      echo "<td class='danger' colspan='5'>&nbsp;Session Incomplete</td>";
                                      echo "</tr>";
                                      echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                      echo "<td colspan='6'>&nbsp;</td>";
                                      echo "</tr>";
                                    } // end !empty(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['session_complete']) else

                                    unset($inFamily);

                                  } // end !empty(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['st_challenge_response_chosen']) if
                                  else
                                  { // begin !empty(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['st_challenge_response_chosen']) else

                                    // ************* A user response to the Coach Message doesn't exists ***********************

                                    //php seems to have an issue with if statements with too many characters. Thus, without adding another if statement, I check if the array element 2 indexes ahead is still in the family. The result of this evaluation is then included inside the if statement to maintain the original condition

                                    $inFamily = ${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k]['data_coaching_incident_id'] == ${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['data_coaching_incident_id'];

                                    if ((${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['session_complete']==='Y') && $inFamily)
                                    { // begin !empty(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['session_complete']) if

                                    // ******* Session Complete = Y and the array member is still part of the same coachingincidentlisting/coachingincidentdata family *************

                                    // Printing that no student interaction associated with the Coach Message was required
                                    echo "<td colspan='5'>&nbsp;None required</td>";
                                    echo "</tr>";

                                    echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                    echo "<th style='border-right:1px solid #ddd;'>Event Status:</th>";
                                    echo "<td class='success' colspan='5'>&nbsp;Session Complete</td>";
                                    echo "</tr>";
                                    $k = $k + 1; // necessary for the session complete entry not to show up as an additional row
                                    } // end !empty(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['session_complete']) if
                                    else
                                    { // begin !empty(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['session_complete']) else

                                    // ******* Session Complete != Y OR the array member isn't part of the same coachingincidentlisting/coachingincidentdata family *************

                                    // Printing that no student interaction associated with the Coach Message was available
                                    echo "<td colspan='5'>&nbsp;Student didn't Answer</td>";
                                    echo "</tr>";

                                    echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                    echo "<th style='border-right:1px solid #ddd;'>Event Status:</th>";
                                    echo "<td class='danger' colspan='5'>&nbsp;Session Incomplete</td>";
                                    echo "</tr>";
                                    echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                    echo "<td colspan='6'>&nbsp;</td>";
                                    echo "</tr>";
                                    } // end !empty(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['session_complete']) else

                                    unset($inFamily);

                                  } // end !empty(${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['st_challenge_response_chosen']) else

                                }// end [$familyMember][$l][$k]['data_coaching_incident_id'] == [$familyMember][$l][$k+1]['data_coaching_incident_id'] if
                                else
                                { // begin [$familyMember][$l][$k]['data_coaching_incident_id'] == [$familyMember][$l][$k+1]['data_coaching_incident_id'] else

                                  // ************* The last entry in the array OR not from the same coachingincidentlisting/coachingincidentdata family ***********************
                                  echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                  echo "<th style='border-right:1px solid #ddd;'>Event Status:</th>";
                                  echo "<td class='danger' colspan='5'>&nbsp;Session Incomplete</td>";
                                  echo "</tr>";
                                  echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                  echo "<td colspan='6'>&nbsp;</td>";
                                  echo "</tr>";

                                } // end [$familyMember][$l][$k]['data_coaching_incident_id'] == [$familyMember][$l][$k+1]['data_coaching_incident_id'] else
                              } // end !empty($coachMessage) if
                              else
                              { // begin !empty($coachMessage) else

                                // ****************** No Coach Message present ***********************
                                echo "<td colspan='5'>&nbsp;No coach message for this session</td>";
                                echo "</tr>";
                                echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                echo "<th style='border-right:1px solid #ddd;'>Student Response to Diogenes's Message:</th>";
                                echo "<td colspan='5'>&nbsp;No student response required</td>";
                                echo "</tr>";

                                // This set of nested if blocks determines if the session was completed and appends the correct final entry to the sub-table
                                if (($k != $listingEventsLength-1) &&  (${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k]['data_coaching_incident_id'] == ${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['data_coaching_incident_id']))
                                { // begin [$familyMember][$l][$k]['data_coaching_incident_id'] == [$familyMember][$l][$k+1]['data_coaching_incident_id'] if

                                  // ************* Not the last entry in the array and from the same coachingincidentlisting/coachingincidentdata family ***********************

                                  if (${'needID'.$parentIDs[$j].'FamilyHistory'}[$familyMember][$l][$k+1]['session_complete'] === 'Y')
                                  { // begin ['session_complete'] === 'Y' if

                                    //****************** The session is complete ********************
                                    echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                    echo "<th style='border-right:1px solid #ddd;'>Event Status:</th>";
                                    echo "<td class='success' colspan='5'>&nbsp;Session Complete</td>";
                                    echo "</tr>";
                                    $k = $k + 1; // necessary for the session complete entry not to show up as an additional row
                                  } // end ['session_complete'] === 'Y' if
                                  else
                                  { // begin ['session_complete'] === 'Y' else

                                    //******************** The session is incomplete ******************
                                    echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                    echo "<th style='border-right:1px solid #ddd;'>Event Status:</th>";
                                    echo "<td class='danger' colspan='5'>&nbsp;Session Incomplete</td>";
                                    echo "</tr>";
                                    echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                    echo "<td colspan='6'>&nbsp;</td>";
                                    echo "</tr>";
                                    $k += 1; // necessary for the session complete entry not to show up as an additional row
                                  } // end ['session_complete'] === 'Y' else
                                } // end [$familyMember][$l][$k]['data_coaching_incident_id'] == [$familyMember][$l][$k+1]['data_coaching_incident_id'] if
                                else
                                { // begin [$familyMember][$l][$k]['data_coaching_incident_id'] == [$familyMember][$l][$k+1]['data_coaching_incident_id'] else

                                  // ************* The last entry in the array OR not from the same coachingincidentlisting/coachingincidentdata family ***********************

                                  echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                  echo "<th style='border-right:1px solid #ddd;'>Event Status:</th>";
                                  echo "<td class='danger' colspan='5'>&nbsp;Session Incomplete</td>";
                                  echo "</tr>";
                                  echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                                  echo "<td colspan='6'>&nbsp;</td>";
                                  echo "</tr>";

                                } // end [$familyMember][$l][$k]['data_coaching_incident_id'] == [$familyMember][$l][$k+1]['data_coaching_incident_id'] if
                                $t=0; // resetting the counter flag

                              } // end !empty($coachMessage) else
                            } // end ['data_coaching_incident_id'] == NULL ) else
                            if ($k === ($listingEventsLength - 1))
                            {
                              echo "<tr class='#details-$button_id mass-coll' style = 'font-size:1vw; display:none;'>";
                              echo <<<EOD
                              <td colspan='6' style='padding:10px; text-align:center;'><button type='button'  class='btn btn-info active' onclick="returnToTop('section-$button_id')">Return to Section Top</button>&nbsp;&nbsp;
                              <button type='button' id='returnToTop' class='btn btn-primary active' onclick="returnToTop('tops')">Return to Page Top</button></td>
EOD;
                              echo "</tr>";
                            }
                          } // end $k for
                        } // end $l === 0 else
                      } // end $l for
                    } // end $u for
                    echo "<tbody>";
                  echo "</table>";
                echo "</div>";
              echo "</div>";
            echo "</div>";
          echo "</div>"; // End of Coaching History Table Section
        } //end parentIDsLength for
      } // end parentIDs if

    ?>
  </div> <!--End Cluster Section-->

  <div id="helpBox" class="container helps"> <!-- Help Box -->
    <div class = 'col-xs-12'>
      <div class = 'panel panel-default help-panel'>
        <div class = 'panel-heading'>
          <h4>Coaching History Help</h4>
        </div>
      </div>
      <div class = 'panel panel-default help-panel'>
        <div class = 'panel-heading'>
          <span>Cluster Button Color Scheme</span>
        </div>
        <div class = 'panel-body'>
          <button class='btn' style='color:white; background-color:#29a319'>Cluster #</button><br>
          <span>All encompassed sessions completed</span><br>
          <button class='btn' style='color:white; background-color:#c21b2c'>Cluster #</button><br>
          <span>One or more encompassed session(s) incomplete</span>
        </div>
      </div>
      <div class = 'panel panel-default help-panel'>
        <div class = 'panel-heading'>
          <span>Enclaves Key</span>
        </div>
        <div class = 'panel-body'>
          <span>E0 = Introduction</span><br>
          <span>E1 = Relativism Range</span><br>
          <span>E2 = Egoism Empire</span><br>
          <span>E3 = Utilitarian Union</span><br>
          <span>E4 = Kantian Kingdom</span><br>
          <span>E5 = Virtue Village</span><br>
          <span>E6 = Care Community</span><br>
          <span>E7 = Social Contract Confederacy</span><br>
          <span>E8 = Natural Law Nation</span><br>
          <span>E9 = Stoic Stronghold</span><br>
          <span>EA = Conclusion</span><br>
          <span>ZZ = Enclave Independent</span><br>
        </div>
      </div>
      <div class = 'panel panel-default help-panel'>
        <div class = 'panel-heading'>
          <span>Event Description/Status Definitions</span>
        </div>
        <div class = 'panel-body' style='padding: 15px 0px 15px 0px;'>
          <ul style='padding-inline-start: 30px;'>
            <li><span><u>Triggering Event:</u></span></li>
            <ul style='padding-inline-start: 15px;'>
              <li><span>Event that triggers a coaching session</span></li>
            </ul>
            <li><span><u>Mentoring Jar Open:</u></span></li>
            <ul style='padding-inline-start: 15px;'>
              <li><span>Diogenes's Mentoring Jar window opens</span></li>
            </ul style='padding-inline-start: 15px;'>
            <li><span><u>Coaching Interaction Started:</u></span></li>
            <ul style='padding-inline-start: 15px;'>
              <li><span>The conversation with Diogenes begins</span></li>
            </ul>
            <li><span><u>Mentoring Jar Closed - Session Aborted:</u><span></li>
              <ul style='padding-inline-start: 15px;'>
                <li><span>The user closed the Jar window after it opened without participating in the session</span></li>
              </ul>
            <li><span><u>Session Incomplete:</u></span></li>
            <ul style='padding-inline-start: 15px;'>
              <li><span>The user began the conversation with Diogenes, but didn't complete it</span></li>
            </ul>
            <li><span><u>Session Complete:</u></span></li>
            <ul style='padding-inline-start: 15px;'>
              <li><span>The user completed the entire coaching session conversation</span></li>
            </ul>
          </ul>
        </div>
      </div>
    </div>
  </div> <!-- end Help Box -->

  <!--Beginning Close Button Code-->
  <div id = "bottom-close" class="container bott-close">
    <div class = "row row-page-close" style = "background-color:white;">
      <div class = "col-xs-6 col-xs-offset-3">
        <div class = "panel panel-default bott-close-panel">
          <button type="button" class="btn btn-danger active close-button" data-dismiss="modal" style="width:100%;">Close Window</button>
        </div>
      </div>
    </div>
  </div> <!--End Close Button Code-->

</body>

<script>

//This script is responsible for the hiding effect of each Coaching Session's details

function vaporize(clicked_id)
{ // begin vaporize function
  var id =""+clicked_id+"";
  console.log("The selected button id = "+id);
  var details_coll = document.getElementsByClassName(id);
  var i;

  for (i = 0; i < details_coll.length; i++)
  { // begin for
    if (id === "mass-coll")
    { //begin id === "mass-coll" if
      details_coll[i].style.display = "none";
    } //end id === "mass-coll" if
    else
    { // begin id === "mass-coll" else
      if (details_coll[i].style.display === "none")
      {
        details_coll[i].style.display = "table-row";
      }
      else
      {
        details_coll[i].style.display = "none";
      }
    } // end id === "mass-coll" else
  } // end for
} // end vaporize function

</script>

<script>

// This script controls the navigation buttons
function returnToTop(location)
{ // begin returnToTop function

  var mod = document.getElementById("student-progress-modal");
  observer.disconnect();

  // Checking if the modal was just opened
  if (mod.style.height !== "800px")
  { //  begin mod.style.height !== 800px if

    //Resizing the modal window
    mod.style.height="800px";

    var navBar = document.getElementById("pageNavigationContainer");

    // Moving the Navigation tool bar to its final position
    navBar.style.left = "5px";
    navBar.style.top = "180px";

    console.log("Inside the returnToTop function and navBar left = "+navBar.style.left+" and top = "+navBar.style.top);

    var bottomClose = document.getElementById("bottom-close");

    //Moving the close button to its final position
    bottomClose.style.top = "755px";

    // Inserting missing NavButtons
    var missingButtons = document.getElementsByClassName("notAtStart");
    var i;

    for (i = 0; i < missingButtons.length; i++)
    { // begin for
      missingButtons[i].style.display = "inline-block";
    } // end for

    // Making the history data appear
    var dataContainer = document.getElementById("historyData");

    var helpContainer = document.getElementById("helpBox");

    // The querySelector finds the pageNavigationContainer and the if determines if the container has reached its final position.

    const el = document.querySelector("#pageNavigationContainer");

    if (el.style.left = "5px")
    { // begin el.style.left = "5px" if

      // Get navBar's specs to determine its final height. Since the navBar can have several rows of cluster buttons it's necessary to determine it's height everytime so that the history data and help containers can be placed at the correct position.

      // Retrieving navBar specs
      var navBarData = navBar.getBoundingClientRect();
      var navBarHeight = navBarData.height;
      var navBarBottom = navBarData.bottom;

      console.log("The final navBar top is located at:  "+navBarData.top+"px");
      console.log("The final navBar bottom is located at:  "+navBarBottom+"px");
      console.log("The final navBar height is : "+navBarHeight+"px");
      //console.log("The final navBar left position is located at:  "+navBarData.left+"px");

      // The getBoundingClientRect function returns values with respect to the viewport and not the top of the modal. Thus, this difference needs to be taken into account in the calculations below to ensure that everything lines up correctly.

      // Amount of pixels between the top of the viewport and top of the modal
      var viewPortDelta = navBarData.top - 180;

      console.log("The viewPortDelta = "+viewPortDelta);

      //Calculating the pixel value for the history data container top and height attributes
      dataContainerTop = (navBarBottom - viewPortDelta) + 10;
      dataContainerHeight = 731 - (navBarBottom - viewPortDelta);

      console.log("The final dataContainer Top is located at: "+dataContainerTop+"px");
      console.log("The final height of the data container is: "+dataContainerHeight+"px");

      // Assigning the attributes to the history data and help containers
      dataContainer.style.top = ""+dataContainerTop+"px";
      helpContainer.style.top = ""+dataContainerTop+"px";
      dataContainer.style.height = ""+dataContainerHeight+"px";
      helpContainer.style.height = ""+dataContainerHeight+"px";

      // Delaying the appearance of the data and help until the modal has had a chance to grow to its final size.

      setTimeout(()=>{dataContainer.style.display="block";},1200);
      //making the helpbox appear but stays hidden behind data container until the help button is clicked
      setTimeout(()=>{helpContainer.style.display="block";},1300);

      // Scrolling the selected cluster into view
      setTimeout(function()
      {
        console.log("The passed location is = "+location);
        var loc =""+location+"";
        console.log("The selected button id = "+loc);
        document.getElementById(loc).scrollIntoView(true);
      }, 1500);

    } // end el.style.left = "5px" if
  } //  end mod.style.height !== 800px if

  // The code below runs when the the window is already open and the user is just trying to focus in on a different cluster or return to the top

  // Energizing the NavButtons
  console.log("The passed location is = "+location);
  var loc =""+location+"";
  console.log("The selected button id = "+loc);
  document.getElementById(loc).scrollIntoView(true);

  return false;
} // end returnToTop function

</script>

<script>

//This script is responsible for recognizing all the mutations that occur when the history modal is first displayed. It calculates the correct position for the navBar and resets the size of the modal when the window is first opened.

var modalFrame = document.getElementById("modalFrame");

if (modalFrame.style.display === "" || modalFrame.style.display === "none")
{ // begin modalFrame.style.display if

  var mod = document.getElementById("student-progress-modal");
  console.log("It is none");
  // Reset the size of the modal when first opened to its smaller version
  mod.style.height="400px";

  //Options for the observer (which mutations to observe)
  var options = { attributes: true, childList: true, subtree: true};

  //Callback function to execute when mutations are observed
  var callback = function(mutationsList, observer)
  { // begin callback function
    for(let mutation of mutationsList)
    { // begin mutation for
      console.log("The mutation is: "+mutation);
      if (mutation.type==='attributes')
      { // begin mutation.type if
        console.log("The "+mutation.attributeName+" attribute was modified");
        if (modalFrame.style.display==="block")
        { // begin modalFrame.style.display if
          console.log("It is block");
          tailorCalcs();
        } // end modalFrame.style.display if
        else if (modalFrame.style.display==="none")
        { // begin modalFrame.style.display else
          console.log("It is none");
          mod.style.height="400px";
        } // end modalFrame.style.display else
      } // end mutation.type if
    } // end mutation for
  }; // begin callback function

  //Create an observer instance linked to the callback function
  var observer = new MutationObserver(callback);

  //Start observing the target node for configured mutations
  observer.observe(modalFrame, options);

  // The tailorCalc function is responsible for calculating the correct position for the navBar when the modal is first opened so that the bar is centered in the modal

  function tailorCalcs()
  { // begin tailorCalc function

    var historyModal = document.getElementById("student-progress-modal");
    // Getting formatting info on the modal
    var historyModalData = historyModal.getBoundingClientRect();
    console.log("The historyModalWidth = "+historyModalData.width);

    // Importing the number of Clusters so that a correct navBar height determination
    var numOfClusters = <?php echo json_encode($parentIDsLength, JSON_HEX_TAG); ?>;
    console.log("The number of Clusters = "+numOfClusters);

    var numOfClustersModulus = numOfClusters % 8;

    console.log("The numOfClustersModulus = "+numOfClustersModulus);

    var navBar=document.getElementById("pageNavigationContainer");

    // Getting formatting info on the NavBar
    var navBarData = navBar.getBoundingClientRect();
    console.log("The navBarWidth = "+navBarData.width);
    console.log("The navBarHeight = "+navBarData.height);

    // Calculating the style.left position for the NavBar
    var upfrontWhiteSpace = Math.round((historyModalData.width - navBarData.width) / 2);
    console.log("The upfrontWhiteSpace = "+upfrontWhiteSpace);

    navBar.style.left=""+upfrontWhiteSpace+"px";

    /* This is the height of the modal header area */
    const topOfModalHeight = 180;
    /* This is the height of the close window button at bottom of page */
    const closeButtonHeight = 40;
    /* This is the initial available blank area between the header and close window button area */
    const availableInitialRealEstate = 400 - topOfModalHeight - closeButtonHeight;

    /* Calculating the amount of white space needed at the top of the available white space to center the button bar */
    var upTopWhiteSpace = Math.round((availableInitialRealEstate - navBarData.height) / 2) + 180;

    /* Due to the way the animated transition works, the display of clusters buttons where the number of clusters returns a modulus of 1 when divided by 8 (i.e. the number of clusters that fit into one row of the navBar) doesn't run very smoothly. The animation runs as if all clusters would fit in one row only to find out at the last moment that another row is needed. The code below takes care this problem by determining if the modulus is 1 and then adjusting the white space calculation. */

    if (numOfClustersModulus === 1)
    {
      upTopWhiteSpace = upTopWhiteSpace - 34;
    }

    console.log("The upTopWhiteSpace = "+upTopWhiteSpace);

    /* If not enough white space is left due to the amount of buttons in the navBar then the modal window size is increased following the math formulas below */
    if (upTopWhiteSpace >= 180 && upTopWhiteSpace <= 185)
    {
      mod.style.height="420px";
      navBar.style.top=""+upTopWhiteSpace+"px";
    }
    else if (upTopWhiteSpace < 180)
    {
      var neededSpace = Math.round(Math.abs(availableInitialRealEstate - navBarData.height) / 2);
      var newModalHeight = 400 + neededSpace;
      navBar.style.top=""+upTopWhiteSpace+"px";
      mod.style.height=""+newModalHeight+"px;";
    }
    else
    {
      navBar.style.top=""+upTopWhiteSpace+"px";
    }

    // Disconnecting the observer because its job is done
    observer.disconnect();

  } // end tailorCalc function
} // end modalFrame.style.display if

</script>

<script>

function helpSideBar(clicked_id)
{

  var id = ""+clicked_id+"";
  console.log("The selected button id = "+id);
  var helpColl = document.getElementById(id);

  var helpButt = document.getElementById("helpButton");

  var histData = document.getElementById("historyData");

console.log("The helpBox display is set to: "+helpColl.style.display);

  if (helpColl.style.animation === "")
  {
console.log("Inside the helpBox display = none if");
    helpButt.classList.remove('btn-default');
    helpButt.className="btn btn-primary";
    histData.style.animation="histSlideForward 2s forwards";
    histData.style.animationTimingFunction = "ease";
    histData.style.animationFillMode ="forwards";
    histData.style.border = "3px solid black";
    histData.style.padding = "10px";
    helpColl.style.animation="toggle 2s forwards";
    helpColl.style.animationFillMode ="forwards";
    helpColl.style.overflowY="auto";
    helpColl.style.border = "3px solid black";
  }
  else
  {
console.log("Inside the helpBox display = none else");
    histData.style.animation="histSlideBackwards 2s forwards";
    histData.style.animationFillMode ="forwards";
    histData.style.animationTimingFunction = "ease";
    setTimeout(()=>{histData.style.animation = ""},3000);
    setTimeout(()=>{helpColl.style.animation = ""},3000);
    //histData.style.left = "5px";
    histData.style.border = "none";
    histData.style.padding = "0px";
    histData.style.transition = "2s";
    helpButt.classList.remove('btn-success');
    helpButt.className="btn btn-default";
  }

} // end helpSideBar function

</script>

</html>
