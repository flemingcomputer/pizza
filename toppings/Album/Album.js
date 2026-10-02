/*
Copyright 2018 Fleming Computer.
All Rights Reserved.
*/

function Album() {
    var self = this;
    if ($(".album-files").length > 0)
        this.doShowAlbumEditor();
    // If editing...
    if ($("#album-editor").length) {
        this.initEditorState();
    }
    // Save the editor state when clicking a non-done button.
    $("input[type=submit][name!=done]").click(function() {
        self.saveEditorState();
    });
    // Clear the editor state when clicking "done".
    $("input[type=submit][name=done]").click(function() {
        self.clearEditorState();
    });
    // Confirm image delete.
    $(".album-delete img").click(function() {
        if (confirm("Really delete?")) {
            var fileName = $(this).attr("data-file");
            $("#album-form").append($("<input>")
                .attr("type", "hidden")
                .attr("name", "delete")
                .attr("value", fileName)
            );
            $("#album-form").submit();
            return true;
        }
        return false;
    });
    // Show "Save" or "Done" editor button depending on state.
    this.oldCaptions = $("textarea[name^=caption]").map(function() {
        return $(this).val();
    });
    $("textarea[name^=caption]").on("input propertychange", function() {
        self.showSaveOrDone();
    });
    $("input:file[name^=files]").change(function() {
        self.showSaveOrDone();
    });
    // Also set Save Or Done button at page load.
    this.showSaveOrDone();
}

Album.prototype.clearEditorState = function() {
    sessionStorage.removeItem("window-editor-top");
}

Album.prototype.doShowAlbumEditor = function () {
    // Do smart sizing of tiles.
    var borderWidth = parseInt($(".album-files li").first().css("border-left-width"));
    var marginW = parseInt($(".album-files li").first().css("margin-left"));
    var padding = parseInt($(".album-files li").first().css("padding-left"));
    var textareaH = parseInt($(".album-files textarea").first().css("height"));
    function resizeTiles() {
        var columns = parseInt($(".album-files").css("column-gap"));
        if (isNaN(columns)) columns = 4;
        var parentWidth = parseInt($(".album-files").parent().css("width"));
        var liW = Math.floor((parentWidth - 1) / columns) - 2 * marginW;
        $(".album-files li").css("width", liW + "px");
        // Set <li> heights to be the same, resize <img>s within a bounding box.
        var imgBox = liW - 2 * (borderWidth + padding);
        var liH = imgBox + textareaH + 2 * (borderWidth + padding) + 1;
        $(".album-files li").css("height", liH + "px");
        $(".album-files li").each(function() {
            var i = $(this).find("img").first();
            var counterDiv = $(this).find(".album-counter");
            var deleteDiv = $(this).find(".album-delete");
            var handleDiv = $(this).find(".sort-handle");
            var w = parseInt(i.attr("data-width"));
            var h = parseInt(i.attr("data-height"));
            if (w > h) {
                h = Math.ceil(imgBox * h / w);
                w = imgBox;
            }
            else {
                w = Math.ceil(imgBox * w / h);
                h = imgBox;
            }
            i.css("width", w + "px");
            i.css("height", h + "px");
            i.css("margin-top", imgBox - h + "px");
            counterDiv.css("left", Math.ceil((imgBox - w) / 2) + 1 + "px");
            counterDiv.css("bottom", textareaH + "px");
            deleteDiv.css("right", Math.ceil((imgBox - w) / 2) + 1 + "px");
            deleteDiv.css("top", imgBox - h + 1 + "px");
            handleDiv.css("left", Math.ceil((imgBox - w) / 2) + 1 + "px");
            handleDiv.css("top", imgBox - h + 1 + "px");
        });
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
            $(".album-delete").show();
            $(".album-files").sortable({
                tolerance: "pointer",
                handle: ".sort-handle",
                update: function(event, ui) {
                    $("#gray-out").show();
                    $(".album-files").sortable("disable");
                    var order = [];
                    $(".album-files li").each(function() {
                        order.push($(this).attr("data-index"));
                    });
                    var positions = order.join("-")
                    $.get(urlRoot + "/ajax/album-sort-files",
                        {pagePath: pagePath, order: positions},
                        function(data) {
                            // Renumber the <li>s from zero.
                            var n = $(".album-files li").length;
                            $(".album-files li").each(function(index) {
                                $(this).attr("data-index", index);
                                $(this).find(".album-counter").text(index + 1 + " of " + n);
                            });
                            $(".album-files").sortable("enable");
                            $("#gray-out").hide();
                        }
                    );
                }
            });
        }
    );
}

Album.prototype.initEditorState = function() {
    // Restore window scroll top.
    if (sessionStorage.getItem("window-editor-top") != null)
        $(window).scrollTop(sessionStorage.getItem("window-editor-top"));
}

Album.prototype.saveEditorState = function() {
    // Save window position.
    sessionStorage.setItem("window-editor-top", $(window).scrollTop());
}

Album.prototype.showSaveOrDone = function() {
    var saveButton = $(":submit[name=save]");
    var doneButton = $(":submit[name=done]");
    var newCaptions = $("textarea[name^=caption]").map(function() {
        return $(this).val();
    });
    var hasFiles = $("input[type=file][name^=files]")[0].files.length > 0;
    if ((JSON.stringify(this.oldCaptions) != JSON.stringify(newCaptions))
        || hasFiles) {
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

$(function() {
    var album = new Album();
});
