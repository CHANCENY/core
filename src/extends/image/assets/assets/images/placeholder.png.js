// This script creates a placeholder image and saves it as a data URI
const canvas = document.createElement('canvas');
canvas.width = 200;
canvas.height = 200;
const ctx = canvas.getContext('2d');

// Draw background
ctx.fillStyle = 'lightgray';
ctx.fillRect(0, 0, 200, 200);

// Draw circle
ctx.fillStyle = 'gray';
ctx.beginPath();
ctx.arc(100, 100, 50, 0, Math.PI * 2);
ctx.fill();

// Add text
ctx.fillStyle = 'white';
ctx.font = '20px Arial';
ctx.textAlign = 'center';
ctx.textBaseline = 'middle';
ctx.fillText('Image', 100, 100);

// Convert to data URI
const dataURI = canvas.toDataURL('image/png');
console.log(dataURI);
