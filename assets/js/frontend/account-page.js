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
        var subtabs = document.querySelectorAll('.nbuf-subtab-button, .nbuf-subtab-link');

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
            var subtabBtn = parent.querySelector('.nbuf-subtab-button[data-subtab="' + subtabName + '"], .nbuf-subtab-link[data-subtab="' + subtabName + '"]');
            var subtabContent = parent.querySelector('.nbuf-subtab-content[data-subtab="' + subtabName + '"]');
            if (subtabBtn && subtabContent) {
                var parentSubtabs = parent.querySelectorAll('.nbuf-subtab-button, .nbuf-subtab-link');
                var parentContents = parent.querySelectorAll('.nbuf-subtab-content');
                for (var i = 0; i < parentSubtabs.length; i++) {
                    parentSubtabs[i].classList.remove('nbuf-subtab-active');
                    parentSubtabs[i].classList.remove('active');
                }
                for (var j = 0; j < parentContents.length; j++) {
                    parentContents[j].classList.remove('nbuf-subtab-active');
                    parentContents[j].classList.remove('active');
                }
                subtabBtn.classList.add('active');
                subtabContent.classList.add('active');
            }
        }

        /* Restore tabs from localized data (Universal Mode) or URL params (legacy) */
        var activeTab = null;
        var activeSubtab = null;

        /* Check for Universal Mode localized data first */
        if (typeof nbufAccountData !== 'undefined' && nbufAccountData.activeTab) {
            activeTab = nbufAccountData.activeTab;
            activeSubtab = nbufAccountData.activeSubtab || null;
        }

        /* Fall back to URL params */
        if (!activeTab) {
            activeTab = getUrlParam('tab');
            activeSubtab = getUrlParam('subtab');
        }

        if (activeTab) {
            activateTab(activeTab);
            if (activeSubtab) {
                var parent = document.querySelector('.nbuf-tab-content[data-tab="' + activeTab + '"]');
                if (parent) {
                    activateSubtab(parent, activeSubtab);
                }
            }
        }

        /* Check if Universal Mode (localized data provided) */
        var isUniversalMode = (typeof nbufAccountData !== 'undefined');

        /* Helper: Update URL for tab change */
        function updateUrl(tabName, subtabName) {
            if (isUniversalMode) {
                /* Universal Mode: use pretty path URLs like /user-foundry/account/security/ */
                /* Tab goes in path, subtab goes as query param (router only handles 2 segments) */
                var basePath = window.location.pathname.replace(/\/+$/, ''); /* Remove trailing slashes */
                /* Remove any existing tab from the path (keep up to /account/) */
                var pathParts = basePath.split('/');
                /* Find 'account' in the path and truncate after it */
                var accountIndex = pathParts.indexOf('account');
                if (accountIndex !== -1) {
                    pathParts = pathParts.slice(0, accountIndex + 1);
                }
                /* Add new tab to path (but not if it's the default tab) */
                /* Support both 'main' (new) and 'account' (legacy) as default */
                if (tabName && tabName !== 'main' && tabName !== 'account') {
                    pathParts.push(tabName);
                }
                var newPath = pathParts.join('/') + '/';
                /* Add subtab as query param */
                if (subtabName) {
                    newPath += '?subtab=' + encodeURIComponent(subtabName);
                }
                history.replaceState(null, '', newPath);
            } else {
                /* Legacy Mode: use query parameters */
                var url = new URL(window.location.href);
                if (tabName) {
                    url.searchParams.set('tab', tabName);
                }
                if (subtabName) {
                    url.searchParams.set('subtab', subtabName);
                } else {
                    url.searchParams.delete('subtab');
                }
                history.replaceState(null, '', url.toString());
            }
        }

        /* Helper: Update hidden fields in all forms for tab/subtab state */
        function updateHiddenFields(tabName, subtabName) {
            var forms = document.querySelectorAll('.nbuf-account-form');
            for (var i = 0; i < forms.length; i++) {
                var form = forms[i];

                /* Update or create tab hidden field */
                var tabField = form.querySelector('input[name="nbuf_active_tab"]');
                if (!tabField) {
                    tabField = document.createElement('input');
                    tabField.type = 'hidden';
                    tabField.name = 'nbuf_active_tab';
                    form.appendChild(tabField);
                }
                if (tabName) {
                    tabField.value = tabName;
                }

                /* Update or create subtab hidden field */
                var subtabField = form.querySelector('input[name="nbuf_active_subtab"]');
                if (!subtabField) {
                    subtabField = document.createElement('input');
                    subtabField.type = 'hidden';
                    subtabField.name = 'nbuf_active_subtab';
                    form.appendChild(subtabField);
                }
                subtabField.value = subtabName || '';
            }
        }

        /* Main tab click handlers */
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].addEventListener('click', function() {
                var tabName = this.getAttribute('data-tab');
                activateTab(tabName);
                updateUrl(tabName, null);
                updateHiddenFields(tabName, null);
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
                    updateUrl(tabName, subtabName);
                    updateHiddenFields(tabName, subtabName);
                }
            });
        }

        /* Initialize hidden fields with current state */
        if (activeTab || activeSubtab) {
            updateHiddenFields(activeTab, activeSubtab);
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
