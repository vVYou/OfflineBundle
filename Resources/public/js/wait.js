
/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

(function () {
    event.preventDefault();
    $('#buttonSynchronize').on('click', function(event) {
        alert("Je suis sur onclick");
        $.ajax({
           url: $(event.currentTarget).attr('href'),
           success: function(data) {window.location = Routing.generate('claro_sync_result')}
        });
        alert("avec Ajax");
    });
}