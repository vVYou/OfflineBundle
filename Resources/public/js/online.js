/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

(function () {
    // var online = window.navigator.onLine;
    var online = doesConnectionExist();
    alert(!online);
    if(online){
        document.getElementById('buttonSynchronize').setAttribute('disabled', 'disabled');
    }else{
        alert("Je suis online");
    }
}());


// Taken from http://www.kirupa.com/html5/check_if_internet_connection_exists_in_javascript.htm
function doesConnectionExist() {
    var xhr = new XMLHttpRequest();
    var file = "http://www.google.com";
    // var randomNum = Math.round(Math.random() * 10000);
    alert("Je test un truc");
    xhr.open('HEAD', file, false);
     
    try {
        xhr.send();
         
        if (xhr.status >= 200 && xhr.status < 304) {
            return true;
        } else {
            return false;
        }
    } catch (e) {
        return false;
    }
}
