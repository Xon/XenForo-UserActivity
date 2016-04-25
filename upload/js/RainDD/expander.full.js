jQuery(document).ready(function($){

    // The height of the content block when it's not expanded
    var internalheight = $(".uaExpandThreadRead").outerHeight();
    var adjustheight = $(".uaExpandThreadRead").data('threadreadlimit');
    // The "more" link text
    var moreText = $(".uaExpandThreadRead").data('more');
    // The "less" link text
    var lessText = $(".uaExpandThreadRead").data('less');

    if (internalheight > adjustheight)
    {
        $(".uaCollapseThreadRead .uaExpandThreadRead").css('height', adjustheight).css('overflow', 'hidden');
        $(".uaCollapseThreadRead").css('overflow', 'hidden');


        $(".uaCollapseThreadRead").append('<span style="float: right;"><a href="#" class="adjust"></a></span>');

        $("a.adjust").text(moreText);
    }

    $(".adjust").toggle(function() {
            $(this).parents("div:first").find(".uaExpandThreadRead").css('height', 'auto').css('overflow', 'visible');
            $(this).text(lessText);
        }, function() {
            $(this).parents("div:first").find(".uaExpandThreadRead").css('height', adjustheight).css('overflow', 'hidden');
            $(this).text(moreText);
        });
});