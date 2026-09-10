(function () {
    function toggleNewMenu() {
        var menu = document.getElementById('qol-new-menu');
        if (!menu) return;
        menu.style.display = (menu.style.display === 'none' || menu.style.display === '') ? 'block' : 'none';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('qol-new-button');
        if (btn) btn.addEventListener('click', function (e) {
            e.preventDefault();
            toggleNewMenu();
        });

        // Example: save arrange-by when changed. Freescout uses select or other UI - hook into change events by data attribute
        var arrangeElements = document.querySelectorAll('[data-qol-arrange-by]');
        arrangeElements.forEach(function (el) {
            el.addEventListener('change', function () {
                var val = el.value;
                fetch('/qol/preferences/arrange-by', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': (window.Laravel && window.Laravel.csrfToken) || document.querySelector('meta[name=csrf-token]')?.getAttribute('content')
                    },
                    body: JSON.stringify({ arrange_by: val })
                });
            });
        });
    });
})();
