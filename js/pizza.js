/*
Copyright 2023 Fleming Computer.
All Rights Reserved.
*/

// function gg(field) {
//     var pairs = window.location.search.substring(1).split("&");
//     var parts;
//     for (var i = 0; i < pairs.length; i++) {
//         parts = pairs[i].split("=");
//         if (parts[0] == field)
//             return parts[1];
//     }
//     return false;
// }

function sessionStorageSupported() {
    try {
        const key = "__sessionStorageSupported__";
        sessionStorage.setItem(key, key);
        sessionStorage.removeItem(key);
        return true;
    } catch (e) {
        return false;
    }
}

function PizzaBar() {
    this.pbwHeight = parseInt($(".pizza-bar-wrapper").height());
    this.pbTop = parseInt($("#pizza-bar").css("top"));
    this.pbRight = parseInt($("#pizza-bar").css("right"));
    this.someMenuIsOn = false;
    var self = this;
    // Do confirm on "delete page".
    $("#pizza-bar .delete-page").on("click", function(e) {
        $(this).css("background-color", "transparent");
        if (confirm("Really delete?")) return true;
        return false;
    });
    // Bind login form submission to the login process.
    $("#pizza-bar form[name=PizzaBar_login]").on("submit", function(e) {
        e.preventDefault();
        self.doLogin($(this));
    });
    // Bind logout link to logout process.
    $("#pizza-bar a.logout").on("click", function(e) {
        e.preventDefault();
        self.doLogout($(this));
    });
    // Bind notify link to notification process.
    $("#pizza-bar a.notify-page").on("click", function(e) {
        self.toggleMenu($(this).parent().parent()); // close menu
        if ($("#notifier").length) return true;
        $.getScript(urlRoot + "/js/notifier.js", function(data, textStatus, jqxhr) {
            $("body").prepend('<div id="notifier"></div>');
            $("#notifier").pizzaNotifier({
                handler: urlRoot + "/ajax/notifier-handler"
            });
        });
    });
    // Bind clicking a menu to toggling it open or closed, but...
    $("#pizza-bar li.menu").on("click", function(e) {
        e.stopPropagation();
        self.toggleMenu($(this));
    });
    // ...prevent menu entries from closing menu (e.g. clicking login panel).
    $("#pizza-bar li.entry").on("click", function(e) {
        e.stopPropagation();
    });
    // Bind mouseenter on a menu to switching it open and closing the other.
    $("#pizza-bar li.menu").on("mouseenter", function(e) {
        e.stopPropagation();
        self.switchMenu($(this));
    });
    // If a click occurs outside any menu, close all menus.
    $(document).on("click", function() {
        self.someMenuIsOn = false;
        // Hide and turn off highlighting for all submenus.
        $("#pizza-bar ul.submenu").hide();
        $("#pizza-bar li.menu").removeClass("menu-on");
        $(".menu-icon").removeClass("menu-on");
        $("#pizza-cart").attr("class", "");
    });
    // Bind window resizing to adjust the menu height and update the
    // Pizza Bar position.
    this.updateMenuHeight();
    this.updatePizzaBarPosition();
    $(window).resize(function() {
        self.updateMenuHeight();
        self.updatePizzaBarPosition();
    });
}

PizzaBar.prototype.doLogin = function(loginForm) {
    // Disable form inputs and display "checking..." message beside it.
    var statusChecking = '<span style="font-size: smaller;">checking...</span>';
    loginForm.find(":input").prop("disabled", true);
    loginForm.find("input[type=submit]").after(statusChecking);
    // Clear any previous errors.
    loginForm.find(".error").remove();
    // Validate login attempt.
    var email = loginForm.find("input[name=email]").val();
    var password = loginForm.find("input[name=password]").val();
    var self = this;
    $.post(urlRoot + "/ajax/login", {email: email, password: password}, function(data) {
        var json = JSON.parse(data);
        if (json["name"] !== false)
            self.renderLoginSuccess(loginForm);
        else
            self.renderLoginFailed(loginForm);
    });
}

PizzaBar.prototype.doLogout = function(logoutHref) {
    $.get(urlRoot + "/ajax/logout", function(data) {
        // alert(data);
        var json = JSON.parse(data);
        if (json["currentUrl"] != "") {
            window.location.replace(json["currentUrl"]);
        }
    });
}

PizzaBar.prototype.renderLoginFailed = function(loginForm) {
    // Remove "checking" message, display error message.
    var statusError = '<span class="error" style="font-size: smaller;">invalid email or password</span>';
    loginForm.find("input[type=submit]").next().remove();
    loginForm.find("input[type=submit]").after(statusError);
    // Reenable form inputs.
    loginForm.find(":input").prop("disabled", false);
}

PizzaBar.prototype.renderLoginSuccess = function(loginForm) {
    // Success, relocate to current page.
    var currentUrl = loginForm.find("input[name=currentUrl]").val();
    window.location.replace(currentUrl);
}

PizzaBar.prototype.switchMenu = function(menu) {
    if (!this.someMenuIsOn) return false;
    // Add highlighting for this submenu.
    menu.addClass("menu-on");
    var svg = menu.find(".menu-icon");
    if (!svg.hasClass("menu-on"))
        svg.addClass("menu-on");
    if (menu.find("#pizza-cart").length > 0) {
        if (!$("#pizza-cart").attr("class"))
            $("#pizza-cart").attr("class", "menu-on");
    }
    // Show this submenu.
    menu.find("ul.submenu").show();
    // Hide all submenus except this one.
    $("#pizza-bar ul.submenu").not(menu.find("ul.submenu")).hide();
    // Turn off highlighting for all submenus except this one.
    $("li.menu").not(menu).removeClass("menu-on");
    $("li.menu").not(menu).find(".menu-icon").removeClass("menu-on");
    if (menu.find("#pizza-cart").length == 0)
        $("#pizza-cart").removeAttr("class");
    this.updateMenuHeight();
}

PizzaBar.prototype.toggleMenu = function(menu) {
    // Don't do the toggle stuff if the cart icon is clicked.
    if (menu.find("#pizza-cart").length > 0) return true;
    this.someMenuIsOn = !this.someMenuIsOn;
    // Toggle highlighting for this submenu.
    menu.toggleClass("menu-on");
    var svg = menu.find(".menu-icon");
    if (svg.length > 0)
        svg.toggleClass("menu-on");
    // Toggle this submenu.
    menu.find("ul.submenu").toggle();
    // Hide all submenus except this one.
    $("#pizza-bar ul.submenu").not(menu.find("ul.submenu")).hide();
    // Turn off highlighting for all submenus except this one.
    $("li.menu").not(menu).removeClass("menu-on");
    $("li.menu").not(menu).find(".menu-icon").removeClass("menu-on");
    if (this.someMenuIsOn) this.updateMenuHeight();
}

PizzaBar.prototype.updateMenuHeight = function() {
    // Set all menus' max-height to fit inside the window's height.
    var maxh = parseInt($(window).height() - this.pbwHeight - this.pbTop - 10);
    $("#pizza-bar ul.submenu").css("max-height", maxh + "px");
    var visibleMenu = $("#pizza-bar ul.submenu:visible").first();
    var h = parseInt(visibleMenu.css("height"));
    if (isNaN(h)) return;
    // If the visible menu is too short, scroll it, otherwise show it all.
    if (h >= maxh) visibleMenu.css("overflow-y", "scroll");
    else visibleMenu.css("overflow-y", "visible");
}

PizzaBar.prototype.updatePizzaBarPosition = function() {
    var pbwRight = parseInt($(window).width()
        - $(".pizza-bar-wrapper").offset().left
        - $(".pizza-bar-wrapper").width());
    $("#pizza-bar").css("right", pbwRight + this.pbRight + "px");
}

function Sitemap() {
    // First, collapse everything.
    $("#sitemap").find("ul").hide();
    $("#sitemap").find(".marker").removeClass("marker-down").addClass("marker-up");
    // Next, expand only the top tree.
    $("#sitemap").find("ul").first().show();
    $("#sitemap").find(".marker").first().removeClass("marker-up").addClass("marker-down");
    // Allow expanding and collapsing upon click.
    $("#sitemap .marker").on("click", function(e) {
        var tree = $(this).parent().children("ul").first();
        if (tree.is(":visible")) {
            $(this).removeClass("marker-down").addClass("marker-up");
            tree.slideUp();
        }
        else if (tree.is(":hidden")) {
            $(this).removeClass("marker-up").addClass("marker-down");
            tree.slideDown();
        }
    });
}

var pathPrefix;
var pagePath;
var urlRoot;
$(function() {
    pathPrefix = $("body").attr("data-path-prefix") ?? "";
    pagePath = pathPrefix == ""
        ? window.location.pathname
        : window.location.pathname.substring(pathPrefix.length);
    urlRoot = pathPrefix == ""
        ? window.location.origin
        : window.location.origin + pathPrefix;
    if ($("#pizza-bar").length) var pb = new PizzaBar();
    if ($("#sitemap").length) var sm = new Sitemap();
});
