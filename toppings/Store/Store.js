/*
Copyright 2022 Fleming Computer.
All Rights Reserved.
*/

function Store() {
    // var self = this;
    this.doScrollPosition();
    if ($("#add-to-cart").length > 0)
        this.doShowItem();
    else if ($(".store-items").length > 0)
        this.doShowItems();
}

Store.prototype.doScrollPosition = function() {
    var uri = window.location.pathname;
    if (sessionStorage.getItem(uri + "-windowTop") != null) {
        $(window).scrollTop(sessionStorage.getItem(uri + "-windowTop"));
        sessionStorage.removeItem(uri + "-windowTop");
    }
    window.onbeforeunload = function() {
        sessionStorage.setItem(uri + "-windowTop", $(window).scrollTop());
    }
}

Store.prototype.doShowItem = function() {
    // Display price or range above select.
    var prices = $("#add-to-cart").attr("data-prices").split(",");
    var options = $("#add-to-cart").attr("data-options").split(",");
    var href = $("#add-to-cart a").attr("href");
    $("#select-option").change(function() {
        $("#select-option option[value='blank']").remove();
        var selected = $("#select-option option:selected");
        var price = prices[selected.val()];
        var option = options[selected.val()];
        $("#item-price").text("$" + price);
        $("#add-to-cart a").attr("href", href + "&option=" + encodeURIComponent(option));
        $("#add-to-cart a").show();
    });

    // Only continue if the images are being shown using my editable feature.
    if ($(".store-item-images").length == 0) return;

    // Do smart sizing of images in horizontal scroll area.
    var initialImgWidth = parseInt($(".store-item-images").attr("data-initial-img-width"));
    var borderWidth = parseInt($(".store-item-images li").first().css("border-left-width"));
    var padding = parseInt($(".store-item-images li").first().css("padding-left"));
    var borderSpacing = parseInt($(".store-item-images div").eq(0).css("border-spacing"));
    var numTiles = $(".store-item-images li").length;
    function resizeTiles() {
        var imgW = parseInt($(".store-item-images").css("column-gap"));
        if (isNaN(imgW)) imgW = initialImgWidth;
        var parentWidth = parseInt($(".store-item-images").css("width"));
        var tileW = imgW + borderSpacing + 2 * (borderWidth + padding);
        // Adjust tile widths so that 1/3 of a tile is showing on the
        // right side if all the tiles don't fit naturally. Example:
        //     numTiles = 6 tiles
        //     tileQ = 5.01 to 5.99 (or even 42.17 if parentWidth is huge)
        //     numTiles > tileQ ? adjust tiles:
        //         q = 5
        //         (numTiles > 6) && (tileQ >= 5.75) ? q = 6
        //         q += 0.33
        //         tileW = int(parentWidth / q)
        var tileQ = parentWidth / tileW;
        if (numTiles > tileQ) {
            var q = parseInt(tileQ);
            if ((numTiles > q + 1) && (tileQ >= q + 0.75))
                q += 1;
            q += 0.33;
            tileW = parseInt(parentWidth / q);
        }
        imgW = tileW - borderSpacing - 2 * (borderWidth + padding);
        // Set all tile images to the new width.
        $(".store-item-images li").find(".item-image").css("width", imgW + "px");
    }
    resizeTiles();
    $(window).resize(resizeTiles);

    // If page is writable, do the sortable stuff.
    $.get(urlRoot + "/ajax/is-writable",
        {pagePath: pagePath},
        function(data) {
            var json = JSON.parse(data);
            if (json["isWritable"] !== true) return false;
            $(".sort-handle").show();
            $(".store-item-images ul").sortable({
                handle: ".sort-handle",
                update: function(event, ui) {
                    $(".wait").show();
                    $(".store-item-images ul").sortable("disable");
                    var order = [];
                    $(".store-item-images li").each(function() {
                        order.push($(this).attr("data-index"));
                    });
                    var positions = order.join("-");
                    $.get(urlRoot + "/ajax/store-sort-files",
                        {pagePath: pagePath, order: positions},
                        function(data) {
                            // Renumber the <li>s from zero.
                            $(".store-item-images li").each(function(index) {
                                $(this).attr("data-index", index);
                            });
                            $(".store-item-images ul").sortable("enable");
                            $(".wait").hide();
                        }
                    );
                }
            });
        }
    );
}

Store.prototype.doShowItems = function() {
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

    // If page is writable, do the sortable stuff.
    $.get(urlRoot + "/ajax/is-writable",
        {pagePath: pagePath},
        function(data) {
            var json = JSON.parse(data);
            if (json["isWritable"] !== true) return false;
            $(".sort-handle").show();
            $(".store-items").sortable({
                tolerance: "pointer",
                handle: ".sort-handle",
                update: function(event, ui) {
                    $(".wait").fadeIn();
                    $(".store-items").sortable("disable");
                    var order = [];
                    $(".store-items li").each(function() {
                        order.push($(this).attr("data-index"));
                    });
                    var positions = order.join("-");
                    $.get(urlRoot + "/ajax/store-sort-items",
                        {pagePath: pagePath, order: positions},
                        function(data) {
                            // Renumber the <li>s from zero.
                            $(".store-items li").each(function(index) {
                                $(this).attr("data-index", index);
                            });
                            $(".store-items").sortable("enable");
                            $(".wait").hide();
                        }
                    );
                }
            });
        }
    );
}

$(function() {
    var store = new Store();
});
