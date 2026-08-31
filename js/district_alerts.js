// District Admin - Alerts Tab Switching Script
function switchTab(tabName, btnElement) {
    const lowstockSection = document.getElementById('lowstockSection');
    const expirySection = document.getElementById('expirySection');
    const chips = document.querySelectorAll('.chip');

    // Toggle active chip styles
    chips.forEach(chip => chip.classList.remove('active'));
    btnElement.classList.add('active');

    // Toggle sections
    if (tabName === 'lowstock') {
        if (lowstockSection) lowstockSection.style.display = 'block';
        if (expirySection) expirySection.style.display = 'none';
    } else if (tabName === 'expiry') {
        if (lowstockSection) lowstockSection.style.display = 'none';
        if (expirySection) expirySection.style.display = 'block';
    }
}