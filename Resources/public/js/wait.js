
/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$('#buttonSynchronize').on('click', function(event) {
    event.preventDefault();
    $.ajax({
       url: $(event.currentTarget).attr('href'),
       success: function(html) {
            $('#offline-content').html(html);
        },
        error: function(jqXHR, textStatus, errorThrown) {
            var html = '<div class="alert alert-danger" role="alert">   <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button> ' + jqXHR.responseJSON.msg + '</div>'
            $('#offline-content').prepend(html);
        }
    });
});