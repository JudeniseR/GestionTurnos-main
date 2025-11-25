document.addEventListener('DOMContentLoaded', function () {
    const perfilBtn = document.getElementById('perfil-img');
    const menu = document.getElementById('perfil-menu');

    if (perfilBtn && menu) {
        perfilBtn.addEventListener('click', function () {
            const visible = menu.style.display === 'block';
            menu.style.display = visible ? 'none' : 'block';
        });

        document.addEventListener('click', function (event) {
            if (!perfilBtn.contains(event.target) && !menu.contains(event.target)) {
                menu.style.display = 'none';
            }
        });
    }
});
