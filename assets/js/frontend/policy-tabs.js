/**
 * NoBloat User Foundry - Policy Panel Tab Switching
 *
 * Simple tab switching for the Privacy Policy / Terms of Use
 * panel displayed alongside login and registration forms.
 *
 * @package NoBloat_User_Foundry
 */

(function() {
    'use strict';

    /**
     * Initialize policy tab functionality
     */
    function initPolicyTabs() {
        var panels = document.querySelectorAll('.nbuf-policy-panel');

        panels.forEach(function(panel) {
            var tabLinks = panel.querySelectorAll('.nbuf-policy-tab-link');
            var tabContents = panel.querySelectorAll('.nbuf-policy-tab-content');

            tabLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();

                    var targetTab = this.getAttribute('data-tab');

                    /* Remove active class from all tabs and contents in this panel */
                    tabLinks.forEach(function(l) {
                        l.classList.remove('active');
                    });
                    tabContents.forEach(function(c) {
                        c.classList.remove('active');
                    });

                    /* Add active class to clicked tab and matching content */
                    this.classList.add('active');
                    var content = panel.querySelector('.nbuf-policy-tab-content[data-tab="' + targetTab + '"]');
                    if (content) {
                        content.classList.add('active');
                    }
                });
            });
        });
    }

    /* Initialize when DOM is ready */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPolicyTabs);
    } else {
        initPolicyTabs();
    }
})();
