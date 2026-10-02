/*
Copyright 2019 Fleming Computer.
All Rights Reserved.
*/

function Tiles() {
    this.doScrollPosition();
    if ($(".store-items").length > 0)
        this.doShowTiles();
}

Tiles.prototype.doScrollPosition = function() {
    var uri = location.pathname;
    if (sessionStorage.getItem(uri + "-windowTop") != null) {
        $(window).scrollTop(sessionStorage.getItem(uri + "-windowTop"));
        sessionStorage.removeItem(uri + "-windowTop");
    }
    window.onbeforeunload = function() {
        sessionStorage.setItem(uri + "-windowTop", $(window).scrollTop());
    }
}

Tiles.prototype.doShowTiles = function() {
    // Do smart sizing of item tiles.
    var borderWidth = parseInt($(".store-items li .item-content").first().css("border-left-width"));
    var marginW = parseInt($(".store-items li").first().css("margin-left"));
    var padding = parseInt($(".store-items li .item-content").first().css("padding-left"));
    var columns = parseInt($(".store-items").css("column-gap"));
    if (isNaN(columns)) columns = 4;
    var parentWidth = parseInt($(".store-items").parent().css("width"));
    var liW = Math.floor((parentWidth - 1) / columns) - 2 * marginW;
    $(".store-items li").css("width", liW + "px");
    // We don't know description <div> heights before page load. So normalize
    // the <li> heights based on max <img>+<div> height plus border+padding.
    var imgW = liW - 2 * (borderWidth + padding);
    var maxH = Math.max.apply(null, $(".store-items li").map(function() {
        var w = parseInt($(this).find(".item-image").attr("data-width"));
        var h = parseInt($(this).find(".item-image").attr("data-height"));
        var dh = parseInt($(this).find(".item-description").css("height"));
        return Math.ceil(imgW * h / w) + dh + 1;
    }).get());
    $(".store-items li").css("height", maxH + 2 * (borderWidth + padding) + "px");
    function resizeTiles() {
        var columns = parseInt($(".store-items").css("column-gap"));
        if (isNaN(columns)) columns = 4;
        var parentWidth = parseInt($(".store-items").parent().css("width"));
        var liW = Math.floor((parentWidth - 1) / columns) - 2 * marginW;
        $(".store-items li").css("width", liW + "px");
        var maxH = Math.max.apply(null, $(".store-items li").map(function() {
            var ih = parseInt($(this).find(".item-image").css("height"));
            var dh = parseInt($(this).find(".item-description").css("height"));
            return ih + dh + 1;
        }).get());
        $(".store-items li").css("height", maxH + 2 * (borderWidth + padding) + "px");
    }
    $(window).on('load', function() { resizeTiles(); });
    $(window).resize(resizeTiles);
}

$(function() {
    var tiles = new Tiles();
});
