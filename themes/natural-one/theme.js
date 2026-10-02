$(function() {
    // Highlight relevant menu link that is the page itself or a subpage.
    var links = $(".header-menu a").toArray().sort().reverse();
    $(links).each(function(i){
        // Highlight nothing if just a subpage of root.
        if (location.pathname != "/" && links[i].pathname == "/") {
            return false;
        }
        // Highlight first match only.
        if (location.pathname.toLowerCase().indexOf(
            links[i].pathname.toLowerCase()) >= 0) {
            $(this).addClass("header-menu-current");
            return false;
        }
    })
});

$(function() {
    // Slide header menu on and off screen based on scroll direction.
    var height = parseInt($(".header-menu").css("height"));
    var top = parseInt($(".header-menu").css("top"));
    var threshold = 3 * height;
    var animate = false;
    var sDir = "up";
    var sNew = 0;
    var sOld;
    var tDir = "up";
    var tStart = 0;
    var tEnd;
    var t;
    $(window).scroll(function(e) {
        sOld = sNew;
        sNew = $(this).scrollTop();
        if (!animate) {
            // Set threshold start when scroll direction changes.
            if (sNew > sOld && sDir == "up") {
                sDir = "down";
                tStart = sNew;
            } else if (sNew <= sOld && sDir == "down") {
                sDir = "up";
                tStart = sNew;
            }
            // Set threshold end and direction when theshold crossed.
            if (tDir == "up" && sNew - tStart > threshold) {
                tDir = "down";
                tEnd = sNew;
                animate = true;
            } else if (tDir == "down" && tStart - sNew > threshold) {
                tDir = "up";
                tEnd = sNew;
                animate = true;
            }
        } else {  // animate
            if (tDir == "down") t = top + tEnd - sNew;
            else t = tEnd - sNew - height;
            if (t < -height) {
                t = -height;
                tDir = "down";
                animate = false;
            } else if (t > top) {
                t = top;
                tDir = "up";
                animate = false;
            }
            $(".header-menu").css("top", t + "px");
        }
    });
});
