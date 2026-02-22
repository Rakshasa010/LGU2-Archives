document.addEventListener('DOMContentLoaded', function() {
    // 1. Get highlight ID from URL
    const params = new URLSearchParams(window.location.search);
    const highlightId = params.get('highlight');
    if (!highlightId) return;

    // 2. Find the element
    // Try finding by id attribute first, then data-id
    let el = document.getElementById(`file-${highlightId}`) || 
             document.querySelector(`[data-id="${highlightId}"]`) ||
             document.getElementById(highlightId);

    if (el) {
        // 3. Scroll into view
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // 4. Add highlight effect
        // Remove existing class to restart animation if needed
        el.classList.remove('highlight-record');
        void el.offsetWidth; // Trigger reflow
        el.classList.add('highlight-record');

        // 5. Remove the highlight parameter from URL to keep it clean
        const url = new URL(window.location);
        url.searchParams.delete('highlight');
        window.history.replaceState({}, '', url);

        // Optional: Remove class after animation completes (3s)
        setTimeout(() => {
            el.classList.remove('highlight-record');
        }, 3000);
    }
});