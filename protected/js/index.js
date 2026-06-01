const logRegHandler = document.querySelector('.logreg-box');

const loginLink = document.querySelector('.login-link');

const registerLink = document.querySelector('.register-link');

registerLink.addEventListener('click', () => {
  logRegHandler.classList.add('active');
});

loginLink.addEventListener('click', () => {
  logRegHandler.classList.remove('active');
});

let slideIndex = 0;
showSlides();

//SLIDER SCRIPT
function showSlides() {
  let i;
  let slides = document.getElementsByClassName("slider-img");
  let dots = document.getElementsByClassName("dot");
  for (i = 0; i < slides.length; i++) {
    slides[i].style.display = "none";
  }
  slideIndex++;
  if (slideIndex > slides.length) {slideIndex = 1}
  for (i = 0; i < dots.length; i++) {
    dots[i].className = dots[i].className.replace("active", "");
  }
  slides[slideIndex-1].style.display = "block";
  dots[slideIndex-1].className += " active";
  setTimeout(showSlides, 2500); // Change image every 2 seconds
}

// NOTE: Global page pre-loader (#pre-loader) was removed.

var password = document.getElementById("password")
    , confirm_password = document.getElementById("confirm_password");

function validatePassword(){
  if(password.value != confirm_password.value) {
    confirm_password.setCustomValidity("Passwords Don't Match");
  } else {
    confirm_password.setCustomValidity('');
  }
}

password.onchange = validatePassword;
confirm_password.onkeyup = validatePassword;


function togglePassword(inputId, eyeId) {
  var passwordInput = document.getElementById(inputId);
  var eyeIcon = document.getElementById(eyeId);

  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    eyeIcon.classList.remove("ri-eye-fill");
    eyeIcon.classList.add("ri-eye-off-fill");
  } else {
    passwordInput.type = "password";
    eyeIcon.classList.remove("ri-eye-off-fill");
    eyeIcon.classList.add("ri-eye-fill");
  }
}


const expandRegister = (toggleId, mainId, logregId) => {
  const
      toggle = document.getElementById(toggleId),
      main = document.getElementById(mainId),
      log_reg_box = document.getElementById(logregId)

  if(toggleId && main)
  {
    toggle.addEventListener('click', () => {
      main.classList.toggle('hide-main')
      log_reg_box.classList.remove('target_box')
      log_reg_box.classList.toggle('expand-log_reg')
    })
  }
}

expandRegister('register-toggle', 'main-content', 'log_reg_box' )

const contractRegister = (toggleId, mainId, logregId) => {
  const
      toggle = document.getElementById(toggleId),
      main = document.getElementById(mainId),
      log_reg_box = document.getElementById(logregId)

  if(toggleId && main)
  {
    toggle.addEventListener('click', () => {
      main.classList.remove('hide-main')
      log_reg_box.classList.add('target_box')
      log_reg_box.classList.remove('expand-log_reg')
    })
  }
}

contractRegister('login-toggle', 'main-content', 'log_reg_box' )