/*
Copyright 2022 Fleming Computer.
All Rights Reserved.
*/

(function($) {$.fn.pizzaFiler = function(options) {
///// BEGIN PLUG-IN /////

var settings = $.extend({
    handler: "",
    path: pagePath,
    done: function() {},
    listFiles: true,
    maxConnections: 3,
    maxFileSize: 10485760 // 10MB
}, options);
settings.handler = settings.handler.trim();

if (!settings.handler) {
    alert("Filer: handler not set");
    return false;
}
if (!Number.isInteger(settings.maxConnections) || settings.maxConnections < 1) {
    alert("Filer: maxConnections must be > 0");
    return false;
}

// Wrap in a div, taking over the id.
var id = this.attr("id");
this.attr("id", `${id}-input`);
this.wrap(`<div id="${id}"></div>`);
var browseLabel = `
<label class="paperclip" for="${id}-input">
<svg xmlns="http://www.w3.org/2000/svg"
width="32" height="64" viewBox="0 0 32 64">
<g transform="matrix(1.1885748,0,0,1.1486826,-37.68733,-21.387321)">
<path d="m 39.132,31.878 v 29.144 c 0,3.41 2.774,6.184 6.185,6.184 3.411,0 6.184,-2.773 6.184,-6.184 V 27.955 h -0.028 c -0.293,-5.195 -4.601,-9.336 -9.87,-9.336 -5.268,0 -9.574,4.141 -9.867,9.336 h -0.028 v 32.507 c 0,7.649 6.038,13.873 13.462,13.873 7.423,0 13.461,-6.224 13.461,-13.873 V 26.834 h -3.4 v 33.628 c 0,5.774 -4.514,10.474 -10.061,10.474 -5.548,0 -10.063,-4.699 -10.063,-10.474 V 28.516 c 0,-3.582 2.915,-6.496 6.496,-6.496 3.583,0 6.498,2.914 6.498,6.496 v 32.506 c 0,1.535 -1.249,2.783 -2.783,2.783 -1.535,0 -2.785,-1.248 -2.785,-2.783 V 31.878 Z" />
</g></svg>&nbsp;Attach Files
</label>
`;
// Hide default browse button, any data-id="hide" elements, and show new browse svg.
this.attr("hidden", true);
$(`[data-${id}="hide"]`).css("display", "none");
$(browseLabel).insertAfter(this);

// Get thumbnail box size by inserting temporary thumbnail and getting its css.
$(`#${id}`).append('<div class="thumbnail" style="display: none"></div>');
var boxW = parseInt($(`#${id} .thumbnail`).css("width"));
if (boxW == 0) boxW = 40;
var boxH = parseInt($(`#${id} .thumbnail`).css("height"));
if (boxH == 0) boxH = 30;
var box = Math.min(boxW, boxH);
$(`#${id} > div.thumbnail`).last().remove();

var deleteSvg = `<svg xmlns="http://www.w3.org/2000/svg"
width="20" height="24" viewBox="0 0 20 24">
<g transform="translate(-22,-20)"><g>
<path d="m 37,24 v -2 c 0,-1.104 -0.896,-2 -2,-2 h -6 c -1.104,0 -2,0.896 -2,2 v 2 h -5 v 2 h 2 v 16 c 0,1.104 0.896,2 2,2 h 12 c 1.104,0 2,-0.896 2,-2 V 26 h 2 v -2 z m -8,-2 h 6 v 2 h -6 z m 9,20 H 26 V 26 H 38 Z M 31,28 h -2 v 12 h 2 z m 4,0 h -2 v 12 h 2 z" />
</g></g></svg>`;

var cancelSvg = `<svg xmlns="http://www.w3.org/2000/svg"
width="12" height="12" viewBox="0 0 12 12">
<g transform="matrix(.1 0 0 .1 -46.8 -31.6)">
<path d="m472 435-1.91-1.08-2.11-4.08v-1.82c0-0.959 0.347-2.51 0.771-3.35l0.771-1.52 46.9-47.1-46.9-47.1-0.771-1.52c-0.424-0.839-0.771-2.34-0.771-3.35v-1.82l1.06-2.05 1.06-2.04 4.09-2.16h1.82c1 0 2.51 0.36 3.35 0.72l1.52 0.72 47.1 46.9 47.1-46.9 1.52-0.72c0.838-0.48 2.35-0.72 3.35-0.72h1.82l4.09 2.16 1.06 2.04 1.06 2.05v1.82c0 0.959-0.347 2.51-0.771 3.35l-0.771 1.52-46.9 47.1 46.9 47.1 0.771 1.52c0.424 0.839 0.771 2.34 0.771 3.35v1.82l-1.06 2.05-1.06 2.04-4.09 2.16h-1.82c-1 0-2.51-0.36-3.35-0.72l-1.52-0.72-47.1-46.9-47.1 46.9-3.05 1.44-1.96-0.0276-1.96-0.0288-1.91-1.08z"/>
</g></svg>`;

const fileRow = (i) => `
<div class="fileRow" id="${id}-row-${i}">
<div class="thumbnail"></div>
<div class="fileName"></div>
<div class="fileSize"></div>
</div>`.replace(/(\r\n|\n|\r)/gm, "");

function byteSize(bytes) {
    // Based on 1024, so we need 4 significant digits, but show no decimals
    // for K and only one decimal for larger units.
    if (bytes > 1098974756863) return Math.round((bytes / 1099511627776).toPrecision(4) * 10) / 10 + "T";
    if (bytes > 1073217535) return Math.round((bytes/ 1073741824).toPrecision(4) * 10) / 10 + "G";
    if (bytes > 1048063) return Math.round((bytes / 1048576).toPrecision(4) * 10) / 10 + "M";
    if (bytes >= 1024) return Math.round(bytes / 1024) + "K";
    return bytes + "B";
}

function deleteButton(row) {
    $(row).append(`<div class="delete">${deleteSvg}</div>`);
    $(`${row} .delete`).on("click", function(e) {
        var fileName = $(`${row} .fileName`).text();
        if (!fileName) return;
        // Allow multiple clicks/attempts to delete unless currently trying to delete.
        if ($(`${row}`).attr("data-status") == "deleting") return;
        $(`${row}`).attr("data-status", "deleting");
        $(`${row} .fileName`).css("text-decoration", "line-through");
        $(`${row}`).css("opacity", "0.35");
        $.post(settings.handler, {deleteFile: fileName, path: settings.path}, function(data) {
            if (data["result"]) $(row).remove();
            else {
                $(`${row}`).attr("data-status", "delete-failed");
                $(`${row} .fileName`).css("text-decoration", "none");
                $(`${row}`).css("opacity", "");
            }
        });
    });
}

function thumbnail(rowId, type, url) {
    // Thumbnails done client-side for uploads (blob), but fetched from server
    // for downloads (http).
    var imageTypes = ["image/gif", "image/jpeg", "image/pjpeg", "image/png", "image/svg+xml", "image/webp"];
    var isImage = $.inArray(type, imageTypes) != -1;
    var typeToClass = {
        "application/pdf": "type-pdf"
    }
    if (!$(`#${rowId} .thumbnail img`).length)
        $(`#${rowId} .thumbnail`).append("<img/>");
    var img = `#${rowId} .thumbnail img`;
    $(img).on("load", function() {
        var s = Math.min(boxW / this.width, boxH / this.height);
        var thumbW = Math.round(this.width * s);
        var thumbH = Math.round(this.height * s);
        this.width = thumbW;
        this.height = thumbH;
        $(`#${rowId} .thumbnail`).css("padding-top", parseInt((boxH - thumbH) / 2) + "px");
    });
    if (isImage) {
        if (url.slice(0,4) == "blob")
            $(img).attr("src", url);
        else
            $(img).attr("src", url + `?width=${boxW*2}&height=${boxH*2}`);
    } else {
        // Not an image, so use an appropriate thumbnail icon.
        // Append temp img element and get its url("...") from content.
        // jQuery takes care of presenting url as url("..."), so 5 to -2.
        var iconClass = typeToClass[type] ?? 'type-unknown';
        $(`#${id}`).append(`<img class="${iconClass}">`);
        var iconUrl = $(`#${id} > img`).last().css("content").slice(5, -2);
        $(`#${id} > img`).last().remove();
        if (iconUrl)
            $(img).attr("src", iconUrl + `?width=${boxW}&height=${boxH}`);
    }
}

// Upload event handler.
var files = {};
var numCalls = 0;
var uploading = false;
this.on("change", function() {
    // Construct rows of files to be uploaded with faded progress bars.
    $.each(this.files, function(i, file) {
        if (file.size > settings.maxFileSize) {
            alert(`"${file.name}" is too large.` + "\n"
                + `Max allowed size is ${byteSize(settings.maxFileSize)}.`);
            return;
        }
        files[file.name] = new File([file], file.name, { type: file.type });
        var rowId = $(`[id^="${id}-row-"][data-filename='${file.name}']`).first().attr("id");
        if (rowId) {
            // Replace in DOM (if not already being uploaded).
            if ($(`#${rowId} .cancel`).length) return;
            $(`#${rowId} .fileName`).text(file.name); // to clear link
            $(`#${rowId} .delete`).remove();
        } else {
            // Insert into DOM before first greater-than name.
            var greaterName = $(`[id^="${id}-row-"]`).filter(function() {
                return $(".fileName", this).text().toLowerCase() > file.name.toLowerCase();
            }).first();
            if (!greaterName.length) greaterName = `#${id}-input`; // at end
            // Make the new id the max + 1.
            var rowIdInts = $(`[id^="${id}-row-"]`).map(function() {
                return parseInt($(this).attr("id").match(/-(\d+)$/)[1]);
            }).get();
            var newId = rowIdInts.length > 0 ? Math.max.apply(null, rowIdInts) + 1 : 0;
            $([newId].map(fileRow).toString()).insertBefore(greaterName);
            rowId = `${id}-row-${newId}`;
            $(`#${rowId}`).attr("data-filename", file.name);
            $(`#${rowId} .fileName`).text(file.name);
        }
        $(`#${rowId} .fileSize`).text(byteSize(file.size));
        $(`#${rowId}`).append('<div class="progress"><div></div></div>')
            .append(`<div class="cancel">${cancelSvg}</div>`);
        $(`#${rowId} .progress`).addClass("progress-standby");
        $(`#${rowId}`).attr("data-status", "pending");
        // Generate thumbnail.
        var url = URL.createObjectURL(file);
        var img = new Image();
        $(img).on("load error", function() {
            // "error" happens on non-image. thumbnail() handles both cases.
            thumbnail(rowId, file.type, url);
            if (this.width && this.height)
                $(`#${rowId} .fileSize`).text(byteSize(file.size) + ", " + this.width + "x" + this.height);
        });
        img.src = url;
        // One might cancel before uploading starts.
        $(`#${rowId} .cancel`).one("click", function(e) {
            if ($(`#${rowId}`).attr("data-exists")) {
                $(`#${rowId}`).attr("data-status", "canceled");
                $(`#${rowId} .progress`).remove();
                $(`#${rowId} .cancel`).remove();
                deleteButton(`#${rowId}`);
            } else {
                $(`#${rowId}`).remove();
            }
            return false;
        });
    });
    // Prevent multiple setIntervals due to multiple browses.
    if (uploading) return;
    // Start uploading process limited to maxConnections at a time.
    uploading = true;
    this.value = null; // Chrome, Safari: must be done so subsequent same selection is a "change".
    var looper = setInterval(function() {
        if (numCalls < settings.maxConnections) {
            var rowId = $(`[id^="${id}-row-"][data-status="pending"`).first().attr("id");
            if (!rowId) {
                clearInterval(looper);
                uploading = false;
                return;
            }
            // Get file object based on fileName.
            var file = files[$(`#${rowId} .fileName`).text()];
            if (!file) {
                // Shouldn't be possible.
                $(`#${rowId}`).attr("data-status", "file-not-found");
                $(`#${rowId} .fileName`).text($(`#${rowId} .fileName`).text() + " FILE NOT FOUND");
                $(`#${rowId} .progress`).remove();
                $(`#${rowId} .cancel`).remove();
                return;
            }
            var singleForm = new FormData();
            singleForm.append("file", file);
            singleForm.append("path", settings.path);
            $.ajax({
                url: settings.handler,
                method: "POST",
                data: singleForm,
                dataType: "json",
                cache: false,
                contentType: false,
                processData: false,
                tryCount: 0,
                retryLimit: 3,
                xhr: function() {
                    var xhr = new XMLHttpRequest();
                    if (!xhr.upload) {
                        $(`#${rowId}`).attr("data-status", "no-xhr-upload");
                        $(`#${rowId} .fileName`).text($(`#${rowId} .fileName`).text() + " NO XHR UPLOAD");
                        $(`#${rowId} .progress`).remove();
                        $(`#${rowId} .cancel`).remove();
                        return xhr;
                    }
                    numCalls++;
                    $(`#${rowId}`).attr("data-status", "uploading");
                    $(`#${rowId} .progress`).removeClass("progress-standby");
                    $(`#${rowId} .progress div`).addClass("progress-active");
                    xhr.upload.addEventListener("progress", function(e) {
                        if (e.lengthComputable) {
                            $(`#${rowId} .progress-active`).css("width", Math.round(e.loaded / e.total * 100) + "%");
                        }
                    }, false);
                    $(`#${rowId} .cancel`).one("click", function(e) {
                        // Augment canceling by aborting this connection and freeing up a call.
                        if (xhr) {
                            xhr.abort();
                            xhr = null;
                            numCalls--;
                        }
                        return false;
                    });
                    return xhr;
                },
                success: function(data) {
                    numCalls--;
                    if (data["result"]) {
                        $(`#${rowId} .fileName`).wrapInner(`<a href="${data["url"]}" target="_blank"></a>`);
                        $(`#${rowId} .fileSize`).text(byteSize(data["size"]));
                        if (data["width"] && data["height"])
                            $(`#${rowId} .fileSize`).text(byteSize(data["size"]) + ", " + data["width"] + "x" + data["height"]);
                        $(`#${rowId}`).attr("data-status", "success");
                        $(`#${rowId}`).attr("data-exists", true);
                        $(`#${rowId} .progress`).remove();
                        $(`#${rowId} .cancel`).remove();
                        deleteButton(`#${rowId}`);
                        // Invoke callback when all is done.
                        var t1 = $(`[id^="${id}-row-"][data-status="pending"`);
                        var t2 = $(`[id^="${id}-row-"][data-status="uploading"`);
                        if (!t1.length && !t2.length) {
                            settings.done.call(this);
                        }
                    } else {
                        // Failed, probably exceeded file size limit.
                        alert(data["error"]);
                        if ($(`#${rowId}`).attr("data-exists")) {
                            $(`#${rowId}`).attr("data-status", "failed");
                            $(`#${rowId} .progress`).remove();
                            $(`#${rowId} .cancel`).remove();
                            deleteButton(`#${rowId}`);
                        } else {
                            $(`#${rowId}`).remove();
                        }
                    }
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    // Canceling invokes this too, with a zero xhr.status.
                    // Also badly-formed JSON response due to PHP displaying errors/warnings.
                    // Also database deadlocks, so try again.
                    numCalls--;
                    if (xhr.status) {
                        try {
                            var json = JSON.parse(xhr.responseText);
                            if (json.code == 1213) {
                                // Deadlock, try again.
                                this.tryCount++;
                                if (this.tryCount <= this.retryLimit) {
                                    $.ajax(this);
                                    return;
                                }
                                alert(`"${$(`#${rowId} .fileName`).text()}":\n` + xhr.status + ": " + xhr.responseText);
                                if ($(`#${rowId}`).attr("data-exists")) {
                                    $(`#${rowId}`).attr("data-status", "failed");
                                    $(`#${rowId} .progress`).remove();
                                    $(`#${rowId} .cancel`).remove();
                                    deleteButton(`#${rowId}`);
                                } else {
                                    $(`#${rowId}`).remove();
                                }
                                return;
                            }
                        } catch (e) {
                            alert("An unexpected error occurred:\n" + xhr.responseText);
                        }
                    }
                }
            });
        }
    }, 100);
});

if (settings.listFiles) {
    // Show existing file listing.
    $.post(settings.handler, {listFiles: true, path: settings.path}, function(data) {
        if (data["result"]) {
            $.each(data["files"], function(k, v) {
                var row = [k].map(fileRow).toString();
                $(row).insertBefore(`#${id}-input`);
                var rowId = `${id}-row-${k}`;
                $(`#${rowId} .fileName`).text(v["name"]);
                $(`#${rowId} .fileName`).wrapInner(`<a href="${v["url"]}" target="_blank"></a>`);
                $(`#${rowId} .fileSize`).text(byteSize(v["size"]));
                if (v["width"] && v["height"])
                    $(`#${rowId} .fileSize`).text(byteSize(v["size"]) + ", " + v["width"] + "x" + v["height"]);
                $(`#${rowId}`).attr("data-exists", true);
                $(`#${rowId}`).attr("data-filename", v["name"]);
                thumbnail(rowId, v["type"], v["url"]);
                deleteButton(`#${rowId}`);
            });
        }
    });
}

return this;

///// END PLUG-IN /////
}}(jQuery));
