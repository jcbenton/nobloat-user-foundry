/**
 * NoBloat User Foundry - Account Page JavaScript
 *
 * Handles tab navigation and toast message auto-dismiss.
 */
(function() {
    'use strict';

    /* Auto-dismiss toast messages after 5 seconds */
    function dismissMessages() {
        var messages = document.querySelectorAll('.nbuf-message');
        if (messages && messages.length > 0) {
            setTimeout(function() {
                for (var i = 0; i < messages.length; i++) {
                    (function(msg) {
                        msg.style.transition = 'opacity 0.5s ease-out';
                        msg.style.opacity = '0';
                        setTimeout(function() {
                            if (msg.parentNode) {
                                msg.parentNode.removeChild(msg);
                            }
                        }, 500);
                    })(messages[i]);
                }
            }, 5000);
        }
    }

    /* Tab navigation */
    function initTabs() {
        var tabs = document.querySelectorAll('.nbuf-tab-button');
        var contents = document.querySelectorAll('.nbuf-tab-content');
        var subtabs = document.querySelectorAll('.nbuf-subtab-button');

        /* Helper: Get URL parameter */
        function getUrlParam(name) {
            var params = new URLSearchParams(window.location.search);
            return params.get(name);
        }

        /* Helper: Activate a main tab */
        function activateTab(tabName) {
            var tabBtn = document.querySelector('.nbuf-tab-button[data-tab="' + tabName + '"]');
            var tabContent = document.querySelector('.nbuf-tab-content[data-tab="' + tabName + '"]');
            if (tabBtn && tabContent) {
                for (var i = 0; i < tabs.length; i++) {
                    tabs[i].classList.remove('nbuf-tab-active');
                }
                for (var j = 0; j < contents.length; j++) {
                    contents[j].classList.remove('nbuf-tab-active');
                }
                tabBtn.classList.add('nbuf-tab-active');
                tabContent.classList.add('nbuf-tab-active');
            }
        }

        /* Helper: Activate a sub-tab within a parent */
        function activateSubtab(parent, subtabName) {
            var subtabBtn = parent.querySelector('.nbuf-subtab-button[data-subtab="' + subtabName + '"]');
            var subtabContent = parent.querySelector('.nbuf-subtab-content[data-subtab="' + subtabName + '"]');
            if (subtabBtn && subtabContent) {
                var parentSubtabs = parent.querySelectorAll('.nbuf-subtab-button');
                var parentContents = parent.querySelectorAll('.nbuf-subtab-content');
                for (var i = 0; i < parentSubtabs.length; i++) {
                    parentSubtabs[i].classList.remove('nbuf-subtab-active');
                }
                for (var j = 0; j < parentContents.length; j++) {
                    parentContents[j].classList.remove('nbuf-subtab-active');
                }
                subtabBtn.classList.add('nbuf-subtab-active');
                subtabContent.classList.add('nbuf-subtab-active');
            }
        }

        /* Restore tabs from URL on page load */
        var activeTab = getUrlParam('tab');
        var activeSubtab = getUrlParam('subtab');

        if (activeTab) {
            activateTab(activeTab);
            if (activeSubtab) {
                var parent = document.querySelector('.nbuf-tab-content[data-tab="' + activeTab + '"]');
                if (parent) {
                    activateSubtab(parent, activeSubtab);
                }
            }
        }

        /* Main tab click handlers */
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].addEventListener('click', function() {
                var tabName = this.getAttribute('data-tab');
                activateTab(tabName);
                /* Update URL to reflect current tab (without page reload) */
                var url = new URL(window.location.href);
                url.searchParams.set('tab', tabName);
                url.searchParams.delete('subtab');
                history.replaceState(null, '', url.toString());
            });
        }

        /* Sub-tab click handlers */
        for (var j = 0; j < subtabs.length; j++) {
            subtabs[j].addEventListener('click', function() {
                var parent = this.closest('.nbuf-tab-content');
                if (parent) {
                    var subtabName = this.getAttribute('data-subtab');
                    var tabName = parent.getAttribute('data-tab');
                    activateSubtab(parent, subtabName);
                    /* Update URL to reflect current tab and subtab (without page reload) */
                    var url = new URL(window.location.href);
                    url.searchParams.set('tab', tabName);
                    url.searchParams.set('subtab', subtabName);
                    history.replaceState(null, '', url.toString());
                }
            });
        }
    }

    /* Initialize on DOMContentLoaded or immediately if already loaded */
    function init() {
        dismissMessages();
        initTabs();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
