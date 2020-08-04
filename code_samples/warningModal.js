


  // Get the modal
  var modal = document.querySelector(".warning-modal");

  var trigger = document.querySelector(".trigger");

  var closeButton = document.querySelector(".close-button");

  var originatingFile = document.location.pathname;
  console.log("The originatingFile is: "+originatingFile);

  function toggleModal(e)
  { // begin toggleModal function
    console.log(window.opener);
    console.log("In toggleModal function");
    //The preventDefault keeps the script from opening a new window in resposne to the click that activated this script.
    e.preventDefault();
    console.log("default "+event.type+" prevented");
    modal.classList.toggle("show-modal");
  } // end toggleModal function

  function closingToggleModal(e)
  { // begin closingToggleModal function
    if (window.opener && !window.opener.closed)
    { // begin window.opener if
      console.log("in window opener if");
      //The preventDefault keeps the script from opening a new window in resposne to the click that activated this script.
      e.preventDefault();
      console.log("default "+event.type+" prevented");
      modal.classList.toggle("show-modal");
      var URL = 'yourStatus.php';
      //opener.location.href=URL;
      //window.close();
    } // end window.opener if
    else
    { // begin window.opener else
      console.log("In window opener else");
      //The preventDefault keeps the script from opening a new window in resposne to the click that activated this script.
      e.preventDefault();
      console.log("default "+event.type+" prevented");
      if (originatingFile == "/master")
      { // begin originatingFile = master if
        toggleModal(event);
      } // end originatingFile = master if
      else
      { // begin originatingFile = master else
        toggleModal(event);
        var URL = 'yourStatus.php';
        document.location.href=URL;
      } // end originatingFile = master else
    } // end window.opener else
  } // end closingToggleModal function

  function windowOnClick(event)
  { // begin windowOnClick function
    if (event.target === modal)
    { // begin event.target === modal if
      if (originatingFile == "/master")
      { // begin originatingFile = master if
        toggleModal(event);
      } // end originatingFile = master if
      else
      { // begin originatingFile = master else
        toggleModal(event);
        var URL = 'yourStatus.php';
        document.location.href=URL;
      } // end originatingFile = master else
    } // end event.target === modal if
  } // end windowOnClick function

  trigger.addEventListener("click", toggleModal);
  closeButton.addEventListener("click", closingToggleModal);
  window.addEventListener("click", windowOnClick);
