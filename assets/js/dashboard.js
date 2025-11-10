/** Part 5 — Dashboard Data Loading and Template Mounting */

// 🟢 یہاں سے Dashboard JavaScript شروع ہو رہا ہے
(function ($) {
    'use strict';

    // 1. یوٹیلیٹیز (Utilities)
    
    // HTML کو صاف کرنے کا فنکشن (XSS سے بچاؤ کے لیے)
    const escapeHtml = (str) => {
        if (typeof str !== 'string') return str;
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    };

    // ٹیمپلیٹ کو mount کرنے کا فنکشن
    const mountTemplate = (rootSelector, templateSelector, data) => {
        const $root = $(rootSelector);
        const $template = $(templateSelector).html();
        if (!$root.length || !$template) {
            console.warn('SSM Dashboard: Root or Template not found.');
            return;
        }
        
        // Mustache-like simple templating (Vue/React کی غیر موجودگی میں)
        let html = $template;
        for (const key in data) {
            if (data.hasOwnProperty(key)) {
                // ڈیٹا کو escape کریں اور ٹیمپلیٹ میں تبدیل کریں
                const escapedValue = escapeHtml(data[key]);
                html = html.replace(new RegExp('{{ ' + key + ' }}', 'g'), escapedValue);
            }
        }
        
        $root.html(html);
    };

    // 2. مین ڈیش بورڈ لاجک
    
    $(document).ready(function () {
        const rootId = '#ssm-dashboard-root';
        const templateId = '#ssm-dashboard-template';
        const ssmData = window.ssmDashboardData; // PHP سے localize کیا گیا ڈیٹا

        if (!$(rootId).length || !ssmData) {
            return; // اگر پیج پر موجود نہ ہو تو رک جائیں
        }
        
        // (AJAX) کے ذریعے ڈیٹا لوڈ کریں
        $.ajax({
            url: ssmData.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: ssmData.action,
                nonce: ssmData.nonce,
            },
            success: function (response) {
                if (response.success) {
                    // ڈیٹا کو ٹیمپلیٹ میں render کریں
                    mountTemplate(rootId, templateId, response.data);
                    
                    // مزید: یہاں Chart.js کو بھی initialize کیا جا سکتا ہے،
                    // لیکن فی الحال صرف ڈمی چارٹ placeholder ہے۔
                    console.log('SSM Dashboard data loaded successfully.');
                } else {
                    // (PHP) سے خرابی کا پیغام
                    $(rootId).html('<div class="ssm-error-message">' + escapeHtml(response.data.message || ssmData.strings.loading_error) + '</div>');
                    console.error('SSM Dashboard Error:', response.data.message);
                }
            },
            error: function (xhr, status, error) {
                // (AJAX) کنکشن میں خرابی
                $(rootId).html('<div class="ssm-error-message">' + ssmData.strings.loading_error + ' (' + status + ')</div>');
                console.error('SSM AJAX Error:', error);
            }
        });
    });

})(jQuery);
// 🔴 یہاں پر Dashboard JavaScript ختم ہو رہا ہے
// ✅ Syntax verified block end.
