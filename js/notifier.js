/*
Copyright 2022 Fleming Computer.
All Rights Reserved.
*/

(function($) {$.fn.pizzaNotifier = function(options) {
///// BEGIN PLUG-IN /////

var settings = $.extend({
    handler: "",
    path: pagePath
}, options);
settings.handler = settings.handler.trim();

if (!settings.handler) {
    alert("Notifier: handler not set");
    return false;
}

var notifier = this;
var id = $(notifier).attr("id");

var bodyBgColor = $("body").css("background-color");
var rgb = bodyBgColor.match(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/);
var r = Math.min(parseInt(rgb[1]) * 1, 255);
var g = Math.min(parseInt(rgb[2]) * 1, 255);
var b = Math.min(parseInt(rgb[3]) * 1, 255);
var bodyBgRGBA = "rgba(" + r + ", " + g + ", " + b + ", " + "0.9)";
var bodyFontColor = $("body").css("color");
var aColor = $("a:not([class])").eq(0).css("color");
var style=`
<style id="${id}-style">
#${id} {
    display: block;
    position: fixed;
    z-index: 5;
    padding-top: 60px;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: ${bodyBgRGBA};
}

.modal-content {
    background-color: ${bodyBgColor};
    border-color: ${bodyFontColor};
    border-style: solid;
    border-width: 2px;
    border-radius: 10px;
    margin: auto;
    padding: 20px;
    width: 90%;
}

.close {
    color: ${aColor};
    float: right;
    font-size: 28px;
    font-weight: bold;
}

.close:hover,
.close:focus {
    color: ${aColor};
    text-decoration: none;
    cursor: pointer;
}

.preview {
    margin-top: 1em;
}

.preview iframe {
    border-color: ${bodyFontColor};
    border-style: solid;
    border-width: 1px;
    overflow: hidden;
    scale: 0.5;
    transform-origin: top center;
    width: 100%;
}

.select-theme {
    margin-right: 1em;
}

.alt-url, .test-emails {
    margin: 0 2em 0 0;
    padding: 0;
}

.test-link {
    margin-right: 0.5em;
}

@media only screen and (max-width: 600px) {
    .modal-content {
        border: 0;
        padding: 0;
    }
    .preview iframe {
        scale: 1;
    }
}
</style>
`;
if (!$(`#${id}-style`).length)
    $(style).appendTo("head");

var modal = `
<div class="modal-content">
    <span class="close">&times;</span>
</div>
`;

var loading = `<span class="loading">loading...</span>`;
var selectTheme = `<select class="select-theme"></select>`;
var testLink = `<a class="test-link" href="#0">send test to</a>`;
var testEmails = `<input class="test-emails" type="text"/>`;
var testSent = `<span class="test-sent">test sent</span>`;
var allLink = `<a class="all-link" href="#0">send to subscribed</a>`;
var allSent = `<span class="all-sent">sent to all</span>`;
var preview = `<div class="preview"><iframe scrolling="no"></iframe></div>`;
var altUrl = `<input class="alt-url" placeholder="Alt Target URL" type="text"/>`;

$(notifier).append(modal);
modal = $(".modal-content", notifier);
$(modal).append(loading);
$(modal).append(selectTheme);
$(modal).append(testLink);
$(modal).append(testEmails);
$(modal).append(altUrl);
$(modal).append(testSent);
$(modal).append(allLink);
$(modal).append(allSent);
$(modal).append(preview);

$("#pizza-bar").hide();
$(".select-theme", notifier).hide();
$(".test-link", notifier).hide();
$(".test-emails", notifier).hide();
$(".alt-url", notifier).hide();
$(".test-sent", notifier).hide();
$(".all-link", notifier).hide();
$(".all-sent", notifier).hide();
$(".preview", notifier).hide();

// Load available email themes into select element.
$.post(settings.handler, {path: settings.path, mode: "initialize"}, function(data) {
    if (!data["result"]) return false;
    if (Object.keys(data["themes"]).length == 1)
    {
        $(".select-theme", notifier).append(`<option value="0" selected="selected">${data["themes"][0]}</option>`);
        $(".select-theme", notifier).trigger("change");
    }
    else
    {
        $(".select-theme", notifier).append(`<option value="blank">Select Theme</option>`);
        $.each(data["themes"], function(k, v) {
            $(".select-theme", notifier).append(`<option value="${k}">${v}</option>`);
        });
    }
    $(".loading", notifier).hide();
    $(".select-theme", notifier).show();
    $(".test-emails", notifier).val(data["test-emails"]);
    $(".test-emails", notifier).trigger("input");
});

$(".select-theme", notifier).on("change", function(e) {
    if ($(this).val() == "blank") return false;
    $(`option[value="blank"]`, this).remove();
    $(".test-link", notifier).show();
    $(".test-emails", notifier).show();
    $(".alt-url", notifier).show();
    $(".preview", notifier).show();
    $(".preview iframe", notifier).on("load", function() {
        // Resize iframe to 50% width.
        $(this).css("height", this.contentWindow.document.body.offsetHeight + "px");
        // Container must be scaled likewise.
        var s = $(this).css("scale");
        $(this).parent().css("height", $(this).height() * s + "px");
        // Or, resize and scale to fit browser window.
        // // First, resize iframe to hold full contents.
        // $(this).css("height", this.contentWindow.document.body.offsetHeight + "px");
        // // Next, scale iframe to fit browser window.
        // var padding = parseInt($(".modal-content").css("padding-top"))
        //     + parseInt($(".modal-content").css("padding-bottom"));
        // var s = ($(window).height() - $(this).offset().top - padding) / $(this).height();
        // $(this).css("scale", s.toString());
        // // Container must be scaled likewise.
        // $(this).parent().css("height", $(this).height() * s + "px");
    });
    var theme = $("option:selected", this).text();
    $(".preview iframe", notifier).attr("src", urlRoot + pagePath + "?emailTheme=" + theme);
});

// Resize iframe (if has src) on window resize.
$(window).resize(function() {
    var f = $(".preview iframe", notifier);
    if (typeof f.attr("src") === "undefined" || f.attr("src") === false) return;
    f.css("height", f[0].contentWindow.document.body.offsetHeight + "px");
    var s = f.css("scale");
    f.parent().css("height", f.height() * s + "px");
});

$(".test-emails", notifier).on("input", function(e) {
    var t = $(this).val();
    $(notifier).append(`<span class="temp">${t}</span>`);
    var w = Math.max(80, $(".temp", notifier).width());
    $(".temp", notifier).remove();
    $(this).width(w + 8);
});

$(".test-link", notifier).on("click", function(e) {
    var theme = $(".select-theme option:selected", notifier).text();
    var emailRE = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    var testEmails = $(".test-emails", notifier).val();
    testEmails = testEmails.replace(/[,;]/g, " ") // Turn , and ; into space.
    testEmails = testEmails.replace(/\s+/g, ",") // Turn whitespace+ into ,
    var testEmailsArray = testEmails.split(",");
    testEmails = "";
    for (var i = 0; i < testEmailsArray.length; i++) {
        if (emailRE.test(testEmailsArray[i]))
            testEmails = testEmails + testEmailsArray[i] + ",";
    }
    testEmails = testEmails.substring(0, testEmails.length - 1);
    var altUrl = $(".alt-url", notifier).val();
    $.post(settings.handler, {path: settings.path, mode: "test", theme: theme, testEmails: testEmails, altUrl: altUrl},
        function(data) {
        if (!data["result"]) return false;
        $(".test-link", notifier).hide();
        $(".test-emails", notifier).hide();
        $(".alt-url", notifier).hide();
        $(".test-sent", notifier).show();
        $(".all-link", notifier).hide();
        $(".all-sent", notifier).hide();
        setTimeout(function() {
            $(".test-link", notifier).show();
            $(".test-emails", notifier).show();
            $(".alt-url", notifier).hide();
            $(".test-sent", notifier).hide();
            $(".all-link", notifier).show();
            $(".all-sent", notifier).hide();
        }, 3000);
    });
});

$(".all-link", notifier).on("click", function(e) {
    var theme = $("select option:selected", notifier).text();
    var altUrl = $(".alt-url", notifier).val();
    $.post(settings.handler, {path: settings.path, mode: "all", theme: theme, altUrl: altUrl}, function(data) {
        if (!data["result"]) return false;
        $(".test-link", notifier).hide();
        $(".test-emails", notifier).hide();
        $(".alt-url", notifier).hide();
        $(".test-sent", notifier).hide();
        $(".all-link", notifier).hide();
        $(".all-sent", notifier).show();
        setTimeout(function() {
            $(notifier).remove();
            $(`#${id}-style`).remove();
            $("#pizza-bar").show();
        }, 3000);
    });
});

$(".close", notifier).on("click", function(e) {
    $(notifier).remove();
    $(`#${id}-style`).remove();
    $("#pizza-bar").show();
});

return this;

///// END PLUG-IN /////
}}(jQuery));
