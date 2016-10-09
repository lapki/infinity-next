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
            initial : true,
            onChange : events.doContentUpdate,
            onUpdate : events.doContentUpdate
        }
    };

    blueprint.prototype.adjustDisplay = function(sfw) {
        $("body").toggleClass('nsfw-filtered', sfw);
        $("body").toggleClass('nsfw-allowed', !sfw);
        var query = window.location.href.split('?');
        if (query[0].split(window.document.domain)[1] === '/') {
            // Index page
            if (query.length > 1 && query[1] === 'nsfw' && sfw) {
                location.href = query[0];
            } else if (!sfw && (query.length <= 1 || (query.length > 1 && query[1] !== 'nsfw'))) {
                window.location.href = query[0] + '?nsfw';
            }
        } else if (query[0].split(window.document.domain)[1].startsWith('/*/')) {
            // Overcatalog
            var link = query[0].split(window.document.domain)[1].substring(3);
            if (link.substring(0, 3) === 'sfw' && !sfw) {
                link = link.substring(3);
                window.location.href = window.location.protocol + '//' + window.document.domain + '/*' + (link.substring(0, 1) === '/' ? '' : '/') + link;
            } else if (link.substring(0, 3) !== 'sfw' && sfw) {
                window.location.href = window.location.protocol + '//' + window.document.domain + '/*/sfw' + (link.substring(0, 1) === '/' ? '' : '/') + link;
            }
        }
        // Convert Overboard link to SFW version when SFW mode is on
        if (sfw) {
            $(".gnav a[data-item='recent_posts']").attr('href', window.location.protocol + '//' + window.document.domain + '/*/sfw/catalog');
        } else {
            $(".gnav a[data-item='home']").attr('href', window.location.protocol + '//' + window.document.domain + '/?nsfw');
        }

    };

    // Event bindings
    blueprint.prototype.bind = function() {
        var data    = {
            widget  : this,
            $widget : this.$widget
        };

        this.adjustDisplay(this.is('sfw'));
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
