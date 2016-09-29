// ===========================================================================
// Purpose          : Content
// Contributors     : jaw-sh
// Widget Version   : 2
// ===========================================================================

(function(window, $, undefined) {
    // Widget blueprint
    var blueprint = ib.getBlueprint();

    // Content events
    var events = {
        doContentUpdate : function(event) {
            blueprint.prototype.adjustDisplay(event.data.setting.get());
        }
    };

    // Configuration options
    var options = {
        sfw : {
            type : "bool",
            initial : false,
            onChange : events.doContentUpdate,
            onUpdate : events.doContentUpdate
        }
    };

    blueprint.prototype.adjustDisplay = function(sfw) {
        $("body").toggleClass('nsfw-filtered', sfw);
        $("body").toggleClass('nsfw-allowed', !sfw);
    };

    // Event bindings
    blueprint.prototype.bind = function() {
        var widget  = this;
        var $widget = this.$widget;
        var data    = {
            widget  : widget,
            $widget : $widget
        };

        widget.adjustDisplay(widget.is('sfw'));
    };

    blueprint.prototype.defaults = {
        nsfw_skin : "/static/css/skins/next-yotsuba.css",

        selector : {
            'page-stylesheet' : "#page-stylesheet",
            'overboard-nav'   : ".item-recent_posts .gnav-link"
        }
    };

    blueprint.prototype.events = {
    };

    ib.widget("content", blueprint, options);
})(window, window.jQuery);
