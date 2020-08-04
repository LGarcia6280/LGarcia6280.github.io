<?php

//start a session if one hasn't been started
if (session_status() === PHP_SESSION_NONE)
{
  session_start();
}

require_once('startsession.php');
require_once('appvars.php');
require_once('library/Auth.php');
require_once('library/Debug.php');
require_once('library/Students.php');
require_once('library/Courses.php');
require_once('library/Database.php');
require_once('services/Intervention.php');
require_once('classes/CoachLibrary.php');
require_once('classes/CoachNoLogin.php');
require_once('classes/CoachPowerThru.php');
require_once('classes/CoachBadFive.php');
require_once('classes/CoachWander.php');
require_once('classes/CoachFirstHint.php');
require_once('classes/CoachFirst.php');
require_once('classes/CoachCommunity.php');

ini_set("log_errors",1);
ini_set("error_log","F:/chapters_errorlogs/log.txt");

$page_title = 'Interaction Window';
// ***************** Execute Top Session ***********************

if (empty($_POST['choose-interaction']))
{
  $topSessionPendingId = $_GET['csd'];
  $chooseInteraction = -1;
}
else
{
  $chooseInteraction = $_POST['choose-interaction'];
  $topSessionPendingId = $_SESSION['coaching-session-id'];
}

if (!empty($_POST['choose-challenge']))
{
  $topSessionPendingId = $_SESSION['coaching-session-id'];
  $chooseChallenge = $_POST['choose-challenge'];
}
else
{
  $chooseChallenge = -1;
}

if (!empty($_POST['final_entry']))
{
  $finalEntry = $_POST['final_entry'];
}
else
{
  $finalEntry = -1;
}

// The following stmt is used to derive the occasion id from the session id passed from root/yourStatus.php. The occasion id is used to select the appropriate class for the highest priority session in the interventionqueue table in the db.

$topOccasionPending = (int)floor($topSessionPendingId/10);

switch ($topOccasionPending)
{ // begin topSessionPending switch
  case 1:
    //the statement below creates a new object of class type CoachLibrary found in classes/CoachLibrary.php
    $topSession = new CoachLibrary;
    $session_data = $topSession->execute($topSessionPendingId, $chooseInteraction,$chooseChallenge,$finalEntry);
    break;
  case 2:
    $topSession = new CoachFirst;
    $session_data = $topSession->execute($topSessionPendingId, $chooseInteraction,$chooseChallenge,$finalEntry);
    break;
  case 3:
    $topSession = new CoachBadFive;
    $session_data = $topSession->execute($topSessionPendingId, $chooseInteraction,$chooseChallenge,$finalEntry);
    break;
  case 4:
    $topSession = new CoachNoPass;
    $topSession->execute();
    break;
  case 5:
    //(Added lg 20191020)
    $topSession = new CoachPowerThru;
    $session_data = $topSession->execute($topSessionPendingId, $chooseInteraction,$chooseChallenge,$finalEntry);
    break;
  case 6:
    $topSession = new CoachDuringTest;
    $topSession->execute();
    break;
  case 7:
    $topSession = new CoachCommunity;
    $session_data = $topSession->execute($topSessionPendingId, $chooseInteraction,$chooseChallenge,$finalEntry);
    break;
  case 8:
    $topSession = new CoachWander;
    $session_data = $topSession->execute($topSessionPendingId, $chooseInteraction,$chooseChallenge,$finalEntry);
    break;
  case 9:
    $topSession = new CoachNoLogin;
    $session_data = $topSession->execute($topSessionPendingId, $chooseInteraction,$chooseChallenge,$finalEntry);
    break;
  case 10:
    $topSession = new CoachFirstHint;
    $session_data = $topSession->execute($topSessionPendingId, $chooseInteraction,$chooseChallenge,$finalEntry);
    break;
  case 11:
    $topSession = new CoachNoHints;
    $topSession->execute();
    break;
  case 12:
    $topSession = new CoachMissedTest;
    $topSession->execute();
    break;

} // end topSessionPending switch
 ?>

<!--***************************HTML*************************-->

<!--the script below runs when the popup window is closing-->
 <script>
  //window.onunload = function()
  function theend()
  { // begin onunload script

    if (!refreshFlag)
    { // begin !refreshFlag
      if (window.opener && !window.opener.closed)
      { // begin if
        opener.location.href='yourStatus.php';
        window.close();
        //window.opener.popUpClosed();
      } // end if
    } // end !refreshFlag
  } // end ununload

  window.addEventListener("unload",theend);

 </script>

 <!--The script below runs whenever there's no CoachPrompt/Challenge after a Coaching Session-->

 <script type="text/javascript">
  function submitform()
  { // begin submitform function
    window.removeEventListener("unload", theend);
    var refreshFlag = true;
    document.getElementById("hiddenpost").submit();
  } // end submitform function
 </script>

 <script> var refreshFlag = false; </script>

<html>
  <head>
    <link rel="stylesheet" type="text/css" href="/css/intervention.css"/>
    <title>Diogenes Corner</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  </head>

  <body>
    <br><br>
    <div class="title_sequence">
      <div class ="plaque">

      </div>

      <h1 style="text-indent:32px"><span>Diogenes's Mentoring Jar</span></h1>
      <div class = "lantern">
        <img src='/images/lantern.png' alt='lantern.png' style="width:150px;height:150px;z-index:1;">
      </div>
      <div class ="bookshelf">
        <img src = '/images/bookshelf.png' alt='bookshelf.png'>
      </div>
      <div class="scroll-sign">
        <img style="width:100%; height:auto;" src = "/images/scroll-sign.png" alt='hanging sign'>
        <div class = "scrolldownmessage">
          <span style="width:100%; height:auto;">Please complete the dialogue with Diogenes below and afterwards click the button at the bottom of the page. You can use the scroll bar on right edge of the window, your mouse or hand gestures to reach the bottom.</span>
        </div>
      </div>
  </div>

  <?php
    if (empty($_POST['choose-interaction']) && (empty($_POST['final_entry'])))
    { // begin empty $_POST'choose-interaction'] if

      echo "<div class='righthandcallout'>";
        echo nl2br($session_data['coach_prompt_text']);
        echo "<b class='righthandnotch'></b>";
      echo "</div>";
      echo "<br>";
      echo "<img class='diogenes-chat' id='smaller-diogenes' src='images/coachingsmall.png' alt='smaller-coaching.png'>";

      if (!empty($session_data['response_text']) && count($session_data['response_text'])!=0)
      { // begin !=0 if

        echo "<div class='lefthandcallout'>";
        echo '<form method="post">';

        for ($i=0; $i < count($session_data['response_text']); $i++)
        //for ($i=0; $i < $numberOfInteractionResponses[0]; $i++)
        { // begin for

          $choice = $session_data['response_text'][$i];
          echo '<label for = "question'.$i.'">';
          echo '<input id = "question'.$i.'" type = "radio" name="choose-interaction" value="'.$choice.'">';
          echo " ".$session_data['response_text'][$i];
          echo "<br>";
          echo "</label>";

        } // end for

        echo "<b class='lefthandnotch'</b>";
        echo "</div>";
        echo "<img class='student-chat' id='smaller-student' src='images/stcoaching.png' alt='smaller-coaching.png'>";
        //echo "<br><br><br>";
        echo '<input class="button" type = "submit" value = "Submit" onclick="refreshFlag = true;">';
        echo "</form>";

        $_SESSION['coaching-session-id'] = $topSessionPendingId;

      } // end !=0 if
      elseif (empty($session_data['response_text']))
      { // begin <1 elseif

        echo '<form method="post">';
          echo '<input id = "hiddenpostinput" type = "hidden" name="choose-interaction" value="none required">';
          echo '<input class="button" type = "submit" value = "I Understand" onclick="refreshFlag = true;">';
        echo "</form>";

        $_SESSION['coaching-session-id'] = $topSessionPendingId;

      } // end < 1 elseif
      // The statement below invokes Javascript to reload the page so as to clear it of previous format
      if (!empty($_POST['choose-interaction']))
      { // begin !empty($_POST['choose-interaction'] if

        echo "<script> window.location.href='intervention.php';</script>";

      } // end !empty($_POST['choose-interaction'] if
    } // end empty $_POST'choose-interaction'] if
    elseif (!empty($session_data['coach_message_text']))
    { // begin !empty($session_data['coach_message_text']) elseif

      //the nl2br inserts HTML line breaks before all newlines in a string. This is done to ensure that coach message has paragraph breaks when appropriate
      echo "<div class='righthandcallout'>";
      echo nl2br($session_data['coach_message_text']);
      echo "<b class='righthandnotch'></b>";
      echo "</div>";
      echo "<br>";
      echo "<img class='diogenes-chat' id='smaller-diogenes' src='images/coachingsmall.png' alt='smaller-coaching.png'>";

      // (Added lg 20200131) The if below checks if there's any challenge text associated with the Coach Message

      if (!empty($session_data['challenge_text']))
      { // begin !empty($session_data['challenge_text'])

        //The if statement below checks to see if a student callout box will be displayed
        if (count($session_data['challenge_text'])!=0)
        {
          echo "<br>";
          echo "<div class='lefthandcallout'>";
        }
        echo '<form method="post">';
        for ($i=0; $i < count($session_data['challenge_text']); $i++)
        { // begin for

          $choice = $session_data['challenge_text'][$i];
          echo '<label for = "question'.$i.'">';
          echo '<input id = "question'.$i.'" class = "radio" type = "radio" name="choose-challenge" value="'.$choice.'">';
          echo " ".$session_data['challenge_text'][$i];
          echo "<br>";
          echo "</label>";

        } // end for

        //The if statement below checks to see if the student icon needs to be displayed as part of a coaching session
        if (count($session_data['challenge_text'])!=0)
        {

          echo "<b class='lefthandnotch'</b>";
          echo "</div>";
          echo "<img class='student-chat' id='smaller-student' src='images/stcoaching.png' alt='smaller-coaching.png'>";

        }
      } // end !empty($session_data['challenge_text']) if

      // (Added lg 20200131) The if statement below sets the $thecount variable based on whether $session_data['challenge_text'] is empty. The content of the $thecount variable determines the type of button placed on the popup window.

      if (empty($session_data['challenge_text']))
      {
        $thecount = 0;
        echo '<form method="post">';
      }
      else
      {
        $thecount = count($session_data['challenge_text']);
      }

      // The if statement below checks to see if there are any answers associated with the coaching message just displayed. If there are it prints the Submit button. Otherwise, it prints the I Understand button.

      if ($thecount > 0)
      { //begin ($thecount > 0) if

        echo '<button class="button" name="final_entry" type="submit" value="final" onclick="refreshFlag = true;">Submit</button>';
        echo "</form>";

      } // end ($thecount > 0) if
      elseif (empty($_POST['final_entry']))
      { // begin ($thecount > 0) else

        echo '<button class="button" name="final_entry" type="submit" value="final" onclick="refreshFlag = true;">I Understand</button>';
        echo "</form>";

      } //end ($thecount > 0) else

    } // end !empty($session_data['coach_message_text']) elseif
    elseif (empty($_POST['final_entry']))
    { // begin empty($_POST['final_entry']) elseif

      //This elseif runs whenever there's no Coach Message/Challenge after a session
      echo '<form id = "hiddenpost" method="post">';
      echo '<input id = "hiddenpostinput" type = "hidden" name="final_entry" value="final">';
      echo "</form>";
      echo '<script type="text/javascript">submitform();</script>';

    } // end empty($_POST['final_entry']) elseif

    if (!empty($_POST['final_entry']))
    { // begin !empty($_POST['final_entry']) if

      echo "<br>";
      echo "<div class='righthandcallout'>";
        echo "<p><span>Remember that you can always approach your instructor with any concerns or issues. I look forward to seeing you progress through Chapters. Good luck and thanks for chatting with me. <span></p>";
        echo "<b class='righthandnotch'></b>";
      echo "</div>";
      echo "<br>";
      echo "<img class='diogenes-chat' id='smaller-diogenes' src='images/coachingsmall.png' alt='smaller-coaching.png'>";

      echo "<br><br>";
      echo '<input class="button" type="button" value="Close" onclick="javascript: theend();"</input>';
      
    } // end !empty($_POST['final_entry']) if
   ?>

 </body>
