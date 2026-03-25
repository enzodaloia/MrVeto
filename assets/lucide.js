import { createIcons, icons } from 'lucide';

function initLucide() {
    console.log('init lucide');
    createIcons({ icons });
}

document.addEventListener('DOMContentLoaded', initLucide);
document.addEventListener('turbo:load', initLucide);
window.addEventListener('load', initLucide);