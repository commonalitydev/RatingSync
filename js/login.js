
function validateRegistrationInput() {
    var valid = true;
    var msg = "";

    // Clear message
    var msgEl = document.getElementById("msg-reg-warning");
    if (msgEl) {
        msgEl.hidden = true;
    }
    
    var username = "";
    var usernameEl = document.getElementById("username-reg");
    if (usernameEl) {
        username = usernameEl.value.trim();
        document.getElementById("username-verify").value = username;
    }
    var password = "";
    var passwordEl = document.getElementById("password-reg");
    if (passwordEl) {
        password = passwordEl.value.trim();
        document.getElementById("password-verify").value = password;
    }
    var passwordConfirm = "";
    var passwordConfirmEl = document.getElementById("password-reg-confirm");
    if (passwordConfirmEl) {
        passwordConfirm = passwordConfirmEl.value.trim();
    }

    if (username.length == 0 || password.length == 0 || passwordConfirm.length == 0) {
        // Required field missing value
        valid = false;
        msg = "All fields are required";
    } else if (password != passwordConfirm) {
        valid = false;
        msg = "Passwords do not match";
    } else {
        // Disable register input elements
        var inputElements = new Array();
        inputElements.push(usernameEl);
        inputElements.push(passwordEl);
        inputElements.push(passwordConfirmEl);
        for (var i = 0; i < inputElements.length; i++) {
            inputElements[i].disabled = true;
        }

        // Request validation from server
            var params = "?action=validateNewUsername";
        params = params + "&u=" + username;
	    var xmlhttp = new XMLHttpRequest();
	    var callbackHandler = function () {
	        if (xmlhttp.readyState == 4) {
                for (var i = 0; i < inputElements.length; i++) {
                    inputElements[i].disabled = false;
                }
                if (xmlhttp.status == 200) {
	                validateNewUsernameCallback(xmlhttp, username);
                }
	        }
	    };
        xmlhttp.onreadystatechange = callbackHandler;
	    xmlhttp.open("GET", RS_URL_API + params, true);
	    xmlhttp.send();
    }

    if (!valid && msgEl) {
        msgEl.innerHTML = msg;
        msgEl.hidden = false;
    }
}

function validateNewUsernameCallback(xmlhttp, username) {
    var result = JSON.parse(xmlhttp.responseText);
    var msgEl = document.getElementById("msg-reg-warning");
    if (result.valid && result.valid.toLowerCase() == "true") {
        if (msgEl) {
            msgEl.hidden = true;
        }
        $('#registerModal').modal('show');
    } else {
        if (msgEl) {
            msgEl.innerHTML = "Username not available";
            msgEl.hidden = false;
        }
    }
}

function renderMsg(message, element) {
    if (element) {
        if (message && message.length > 0) {
            element.innerHTML = message;
            element.hidden = false;
        } else {
            element.hidden = true;
        }
    }
}