document.addEventListener('DOMContentLoaded', initializeTooltips);

function initializeTooltips() {
    const elements = document.querySelectorAll('[data-image]');
    let timeoutId = null;

    console.log('Tooltip initialized with', elements.length, 'elements');

    elements.forEach((element) => {
        element.addEventListener('mouseenter', function (e) {
            if (window.innerWidth > 600) {
                e.stopPropagation();
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => {
                    console.log('Creating tooltip for:', element.dataset.image);
                    createTooltip(element, e);
                }, 500);
            }
        });

        element.addEventListener('mouseleave', function (e) {
            e.stopPropagation();
            clearTimeout(timeoutId);
            console.log('Removing tooltip');
            removeTooltip();
        });

        element.addEventListener('click', function (e) {
            if (window.innerWidth <= 600) {
                e.stopPropagation();
                const existingTooltip = document.querySelector('.tooltip');
                if (existingTooltip) {
                    console.log('Toggling off tooltip');
                    removeTooltip();
                    return;
                }
                console.log('Creating tooltip for mobile click:', element.dataset.image);
                createTooltip(element, e);

                document.addEventListener('click', function closeTooltip(event) {
                    if (!element.contains(event.target) && !document.querySelector('.tooltip')?.contains(event.target)) {
                        console.log('Closing tooltip due to outside click');
                        removeTooltip();
                        document.removeEventListener('click', closeTooltip);
                    }
                }, { once: true });
            }
        });
    });

    function createTooltip(element, event) {
        if (!element.dataset.image) {
            console.warn('No image data found');
            return;
        }

        removeTooltip();

        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        const img = document.createElement('img');
        img.src = element.dataset.image;
        img.alt = 'Preview';
        img.style.maxWidth = '400px'; // 圖片寬度
        img.style.maxHeight = '400px'; // 圖片高度
        img.onerror = () => {
            img.src = 'assest/placeholder.jpg';
            img.alt = 'Image not found';
        };
        tooltip.appendChild(img);
        document.body.appendChild(tooltip);

        const rect = element.getBoundingClientRect();
        tooltip.style.position = 'absolute';
        tooltip.style.left = `${rect.left + window.scrollX}px`;
        tooltip.style.top = `${rect.bottom + window.scrollY + 5}px`;
        tooltip.style.zIndex = '10000';

        const tooltipRect = tooltip.getBoundingClientRect();
        if (tooltipRect.right > window.innerWidth) {
            tooltip.style.left = `${window.innerWidth - tooltipRect.width - 5}px`;
        }
        if (tooltipRect.bottom > window.innerHeight) {
            tooltip.style.top = `${rect.top + window.scrollY - tooltipRect.height - 5}px`;
        }

        tooltip.addEventListener('mouseenter', (e) => e.stopPropagation());
        tooltip.addEventListener('mouseleave', (e) => e.stopPropagation());
    }

    function removeTooltip() {
        const tooltip = document.querySelector('.tooltip');
        if (tooltip) {
            tooltip.remove();
            console.log('Tooltip removed');
        }
    }
}

if (document.body) {
    const observer = new MutationObserver(() => {
        initializeTooltips();
    });
    observer.observe(document.body, { childList: true, subtree: true });
}