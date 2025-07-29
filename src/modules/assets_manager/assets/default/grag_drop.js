(function(){
    $(document).ready(function() {

        const dropZoneContainer = $(`__UUID__`);

        if (dropZoneContainer.length === 0) {
            return;
        }

        // Variables to store files
        let selectedFiles = []; // Local files selected for upload
        let uploadedFiles = []; // Server-side files after upload

        // Cache DOM elements
        const $dropZone = dropZoneContainer.find('#dropZone');
        const $fileInput = dropZoneContainer.find('#fileInput');
        const $fileList = dropZoneContainer.find('#fileList');
        const $uploadBtn = dropZoneContainer.find('#uploadBtn');
        const $clearBtn = dropZoneContainer.find('#clearBtn');
        const $progressContainer = dropZoneContainer.find('.upload-progress-container');
        const $progressBar = dropZoneContainer.find('#totalProgress');
        const $progressText = dropZoneContainer.find('.progress-text');
        const $emptyMessage = dropZoneContainer.find('.empty-message');

        // Maximum file size in bytes (10MB)
        const MAX_FILE_SIZE = 10 * 1024 * 1024;

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            $dropZone[0].addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Highlight drop zone when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            $dropZone[0].addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            $dropZone[0].addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            $dropZone.addClass('highlight');
        }

        function unhighlight() {
            $dropZone.removeClass('highlight');
            $dropZone.removeClass('error');
        }

        // Handle dropped files
        $dropZone[0].addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        }

        // Handle files from file input
        $fileInput.on('change', function() {
            handleFiles(this.files);
        });

        // Process the selected files
        function handleFiles(files) {
            if (files.length === 0) return;

            // Convert FileList to array and add to selectedFiles
            const newFiles = Array.from(files);

            // Check file sizes
            let hasLargeFile = false;
            newFiles.forEach(file => {
                if (file.size > MAX_FILE_SIZE) {
                    hasLargeFile = true;
                    showError(`File "${file.name}" exceeds the maximum size limit of 10MB.`);
                }
            });

            if (hasLargeFile) {
                $dropZone.addClass('error');
                return;
            }

            // Add valid files to selection
            selectedFiles = [...selectedFiles, ...newFiles.filter(file => file.size <= MAX_FILE_SIZE)];

            // Update UI
            updateFileList();
            updateButtons();
        }

        // Show error message
        function showError(message) {
            // Create error message if it doesn't exist
            if (dropZoneContainer.find('.error-message').length === 0) {
                $('<div class="error-message"></div>').insertAfter($dropZone);
            }

            const $errorMessage = dropZoneContainer.find('.error-message');
            $errorMessage.text(message).show();

            // Hide after 5 seconds
            setTimeout(() => {
                $errorMessage.hide();
                $dropZone.removeClass('error');
            }, 5000);
        }

        // Update the file list UI
        function updateFileList() {
            $fileList.empty();

            // Show uploaded files if any (these come from server response)
            if (uploadedFiles.length > 0) {
                uploadedFiles.forEach((file, index) => {
                    const $fileItem = $('<div class="file-item uploaded"></div>');

                    // Check if file is an image
                    const isImage = file.mime_type && file.mime_type.startsWith('image/');

                    if (isImage) {
                        // For uploaded images, we could show a thumbnail from the server
                        // This would typically be a URL to the image on the server
                        if (file.uri) {
                            // Create a URL to the image (adjust this based on your server setup)
                            // This assumes the file_path can be used to construct a URL
                            const $preview = $('<img class="file-preview" alt="Preview">');
                            $preview.attr('src', file.uri);
                            $fileItem.prepend($preview);
                        } else {
                            // Fallback to icon if no path available
                            const $fileIcon = $('<div class="file-icon image-icon"></div>').text(file.extension.toUpperCase());
                            $fileItem.prepend($fileIcon);
                        }
                    } else {
                        // Create file icon with extension
                        const extension = file.extension ? file.extension.toUpperCase() : 'FILE';
                        const $fileIcon = $('<div class="file-icon"></div>').text(extension);
                        $fileItem.prepend($fileIcon);
                    }

                    // File details
                    const $fileDetails = $('<div class="file-details"></div>');
                    $fileDetails.append(`<div class="file-name">${file.name}</div>`);
                    $fileDetails.append(`<div class="file-size">${formatFileSize(file.size)}</div>`);
                    $fileDetails.append(`<div class="file-path">${file.uri}</div>`);
                    $fileItem.append($fileDetails);

                    // Remove button for uploaded files
                    const $removeBtn = $('<div class="file-remove">&times;</div>');
                    $removeBtn.on('click', function() {
                        deleteUploadedFile(index);
                    });
                    $fileItem.append($removeBtn);

                    $fileList.append($fileItem);
                });
                return;
            }

            // Show selected files if no uploaded files
            if (selectedFiles.length === 0) {
                $fileList.append('<p class="empty-message">No files selected</p>');
                return;
            }

            selectedFiles.forEach((file, index) => {
                const $fileItem = $('<div class="file-item"></div>');

                // Check if file is an image
                const isImage = file.type.startsWith('image/');

                if (isImage) {
                    // Create image preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const $preview = $('<img class="file-preview" alt="Preview">');
                        $preview.attr('src', e.target.result);
                        $fileItem.prepend($preview);
                    };
                    reader.readAsDataURL(file);
                } else {
                    // Create file icon with extension
                    const extension = file.name.split('.').pop().toUpperCase();
                    const $fileIcon = $('<div class="file-icon"></div>').text(extension);
                    $fileItem.prepend($fileIcon);
                }

                // File details
                const $fileDetails = $('<div class="file-details"></div>');
                $fileDetails.append(`<div class="file-name">${file.name}</div>`);
                $fileDetails.append(`<div class="file-size">${formatFileSize(file.size)}</div>`);
                $fileItem.append($fileDetails);

                // Remove button
                const $removeBtn = $('<div class="file-remove">&times;</div>');
                $removeBtn.on('click', function() {
                    removeFile(index);
                });
                $fileItem.append($removeBtn);

                $fileList.append($fileItem);
            });
        }

        // Format file size to human-readable format
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';

            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));

            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Remove a file from the selection (local files)
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFileList();
            updateButtons();
        }

        // Delete an uploaded file from the server
        function deleteUploadedFile(index) {
            const fileToDelete = uploadedFiles[index];

            // Show deletion in progress
            const $fileItem = $($fileList.find('.file-item')[index]);
            $fileItem.addClass('deleting');

            // Send delete request to server
            fetch('/file/delete/ajax', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(fileToDelete)
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Remove file from array on success
                    uploadedFiles.splice(index, 1);
                    updateFileList();

                    // Show success message
                    if (dropZoneContainer.find('.success-message').length === 0) {
                        $('<div class="success-message"></div>').insertAfter($progressContainer);
                    }
                    dropZoneContainer.find('.success-message').text('File deleted successfully!').show();

                    // Hide success message after 3 seconds
                    setTimeout(() => {
                        dropZoneContainer.find('.success-message').hide();
                    }, 3000);
                })
                .catch(error => {
                    console.error('Delete error:', error);
                    $fileItem.removeClass('deleting');
                    showError('Delete failed: ' + error.message);
                });
        }

        // Update button states
        function updateButtons() {
            // If we have uploaded files, disable upload button and enable clear
            if (uploadedFiles.length > 0) {
                $uploadBtn.prop('disabled', true);
                $clearBtn.prop('disabled', false);
                return;
            }

            // Otherwise, base on selected files
            if (selectedFiles.length > 0) {
                $uploadBtn.prop('disabled', false);
                $clearBtn.prop('disabled', false);
            } else {
                $uploadBtn.prop('disabled', true);
                $clearBtn.prop('disabled', true);
            }
        }

        // Clear all selected files
        $clearBtn.on('click', function() {
            // If we have uploaded files, confirm before clearing
            if (uploadedFiles.length > 0) {
                if (confirm('This will only clear the display. Files will remain on the server. Continue?')) {
                    uploadedFiles = [];
                    updateFileList();
                    updateButtons();
                }
            } else {
                selectedFiles = [];
                updateFileList();
                updateButtons();
                $fileInput.val('');
            }
        });

        // Handle file upload
        $uploadBtn.on('click', function() {
            if (selectedFiles.length === 0) return;

            // Use FormData and Fetch API to upload files
            uploadFiles();
        });

        // Upload files using Fetch API
        function uploadFiles() {
            $progressContainer.show();
            $uploadBtn.prop('disabled', true);
            $clearBtn.prop('disabled', true);

            // Create FormData object
            const formData = new FormData();

            // Add each file to FormData
            selectedFiles.forEach((file, index) => {
                formData.append('files[]', file);
            });

            // Add any additional data if needed
            formData.append('fileCount', selectedFiles.length);

            // Use Fetch API to send files to server
            fetch('/file/upload/ajax', {
                method: 'POST',
                body: formData,
            })
                .then(response => {
                    // Check if the request was successful
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Handle successful upload
                    updateProgress(100);

                    // Process server response
                    if (data.results && Array.isArray(data.results)) {
                        // Store uploaded files from server response
                        uploadedFiles = data.results;

                        dropZoneContainer.find("input[type='hidden']").val(JSON.stringify(uploadedFiles));

                        // Update UI with uploaded files
                        updateFileList();
                        updateButtons();

                        // Show success message
                        setTimeout(() => {
                            uploadComplete(true, 'Files uploaded successfully!');
                        }, 500);
                    } else {
                        throw new Error('Invalid server response format');
                    }
                })
                .catch(error => {
                    // Handle upload error
                    console.error('Upload error:', error);
                    uploadComplete(false, 'Upload failed: ' + error.message);
                });

            // For demonstration purposes, show progress
            // In a real implementation, you might use an upload progress event
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += 5;
                if (progress <= 90) { // Only go to 90% until we get server confirmation
                    updateProgress(progress);
                } else {
                    clearInterval(progressInterval);
                }
            }, 200);
        }

        // Update progress bar
        function updateProgress(percent) {
            $progressBar.css('width', percent + '%');
            $progressText.text(percent + '%');
        }

        // Handle upload completion
        function uploadComplete(success, message) {
            if (success) {
                // Show success message
                if (dropZoneContainer.find('.success-message').length === 0) {
                    $('<div class="success-message"></div>').insertAfter($progressContainer);
                }
                dropZoneContainer.find('.success-message').text(message).show();

                // Reset selected files since they're now uploaded
                selectedFiles = [];
                $fileInput.val('');

                // Hide success message after 5 seconds
                setTimeout(() => {
                    dropZoneContainer.find('.success-message').hide();
                    $progressContainer.hide();
                    updateProgress(0);
                }, 5000);
            } else {
                // Show error message
                showError(message);

                // Re-enable buttons
                $uploadBtn.prop('disabled', false);
                $clearBtn.prop('disabled', false);

                // Hide progress after 2 seconds
                setTimeout(() => {
                    $progressContainer.hide();
                    updateProgress(0);
                }, 2000);
            }
        }

        // Click on drop zone to trigger file input
        $dropZone.on('click', function() {
            $fileInput.trigger('click');
        });
    });
})();
