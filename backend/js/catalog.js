document.addEventListener('DOMContentLoaded', () => {
    const catalogButton = document.getElementById('catalogButton');
    const catalogDropdown = document.getElementById('catalogDropdown');
    const catalogContainer = document.querySelector('.catalog-container');

    if (catalogButton && catalogDropdown) {
        catalogButton.addEventListener('click', (e) => {
            e.stopPropagation();
            catalogContainer.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (!catalogContainer.contains(e.target)) {
                catalogContainer.classList.remove('active');
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                catalogContainer.classList.remove('active');
            }
        });

        catalogDropdown.addEventListener('click', (e) => {
            if (e.target.tagName === 'A') {
                catalogContainer.classList.remove('active');
            }
        });

        catalogButton.addEventListener('mouseenter', () => {
            if (!catalogContainer.classList.contains('active')) {
                catalogButton.style.transform = 'translateY(-2px)';
            }
        });

        catalogButton.addEventListener('mouseleave', () => {
            if (!catalogContainer.classList.contains('active')) {
                catalogButton.style.transform = 'translateY(0)';
            }
        });
    }

    const catalogItems = document.querySelectorAll('.catalog-item');
    catalogItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px)';
        });

        item.addEventListener('mouseleave', function() {
            if (!this.classList.contains('active')) {
                this.style.transform = 'translateY(0)';
            }
        });

        item.addEventListener('click', function(e) {
            catalogItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');
        });
    });
});
