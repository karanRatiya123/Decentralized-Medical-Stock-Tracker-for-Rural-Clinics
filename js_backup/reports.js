function triggerPdfDownload(fileName, selectId) {
    const successBanner = document.getElementById("pdfSuccessBanner");
    const bannerText = document.getElementById("successBannerText");
    const durationSelect = document.getElementById(selectId);
    
    const selectedDuration = durationSelect ? durationSelect.value : "Selected Period";

    if (successBanner && bannerText) {
        bannerText.textContent = `Generating PDF (${selectedDuration}) for ${fileName}... Download starting.`;
        successBanner.style.display = "flex";
        
        setTimeout(() => {
            successBanner.style.display = "none";
        }, 4500);
    }
}