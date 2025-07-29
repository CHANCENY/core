// Update the placeholder images to use data URIs instead of missing image files
$(document).ready(function() {
    // Create a data URI for placeholder images
    function createPlaceholderDataURI() {
        // Create a canvas element
        const canvas = document.createElement('canvas');
        canvas.width = 200;
        canvas.height = 200;
        const ctx = canvas.getContext('2d');
        
        // Draw background
        ctx.fillStyle = '#e9ecef';
        ctx.fillRect(0, 0, 200, 200);
        
        // Draw circle
        ctx.fillStyle = '#adb5bd';
        ctx.beginPath();
        ctx.arc(100, 100, 50, 0, Math.PI * 2);
        ctx.fill();
        
        // Add text
        ctx.fillStyle = 'white';
        ctx.font = '20px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('Image', 100, 100);
        
        // Return data URI
        return canvas.toDataURL('image/png');
    }
    
    // Replace all placeholder image sources with data URIs
    const placeholderURI = createPlaceholderDataURI();
    $(".gallery-item img").each(function() {
       // $(this).attr('src', placeholderURI);
    });
});
