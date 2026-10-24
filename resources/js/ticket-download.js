import html2canvas from 'html2canvas-pro';

window.html2canvas = html2canvas;
window.dispatchEvent(new CustomEvent('html2canvas:ready'));
