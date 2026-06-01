function openPopup(){
    document.getElementById("popupForm").style.display = "block";
    document.getElementById("popupForm").style.animation = "slideIn 0.5s ease"
    document.getElementById("overlay").style.display = "block";
}

function openPopup2(){
    document.getElementById("popupForm2").style.display = "block";
    document.getElementById("popupForm2").style.animation = "slideIn 0.5s ease"
    document.getElementById("overlay").style.display = "block";
    // Set payee field to logged-in user's name when opening the form
    const payeeInput = document.getElementById("payee_name");
    if (payeeInput) {
        const defaultPayee = payeeInput.getAttribute("data-default") || "";
        payeeInput.value = defaultPayee;
    }
}

function openPopup3(){
    document.getElementById("reply_form").style.display = "block";
    document.getElementById("reply_form").style.animation = "slideIn 0.5s ease"
    document.getElementById("overlay").style.display = "block";
}

function openPopupAda(){
    document.getElementById("adaForm").style.display = "block";
    document.getElementById("adaForm").style.animation = "slideIn 0.5s ease"
    document.getElementById("overlay").style.display = "block";
}

var elements = document.getElementsByClassName("pPop");

for (var i=0; i < elements.length; i++) {
    elements[i].addEventListener('click', openPopup, false);
}

var elements55 = document.getElementsByClassName("pPopAda");

for (var i=0; i < elements55.length; i++) {
    elements55[i].addEventListener('click', openPopupAda, false);
}

if (document.getElementById("overlay")){
    document.getElementById("overlay").addEventListener("click", closePopup);
    document.getElementById("overlay").addEventListener("click", closePopup2);
    document.getElementById("overlay").addEventListener("click", closePopup3);
    document.getElementById("overlay").addEventListener("click", closePopup4);
    document.getElementById("overlay").addEventListener("click", closePopup5);
}

function closePopup(){
    if (document.getElementById("popupForm"))
    {
        document.getElementById("popupForm").style.display = "none";
    }
    if (document.getElementById("overlay"))
    {
        document.getElementById("overlay").style.display = "none";
    }
    if (document.getElementById("checker"))
    {
        document.getElementById("checker").checked = false;
    }
}

function closePopup2(){
    if (document.getElementById("popupForm2"))
    {
        document.getElementById("popupForm2").style.display = "none";
    }
    if (document.getElementById("overlay"))
    {
        document.getElementById("overlay").style.display = "none";
    }
    if (document.getElementById("checker"))
    {
        document.getElementById("checker").checked = false;
    }
}

function closePopup3(){
    if (document.getElementById("popupForm3"))
    {
        document.getElementById("popupForm3").style.display = "none";
    }
    if (document.getElementById("overlay"))
    {
        document.getElementById("overlay").style.display = "none";
    }
    if (document.getElementById("checker"))
    {
        document.getElementById("checker").checked = false;
    }
}

function closePopup4(){
    if (document.getElementById("reply_form"))
    {
        document.getElementById("reply_form").style.display = "none";
    }
    if (document.getElementById("overlay"))
    {
        document.getElementById("overlay").style.display = "none";
    }
    if (document.getElementById("checker"))
    {
        document.getElementById("checker").checked = false;
    }
}

function closePopup5(){
    if (document.getElementById("adaForm"))
    {
        document.getElementById("adaForm").style.display = "none";
    }
    if (document.getElementById("overlay"))
    {
        document.getElementById("overlay").style.display = "none";
    }
}


var elements2 = document.getElementsByClassName("popupForm-add");

for (var i=0; i < elements2.length; i++) {
    elements2[i].addEventListener('click', openPopup2, false);
}

var elements3 = document.getElementsByClassName("popupForm_reply");

for (var i=0; i < elements3.length; i++) {
    elements3[i].addEventListener('click', openPopup3, false);
}




if (document.getElementById('close_popup3')){
    document.getElementById("close_popup3").addEventListener("click", closePopup);
}
if (document.getElementById('close_popup4')){
    document.getElementById("close_popup4").addEventListener("click", closePopup);
}
if (document.getElementById('close_popup')){
    document.getElementById("close_popup").addEventListener("click", closePopup2);
}
if (document.getElementById('close_popup2')){
    document.getElementById("close_popup2").addEventListener("click", closePopup2);
}
if (document.getElementById('close_popup_cp')){
    document.getElementById("close_popup_cp").addEventListener("click", closePopup3);
}
if (document.getElementById('close_popup_cp2')){
    document.getElementById("close_popup_cp2").addEventListener("click", closePopup3);
}
if (document.getElementById('close_popup_ada')){
    document.getElementById("close_popup_ada").addEventListener("click", closePopup5);
}

//FOR REPLY FORM
if (document.getElementById('close_reply_form')){
    document.getElementById("close_reply_form").addEventListener("click", closePopup4);
}
if (document.getElementById('close_reply_form')){
    document.getElementById("close_reply_form2").addEventListener("click", closePopup4);
}

var elements4 = document.getElementsByClassName("popupForm-edit_user");

for (var i=0; i < elements4.length; i++) {
    elements4[i].addEventListener('click', openPopup2, false);
}


if (document.getElementById('btn_reply_clear')){
    document.getElementById('btn_reply_clear').addEventListener('click', () => {
        if (confirm("Are you sure to clear?")) {
            document.getElementById('document_reply').value = "";
        }
    });
}