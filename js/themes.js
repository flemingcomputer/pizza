/*
Copyright 2024 Fleming Computer.
All Rights Reserved.
*/

(function($) {$.fn.pizzaThemes = function() {
///// BEGIN PLUG-IN /////

function clearEditorState() {
    sessionStorage.removeItem(id + "-windowTop");
    sessionStorage.removeItem(id + "-activeTab");
    sessionStorage.removeItem(id + "-theme_css");
    sessionStorage.removeItem(id + "-theme_html");
    sessionStorage.removeItem(id + "-theme_js");
}

function dumpSessionStorage() {
    var s = "";
    for (var i = 0; i < sessionStorage.length; i++) {
        s = s + sessionStorage.key(i) + ": "
            + sessionStorage.getItem(sessionStorage.key(i)) + "\n";
    }
    alert(s);
}

// For getting cursor position in textarea and text inputs.
function getCursorPos(el) {
    var ta = el.get(0); // Get textarea DOM from jQuery object.
    var start = 0;
    var end = 0;
    if ("selectionStart" in ta) {
        start = ta.selectionStart;
        if ("selectionEnd" in ta) {
            end = ta.selectionEnd;
        }
    }
    else if (document.selection && document.selection.createRange)
    {
        var range = document.selection.createRange();
        start = 0 - range.duplicate().moveStart("character", -100000);
        end = start + range.text.length;
    }
    return {start: start, end: end};
}

function initEditorState() {
    // Set initial values if not defined.
    if (sessionStorage.getItem(id + "-activeTab") == null)
        sessionStorage.setItem(id + "-activeTab", 0);
    if (sessionStorage.getItem(id + "-theme_css") == null)
        sessionStorage.setItem(id + "-theme_css", "0,0,0");
    if (sessionStorage.getItem(id + "-theme_html") == null)
        sessionStorage.setItem(id + "-theme_html", "0,0,0");
    if (sessionStorage.getItem(id + "-theme_js") == null)
        sessionStorage.setItem(id + "-theme_js", "0,0,0");
    // Restore window scroll top.
    if (sessionStorage.getItem(id + "-windowTop") != null)
        $(window).scrollTop(sessionStorage.getItem(id + "-windowTop"));
    // Restore active tab.
    var activeTab = sessionStorage.getItem(id + "-activeTab");
    // Tabs' "beforeActivate" and "activate" events will fire whether a
    // user or program switches tabs.  I only want to do work when a user
    // switches tabs, not here when restoring state.
    $("#tabs").off("tabsbeforeactivate");
    $("#tabs").off("tabsactivate");
    $("#tabs").tabs("option", "active", activeTab);
    $("#tabs").on("tabsbeforeactivate", tabsBeforeActivate);
    $("#tabs").on("tabsactivate", tabsActivate);
    var textArea = $("textarea[name^=theme_]").eq(activeTab).attr("name");
    if (textArea == null) return;
    // Restore textarea cursor and scroll bar positions.
    var a = sessionStorage.getItem(id + "-" + textArea).split(",");
    setCursorPos($(`textarea[name=${textArea}]`), a[0], a[1]);
    $(`textarea[name=${textArea}]`).scrollTop(a[2]);
}

function saveEditorState() {
    // Save window position.
    sessionStorage.setItem(id + "-windowTop", $(window).scrollTop());
    var activeTab = $("#tabs").tabs("option", "active");
    var textArea = $("textarea[name^=theme_]").eq(activeTab).attr("name");
    if (textArea == null) return;
    // Save textarea cursor and scroll bar positions.
    var c = getCursorPos($(`textarea[name=${textArea}]`));
    var e = $(`textarea[name=${textArea}]`).scrollTop();
    var coord = c.start + "," + c.end + "," + e;
    sessionStorage.setItem(id + "-" + textArea, coord);
}

// For setting cursor position in textarea and text inputs.
function setCursorPos(el, start, end) {
    var ta = el.get(0); // Get textarea DOM from jQuery object.
    if (ta.setSelectionRange) {
        ta.focus();
        ta.setSelectionRange(start, end);
    }
    else if (ta.createTextRange) {
        var range = ta.createTextRange();
        range.collapse(true);
        range.moveEnd("character", end);
        range.moveStart("character", start);
        range.select();
    }
}

// After switching to a tab, restore session state.
function tabsActivate(event, ui) {
    // Update the active tab index.
    var activeTab = $("#tabs").tabs("option", "active");
    sessionStorage.setItem(id + "-activeTab", activeTab);
    // After switching to a textarea tab, reposition cursor and scrollbar.
    var textArea = ui.newPanel.attr("id").substring(4);  // e.g. "tab-theme_css" is "theme_css"
    if (textArea.substring(0,6) != "theme_") return true;  // This is not a textarea.
    var a = sessionStorage.getItem(id + "-" + textArea).split(",");
    setCursorPos($(`textarea[name=${textArea}]`), a[0], a[1]);
    $(`textarea[name=${textArea}]`).scrollTop(a[2]);
}

// Before switching from a textarea tab, save cursor and scrollbar.
function tabsBeforeActivate(event, ui) {
    var textArea = ui.oldPanel.attr("id").substring(4);  // e.g. "tab-theme_css" is "theme_css"
    if (textArea.substring(0,6) != "theme_") return true;  // This is not a textarea.
    var c = getCursorPos($(`textarea[name=${textArea}]`));
    var e = $(`textarea[name=${textArea}]`).scrollTop();
    var coord = c.start + "," + c.end + "," + e;
    sessionStorage.setItem(id + "-" + textArea, coord);
}

function updateEditorButtons() {
    var saveButton = $(":submit[name^=save]");
    var doneButton = $(":submit[name=done]");
    var newCss = $("textarea[name=theme_css]").val();
    var newHtml = $("textarea[name=theme_html]").val();
    var newJs = $("textarea[name=theme_js]").val();
    if ((oldCss != newCss) || (oldHtml != newHtml) || (oldJs != newJs)) {
        saveButton.removeAttr("disabled");
        saveButton.removeClass("input-disabled");
        doneButton.val("Cancel");
    }
    else {
        saveButton.attr("disabled", "disabled");
        saveButton.addClass("input-disabled");
        doneButton.val("Done");
    }
}

var id = this.attr("id");

// Clear editor state if back to theme listing.
if ($("#theme-list").length) {
    clearEditorState();
}

// If "save copy" button, clear the editor state.
$(":submit[name=saveCopy]").click(function() {
    clearEditorState();
});

// Confirm theme delete.
$(":submit[name^=delete]").click(function() {
    var theme = $(this).attr("name").slice(7, -1);
    if (confirm("Really delete \"" + theme + "\"?")) return true;
    return false;
});

// Update editor buttons upon text changing.
var oldCss = $("textarea[name=theme_css]").val();
var oldHtml = $("textarea[name=theme_html]").val();
var oldJs = $("textarea[name=theme_js]").val();
$("textarea[name^=theme_]").on("input propertychange", function() {
    updateEditorButtons();
});

// If editing...
if ($("#tabs").length) {
    $("#tabs").tabs();
    initEditorState();
    updateEditorButtons();
}

// When leaving the editor page, save editor state.
window.onbeforeunload = function() {
    if ($("#tabs").length)
        saveEditorState();
}

// dumpSessionStorage();

return this;

///// END PLUG-IN /////
}}(jQuery));
