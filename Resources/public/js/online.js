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
    // var online = doesConnectionExist();
    // if(!online){
        // document.getElementById('buttonSynchronize').setAttribute('disabled', 'disabled');
    // }
}());


// Taken from http://www.kirupa.com/html5/check_if_internet_connection_exists_in_javascript.htm
function doesConnectionExist() {
    var xhr = new XMLHttpRequest();
    var url = "http://www.google.com";
    xhr.open('GET', url, false);
     
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
