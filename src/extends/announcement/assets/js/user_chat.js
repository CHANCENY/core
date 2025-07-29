$(document).ready(function() {
    // DOM Elements
    const $messageInput = $('#userMessageInput');
    const $sendButton = $('#userSendButton');
    const $chatMessages = $('#userChatMessages');
    
    // Disable/Enable send button based on input
    function toggleSendButton() {
        if ($messageInput.val().trim() === '') {
            $sendButton.prop('disabled', true);
        } else {
            $sendButton.prop('disabled', false);
        }
    }
    
    // Initialize button state
    toggleSendButton();
    
    // Listen for input changes
    $messageInput.on('input', toggleSendButton);
    
    // Function to get current time in 12-hour format
    function getCurrentTime() {
        const now = new Date();
        let hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // Convert 0 to 12
        return `${hours}:${minutes} ${ampm}`;
    }
    
    // Function to add a new message to the chat
    function addMessage(message, isUser = true) {
        const messageType = isUser ? 'user-message' : 'support-message';
        const currentTime = getCurrentTime();
        
        const messageHTML = `
            <div class="message ${messageType}">
                <div class="message-content">
                    <p>${message}</p>
                </div>
                <div class="message-time">${currentTime}</div>
            </div>
        `;
        
        $chatMessages.append(messageHTML);
        
        // Scroll to bottom of chat
        $chatMessages.scrollTop($chatMessages[0].scrollHeight);
    }
    
    // Function to handle sending a message
    function sendMessage() {
        const message = $messageInput.val().trim();
        
        if (message !== '') {
            // Add user message to chat
            addMessage(message, true);
            
            // Clear input field
            $messageInput.val('');
            toggleSendButton();
            
            // Simulate support response (in a real app, this would be handled by the server)
            simulateSupportResponse(message);
        }
    }
    
    // Function to simulate support response
    function simulateSupportResponse(userMessage) {
        // In a real application, this would be replaced with actual server communication
        // This is just for demonstration purposes
        
        // Simulate typing delay
        setTimeout(function() {
            // Simple response logic (would be replaced by actual support responses)
            let response;
            
            if (userMessage.toLowerCase().includes('hello') || 
                userMessage.toLowerCase().includes('hi')) {
                response = "Hello! How can I assist you today?";
            } else if (userMessage.toLowerCase().includes('help')) {
                response = "I'd be happy to help. Could you please provide more details about your issue?";
            } else if (userMessage.toLowerCase().includes('thank')) {
                response = "You're welcome! Is there anything else I can help you with?";
            } else if (userMessage.toLowerCase().includes('bye')) {
                response = "Thank you for chatting with us. Have a great day!";
            } else {
                response = "Thank you for your message. Our support team will review it and get back to you shortly.";
            }
            
            // Add support response to chat
            addMessage(response, false);
        }, 1000); // 1 second delay to simulate typing
    }
    
    // Send message on button click
    $sendButton.on('click', sendMessage);
    
    // Send message on Enter key (but allow Shift+Enter for new lines)
    $messageInput.on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault(); // Prevent default to avoid new line
            sendMessage();
        }
    });
    
    // Auto-resize textarea as user types
    $messageInput.on('input', function() {
        this.style.height = 'auto';
        const newHeight = Math.min(this.scrollHeight, 100); // Max height of 100px
        this.style.height = newHeight + 'px';
    });
    
    // Toggle support status for demonstration
    $('.support-status').on('click', function() {
        const $indicator = $('.status-indicator');
        const $statusText = $('.status-text');
        
        if ($indicator.hasClass('online')) {
            $indicator.removeClass('online').addClass('offline');
            $statusText.text('Support Offline');
        } else {
            $indicator.removeClass('offline').addClass('online');
            $statusText.text('Support Online');
        }
    });
});
