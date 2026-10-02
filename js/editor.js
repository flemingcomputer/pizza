/*
Copyright 2024 Fleming Computer.
All Rights Reserved.
*/

(function($) {$.fn.pizzaEditor = function() {
///// BEGIN PLUG-IN /////

var id = this.attr("id");
var sid = id + "-" + window.location.pathname + "-";
var body = "textarea[name=body]";
var ogTitle = "input[name=ogTitle]";
var ogDescription = "textarea[name=ogDescription]";
var ogImage = "input[name=ogImage]";

// Activate the tabs.
$(`#${id}`).tabs();

function getCursorPos() {
    var el = $(body).get(0);
    var start = 0;
    var end = 0;
    if ('selectionStart' in el) {
        start = el.selectionStart;
        if ('selectionEnd' in el) {
            end = el.selectionEnd;
        }
    }
    else if (document.selection && document.selection.createRange)
    {
        var range = document.selection.createRange();
        start = 0 - range.duplicate().moveStart('character', -100000);
        end = start + range.text.length;
    }
    return {start: start, end: end};
}

function setCursorPos(start, end) {
    var el = $(body).get(0);
    if (el.setSelectionRange) {
        el.focus();
        el.setSelectionRange(start, end);
    }
    else if (el.createTextRange) {
        var range = el.createTextRange();
        range.collapse(true);
        range.moveEnd('character', end);
        range.moveStart('character', start);
        range.select();
    }
}

// After switching to a tab, restore session state.
function tabsActivate(event, ui) {
    // Update the active tab index.
    var t = $(`#${id}`).tabs("option", "active");
    sessionStorage.setItem(sid + "activeTab", t);
    if (t == 0)
    {
        var pos = sessionStorage.getItem(sid + "editorPos").split(",");
        setCursorPos(pos[0], pos[1]);
        $(body).scrollTop(pos[2]);
    }
}

// Before switching from the editor tab, save cursor and scrollbar.
function tabsBeforeActivate(event, ui) {
    if (ui.oldPanel.attr("id") == "editor-tab-body")
    {
        var cur = getCursorPos();
        var top = $(body).scrollTop();
        var pos = cur.start + "," + cur.end + "," + top;
        sessionStorage.setItem(sid + "editorPos", pos);
    }
}

function updateControls() {
    var saveBody = $("#editor-tab-body input[type=submit][name=save]");
    var previewBody = $("#editor-tab-body input[type=submit][name=preview]");
    var doneBody = $("#editor-tab-body input[type=submit][name=done]");
    var saveOg = $("#editor-tab-sharing input[type=submit][name=saveSharing]");
    var doneOg = $("#editor-tab-sharing input[type=submit][name=done]");
    var doneFiles = $("#editor-tab-files input[type=submit][name=done]");
    var newBody = $(body).val();
    var newTitle = $(ogTitle).val();
    var newDescription = $(ogDescription).val();
    var newImage = $(ogImage).val();
    if ((newBody != origBody)
        || (newTitle != origTitle) || (newDescription != origDescription) || (newImage != origImage)) {
        saveBody.show();
        previewBody.show();
        doneBody.val("Cancel");
        saveOg.show();
        doneOg.val("Cancel");
        doneFiles.hide();
    }
    else {
        saveBody.hide();
        previewBody.hide();
        doneBody.val("Done");
        saveOg.hide();
        doneOg.val("Done");
        doneFiles.show();
    }
}

// When navigating away from editor page, save editor state.
$(window).on("beforeunload", function() {
    // Save window position.
    sessionStorage.setItem(sid + "windowTop", $(window).scrollTop());
    var t = $(`#${id}`).tabs("option", "active");
    // scrollTop only works properly if body tab is active.
    if (t == 0)
    {
        // Save textarea cursor and scroll bar positions.
        var cur = getCursorPos();
        var top = $(body).scrollTop();
        var pos = cur.start + "," + cur.end + "," + top;
        sessionStorage.setItem(sid + "editorPos", pos);
    }
    // Save textarea contents.
    sessionStorage.setItem(sid + "body", $(body).val());
    // Save OpenGraph values.
    sessionStorage.setItem(sid + "og-title", $(ogTitle).val());
    sessionStorage.setItem(sid + "og-description", $(ogDescription).val());
    sessionStorage.setItem(sid + "og-image", $(ogImage).val());
});

// Clear editor state when done.
$("input[type=submit][name^=save],input[type=submit][name=done]").on("click", function(e) {
    $(window).off('beforeunload');
    // Only clear editor state if body's save was clicked.
    if ($(this).attr("name") != "saveSharing") {
        sessionStorage.removeItem(sid + "windowTop");
        sessionStorage.removeItem(sid + "activeTab");
        sessionStorage.removeItem(sid + "editorPos");
    }
    sessionStorage.removeItem(sid + "origBody");
    sessionStorage.removeItem(sid + "origTitle");
    sessionStorage.removeItem(sid + "origDescription");
    sessionStorage.removeItem(sid + "origImage");
    sessionStorage.removeItem(sid + "body");
    sessionStorage.removeItem(sid + "og-title");
    sessionStorage.removeItem(sid + "og-description");
    sessionStorage.removeItem(sid + "og-image");
});

// Update form buttons when text changes.
$(`${body}, ${ogTitle}, ${ogDescription}, ${ogImage}`).on("input propertychange", function() {
    updateControls();
});

// Set initial state.
if (sessionStorage.getItem(sid + "activeTab") == null)
    sessionStorage.setItem(sid + "activeTab", 0);
if (sessionStorage.getItem(sid + "editorPos") == null)
    sessionStorage.setItem(sid + "editorPos", "0,0,0");
if (sessionStorage.getItem(sid + "origBody") == null)
    sessionStorage.setItem(sid + "origBody", $(body).val());
if (sessionStorage.getItem(sid + "origTitle") == null)
    sessionStorage.setItem(sid + "origTitle", $(ogTitle).val());
if (sessionStorage.getItem(sid + "origDescription") == null)
    sessionStorage.setItem(sid + "origDescription", $(ogDescription).val());
if (sessionStorage.getItem(sid + "origImage") == null)
    sessionStorage.setItem(sid + "origImage", $(ogImage).val());
var origBody = sessionStorage.getItem(sid + "origBody");
var origTitle = sessionStorage.getItem(sid + "origTitle");
var origDescription = sessionStorage.getItem(sid + "origDescription");
var origImage = sessionStorage.getItem(sid + "origImage");
// Restore any saved editor contents.
if (sessionStorage.getItem(sid + "body") != null)
    $(body).val(sessionStorage.getItem(sid + "body"));
if (sessionStorage.getItem(sid + "og-title") != null)
    $(ogTitle).val(sessionStorage.getItem(sid + "og-title"));
if (sessionStorage.getItem(sid + "og-description") != null)
    $(ogDescription).val(sessionStorage.getItem(sid + "og-description"));
if (sessionStorage.getItem(sid + "og-image") != null)
    $(ogImage).val(sessionStorage.getItem(sid + "og-image"));
// Restore window scroll top.
if (sessionStorage.getItem(sid + "windowTop") != null)
    $(window).scrollTop(sessionStorage.getItem(sid + "windowTop"));
// Restore active tab.
var t = sessionStorage.getItem(sid + "activeTab");
// Tabs' "beforeActivate" and "activate" events will fire whether a
// user or program switches tabs.  I only want to do work when a user
// switches tabs, not here when restoring state.
$(`#${id}`).off("tabsbeforeactivate");
$(`#${id}`).off("tabsactivate");
$(`#${id}`).tabs("option", "active", t);
$(`#${id}`).on("tabsbeforeactivate", tabsBeforeActivate);
$(`#${id}`).on("tabsactivate", tabsActivate);
if (t == 0) {
    // Restore textarea cursor and scroll bar positions.
    var pos = sessionStorage.getItem(sid + "editorPos").split(",");
    setCursorPos(pos[0], pos[1]);
    $(body).scrollTop(pos[2]);
}

// Update controls at page load.
updateControls();

return this;

///// END PLUG-IN /////
}}(jQuery));
