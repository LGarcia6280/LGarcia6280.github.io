<?php

// This file compiles all the information necessary for the Coaching Center tab in the Faculty Control Panel. The display of that information is controlled in views/faculty/roll_call.php. This script is meant to be run as a followup to root/pullCRN.php.  It will not run without that other script running first.

// Created by Luis García on 20191112

$selectedIDs = array();
$occasionPresent = array();
$occasionInfo = array();

// This first query determines the number of coaching occasions present in the database. The reason for the query is to accomodate future expansion of coaching occasions. Instead of hardcoding the number this approach makes it more flexible for future growth

$whatOccasionsSql = "SELECT coaching_occasion_id, coaching_occasion_description FROM CoachingOccasion WHERE coaching_occasion_id <> -1 AND deprecated = 0 ORDER BY coaching_occasion_id ASC;";

$whatOccasionsResult = $link->query($whatOccasionsSql);

$numberOfOccasions = mysqli_num_rows($whatOccasionsResult);

while ($whatOccasionsRow = $whatOccasionsResult->fetch_assoc())
{
  $occasionData['coaching_occasion_id'] = $whatOccasionsRow['coaching_occasion_id'];
  $occasionData['coaching_occasion_description'] = $whatOccasionsRow['coaching_occasion_description'];

  $occasionInfo [] = $occasionData;
}

$studentsLength = sizeof($students); //This is the length of the students array created in root/pullCRN.php

for ($z=0; $z < $studentsLength; $z++)
{ //begin $z if
  $selectedIDs[] = $students[$z]['stUserID'];
} // end $z if

for ($t=0; $t<$studentsLength; $t++)
{ // begin $t for
  $stID = $selectedIDs[$t];

  // The following for loop initializes the $occasionPresent array to blank strings. The appropriate blank statements will be replaced with X values for those coaching occasions the student has triggered

  for ($x=0, $loopCount = $numberOfOccasions; $x<$loopCount; $x++)
  { // begin $x for
    $v = $occasionInfo[$x]['coaching_occasion_id'];
    $occasionPresent[$stID][$v] = " ";
  } // end $x for

} // end $t for

$in = '('. implode(',',$selectedIDs) .')';

$occasionSql = "SELECT stUserID, coaching_occasion_id, COUNT(coaching_session_id) as sessionCount FROM CoachingIncidentListing WHERE stUserID IN $in AND coCRN = '$crn' GROUP BY coaching_occasion_id, stUserID ORDER BY stUserID ASC, coaching_occasion_id ASC;";

if ($occasionResults = $link->query($occasionSql))
{ // begin $occasionResults = $link->query($occasionSql) if

  if (mysqli_num_rows($occasionResults) > 0)
  { //begin mysqli_num_rows($occasionResults) > 1 if

    //The while extracts the information from the query and places the count of that type of sessions triggered by the user on the previously initialized array to signify that the particular occasion is present in the student record

    while ($foundOccasions = $occasionResults->fetch_assoc())
    { // begin $foundOccasions = $occasionResults->fetch_assoc() while

      $stID = $foundOccasions['stUserID'];
      $o = $foundOccasions['coaching_occasion_id'];
      $sessionCount = $foundOccasions['sessionCount'];
      $occasionPresent[$stID][$o] = $sessionCount;

    } // end $foundOccasions = $occasionResults->fetch_assoc() while
  } // end mysqli_num_rows($occasionResults) > 1 if
} // end $occasionResults = $link->query($occasionSql) if

 ?>
