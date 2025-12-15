/* ==========================================================
   NoBloat Email Verification - Admin JS
   ----------------------------------------------------------
   Handles UI behavior for the settings page and AJAX
   template resets.
   ========================================================== */

/* ==========================================================
   Set browser timezone cookie for PHP date conversions
   ----------------------------------------------------------
   Runs immediately (before DOMContentLoaded) to ensure
   the cookie is available on subsequent page loads.
   ========================================================== */
(function() {
    try {
        var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (tz) {
            document.cookie = "nbuf_browser_tz=" + encodeURIComponent(tz) + ";path=/;max-age=31536000;SameSite=Lax";
        }
    } catch (e) {
        /* Fallback: browser doesn't support Intl API */
    }
})();

document.addEventListener("DOMContentLoaded", function () {

    /* ==========================================================
       Toggle visibility of custom hook field
       ========================================================== */
    const customHookCheckbox = document.querySelector("#nbuf-hooks-custom-enable");
    const customHookField = document.querySelector("#nbuf-hooks-custom-field");

    if (customHookCheckbox && customHookField) {
        const toggleField = () => {
            customHookField.style.display = customHookCheckbox.checked ? "block" : "none";
        };
        customHookCheckbox.addEventListener("change", toggleField);
        toggleField(); // run once on load
    }

    /* ==========================================================
       Visual highlight for uninstall checkboxes
       ========================================================== */
    const cleanupBoxes = document.querySelectorAll(".nbuf-cleanup input[type='checkbox']");
    cleanupBoxes.forEach(box => {
        box.addEventListener("change", () => {
            box.parentElement.classList.toggle("checked", box.checked);
        });
    });

    /* ==========================================================
       AJAX: Reset Templates to Default
       ========================================================== */
    document.querySelectorAll(".nbuf-reset-template").forEach(button => {
        button.addEventListener("click", function (e) {
            e.preventDefault();

            const type = this.dataset.template;
            if (!type) return;

            if (!confirm(`Restore the ${type} template to default?`)) return;

            const formData = new FormData();
            formData.append("action", "nbuf_reset_template");
            formData.append("nonce", nobloatEV.nonce);
            formData.append("template", type);

            fetch(nobloatEV.ajax_url, {
                method: "POST",
                credentials: "same-origin",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.data.message);

                    let targetTextarea;
                    switch (type) {
                        case "html":
                            targetTextarea = document.querySelector("textarea[name='nbuf_email_template_html']");
                            break;
                        case "text":
                            targetTextarea = document.querySelector("textarea[name='nbuf_email_template_text']");
                            break;
                        case "password":
                            targetTextarea = document.querySelector("textarea[name='nbuf_password_reset_template']");
                            break;
                        case "policy-privacy-html":
                            targetTextarea = document.querySelector("textarea[name='nbuf_policy_privacy_html']");
                            break;
                        case "policy-terms-html":
                            targetTextarea = document.querySelector("textarea[name='nbuf_policy_terms_html']");
                            break;
                        case "admin-new-user-html":
                            targetTextarea = document.querySelector("textarea[name='nbuf_admin_new_user_html']");
                            break;
                        case "admin-new-user-text":
                            targetTextarea = document.querySelector("textarea[name='nbuf_admin_new_user_text']");
                            break;
                        case "login-form":
                            targetTextarea = document.querySelector("textarea[name='nbuf_login_form_template']");
                            break;
                        case "registration-form":
                            targetTextarea = document.querySelector("textarea[name='nbuf_registration_form_template']");
                            break;
                        case "account-page":
                            targetTextarea = document.querySelector("textarea[name='nbuf_account_page_template']");
                            break;
                        case "request-reset-form":
                            targetTextarea = document.querySelector("textarea[name='nbuf_request_reset_form_template']");
                            break;
                        case "reset-form":
                            targetTextarea = document.querySelector("textarea[name='nbuf_reset_form_template']");
                            break;
                    }

                    if (targetTextarea && data.data.content) {
                        targetTextarea.value = data.data.content;

                        targetTextarea.classList.add("nbuf-updated");
                        setTimeout(() => targetTextarea.classList.remove("nbuf-updated"), 2000);
                    }
                } else {
                    alert(data.data || "Failed to restore template. Default file not found.");
                }
            })
            .catch(() => alert("AJAX request failed. Please check your console or try again."));
        });
    });

    /* ==========================================================
       AJAX: Reset CSS Styles to Default
       ========================================================== */
    document.querySelectorAll(".nbuf-reset-style-btn").forEach(button => {
        button.addEventListener("click", function (e) {
            e.preventDefault();

            const template = this.dataset.template;
            const target = this.dataset.target;
            if (!template || !target) return;

            if (!confirm(`Restore the ${template} CSS to default?`)) return;

            const formData = new FormData();
            formData.append("action", "nbuf_reset_style");
            formData.append("nonce", nobloatEV.nonce);
            formData.append("template", template);

            fetch(nobloatEV.ajax_url, {
                method: "POST",
                credentials: "same-origin",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.data.message);

                    const targetTextarea = document.querySelector(`textarea[name="${target}"]`);
                    if (targetTextarea && data.data.content) {
                        targetTextarea.value = data.data.content;

                        targetTextarea.classList.add("nbuf-updated");
                        setTimeout(() => targetTextarea.classList.remove("nbuf-updated"), 2000);
                    }
                } else {
                    alert(data.data || "Failed to restore CSS. Default file not found.");
                }
            })
            .catch(() => alert("AJAX request failed. Please check your console or try again."));
        });
    });

    /* ==========================================================
       AJAX: Reset HTML Template to Default
       ========================================================== */
    document.querySelectorAll(".nbuf-reset-template-btn").forEach(button => {
        button.addEventListener("click", function (e) {
            e.preventDefault();

            const template = this.dataset.template;
            if (!template) return;

            if (!confirm(`Restore the ${template} HTML template to default? This will reload the page.`)) return;

            const formData = new FormData();
            formData.append("action", "nbuf_reset_template");
            formData.append("nonce", nobloatEV.nonce);
            formData.append("template", template);

            fetch(nobloatEV.ajax_url, {
                method: "POST",
                credentials: "same-origin",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.data.message);
                    location.reload();
                } else {
                    alert(data.data || "Failed to restore template.");
                }
            })
            .catch(() => alert("AJAX request failed. Please try again."));
        });
    });

    /* ==========================================================
       Auto-dismiss success notices after 2 seconds
       ========================================================== */
    const successNotices = document.querySelectorAll(".notice-success.is-dismissible");
    if (successNotices.length > 0) {
        successNotices.forEach(notice => {
            setTimeout(() => {
                notice.style.transition = "opacity 0.5s ease-out";
                notice.style.opacity = "0";
                setTimeout(() => {
                    notice.remove();
                }, 500); // Remove after fade out
            }, 2000); // Wait 2 seconds before fading
        });
    }

    /* ==========================================================
       Tab Navigation (Legacy single-level tabs)
       ========================================================== */
    const tabLinks = document.querySelectorAll(".nbuf-tab-link");
    const tabContents = document.querySelectorAll(".nbuf-tab-content");

    if (tabLinks.length > 0) {
        tabLinks.forEach(link => {
            link.addEventListener("click", function (e) {
                e.preventDefault();

                tabLinks.forEach(l => l.classList.remove("active"));
                tabContents.forEach(c => c.classList.remove("active"));

                const targetId = this.getAttribute("data-target");
                const target = document.getElementById(targetId);

                this.classList.add("active");
                if (target) target.classList.add("active");
            });
        });

        /* Load active tab from ?tab= query string */
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab');
        if (activeTab) {
            const normalizedTab = `tab-${activeTab.replace(/[^a-z0-9\-]/gi, '')}`;
            const targetLink = document.querySelector(`.nbuf-tab-link[data-target="${normalizedTab}"]`);
            if (targetLink) {
                targetLink.click();
            }
        }
    }

    /* ==========================================================
       Two-Level Tab Navigation (Outer + Inner tabs)
       ----------------------------------------------------------
       Progressive enhancement: client-side tab switching without
       page reload. Falls back to URL-based navigation.
       ========================================================== */
    const outerTabLinks = document.querySelectorAll(".nbuf-outer-tab-link");
    const outerTabContents = document.querySelectorAll(".nbuf-outer-tab-content");
    const innerTabLinks = document.querySelectorAll(".nbuf-inner-tab-link");
    const innerTabContents = document.querySelectorAll(".nbuf-inner-tab-content");

    /* Outer tab switching (client-side) */
    if (outerTabLinks.length > 0) {
        outerTabLinks.forEach(link => {
            link.addEventListener("click", function (e) {
                /* Allow default navigation to preserve URL state */
                /* This ensures bookmarkable URLs and proper back button behavior */
                /* Comment out e.preventDefault() to use URL-based navigation */

                /* Optional: Client-side switching for smoother UX
                e.preventDefault();

                outerTabLinks.forEach(l => l.classList.remove("active"));
                outerTabContents.forEach(c => c.classList.remove("active"));

                const tabKey = this.getAttribute("data-tab");
                const target = document.getElementById(`nbuf-tab-${tabKey}`);

                this.classList.add("active");
                if (target) target.classList.add("active");

                // Update URL without page reload
                const url = new URL(window.location);
                url.searchParams.set('tab', tabKey);
                window.history.pushState({}, '', url);
                */
            });
        });
    }

    /* Inner tab switching (client-side) */
    if (innerTabLinks.length > 0) {
        innerTabLinks.forEach(link => {
            link.addEventListener("click", function (e) {
                /* Allow default navigation to preserve URL state */
                /* Comment out e.preventDefault() for client-side switching */

                /* Optional: Client-side switching for smoother UX
                e.preventDefault();

                const parentTab = this.closest('.nbuf-outer-tab-content');
                if (!parentTab) return;

                const siblingLinks = parentTab.querySelectorAll(".nbuf-inner-tab-link");
                const siblingContents = parentTab.querySelectorAll(".nbuf-inner-tab-content");

                siblingLinks.forEach(l => l.classList.remove("active"));
                siblingContents.forEach(c => c.classList.remove("active"));

                const subtabKey = this.getAttribute("data-subtab");
                const target = parentTab.querySelector(`#nbuf-subtab-${subtabKey}`);

                this.classList.add("active");
                if (target) target.classList.add("active");

                // Update URL without page reload
                const url = new URL(window.location);
                url.searchParams.set('subtab', subtabKey);
                window.history.pushState({}, '', url);
                */
            });
        });
    }

    /* ==========================================================
       Note: Datetime pickers now use native HTML5 inputs
       ----------------------------------------------------------
       No JavaScript initialization needed for date/time fields.
       Modern browsers provide native date/time pickers with
       better UX and no external dependencies.
       ========================================================== */
});