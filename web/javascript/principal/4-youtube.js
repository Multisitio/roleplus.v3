
document.addEventListener("DOMContentLoaded", () => {
    initYouTubePlayers();

    const observer = new MutationObserver(() => {
        initYouTubePlayers();
    });

    observer.observe(document.body, { childList: true, subtree: true });
});

function initYouTubePlayers() {
    document.querySelectorAll(".youtube-player:not([data-initialized])").forEach(player => {
        player.dataset.initialized = "true";
        const videoId = player.dataset.id;
        const thumbnail = createThumbnail(videoId);
        player.appendChild(thumbnail);
    });
}

function createThumbnail(id) {
    const div = document.createElement("div");
    div.className = "youtube-thumbnail";
    div.dataset.id = id;
    div.innerHTML = `
        <img src="https://i.ytimg.com/vi/${id}/hqdefault.jpg" alt="YouTube Video Thumbnail">
        <div class="play"></div>
    `;
    div.addEventListener("click", loadIframe);
    return div;
}

function loadIframe() {
    const iframe = document.createElement("iframe");
    iframe.src = `https://www.youtube.com/embed/${this.dataset.id}?autoplay=1`;
    iframe.frameBorder = "0";
    iframe.allowFullscreen = true;
    iframe.allow = "autoplay; encrypted-media";
    this.replaceWith(iframe);
}
