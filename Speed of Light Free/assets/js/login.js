document.addEventListener('DOMContentLoaded', function () {
    var loginBox = document.getElementById('login');
    var footer = document.getElementById('dd-login-footer');

    if (loginBox && footer) {
        loginBox.appendChild(footer);
    }
});
