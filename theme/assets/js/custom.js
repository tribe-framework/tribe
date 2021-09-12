$(document).ready(function() {
    $.post('/tool/content-swap', {"id": $('.replace_body_on_load').data('id'), "body": $('.replace_body_on_load').html()}, function(data) {
        $('.replace_body_on_load').html(data.body);
    }, 'json');
});