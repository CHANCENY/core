$(document).ready(function() {
    // DOM Elements
    const $messageInput = $('#supportMessageInput');
    const $sendButton = $('#supportSendButton');
    const $chatMessages = $('#supportChatMessages');
    const $userList = $('#userList');
    const $userEntries = $('.user-entry');
    const $searchInput = $('#searchUsers');
    
    // Chat data storage - In a real application, this would come from a database
    const chatData = {
        user1: {
            name: "John Doe",
            email: "john.doe@example.com",
            status: "online",
            avatar: "https://via.placeholder.com/40",
            messages: [
                { sender: "user", text: "Hello, I need help with my recent order #12345.", time: "10:05 AM" },
                { sender: "support", text: "Hi John, I'd be happy to help you with your order. Could you please provide more details about the issue you're experiencing?", time: "10:07 AM" },
                { sender: "user", text: "I ordered a laptop last week, but I haven't received any shipping confirmation yet.", time: "10:10 AM" },
                { sender: "support", text: "I understand your concern. Let me check the status of your order right away.", time: "10:12 AM" },
                { sender: "support", text: "I've checked our system, and it looks like your order is currently being processed. It should ship within the next 24 hours, and you'll receive an email confirmation once it's on its way.", time: "10:15 AM" },
                { sender: "user", text: "Thanks for your help!", time: "10:30 AM" }
            ],
            unread: 0
        },
        user2: {
            name: "Jane Smith",
            email: "jane.smith@example.com",
            status: "online",
            avatar: "https://via.placeholder.com/40",
            messages: [
                { sender: "user", text: "Hi there, I'm having an issue with my account.", time: "10:00 AM" },
                { sender: "support", text: "Hello Jane, I'm sorry to hear that. What seems to be the problem?", time: "10:02 AM" },
                { sender: "user", text: "I can't reset my password. The reset link isn't being sent to my email.", time: "10:05 AM" },
                { sender: "user", text: "I've tried multiple times but nothing is coming through.", time: "10:06 AM" },
                { sender: "user", text: "I'm having an issue with...", time: "10:15 AM" }
            ],
            unread: 3
        },
        user3: {
            name: "Robert Johnson",
            email: "robert.johnson@example.com",
            status: "offline",
            avatar: "https://via.placeholder.com/40",
            messages: [
                { sender: "user", text: "Hello, I placed an order yesterday and I'm wondering when it will arrive.", time: "Yesterday" },
                { sender: "support", text: "Hi Robert, thank you for your order. Let me check the estimated delivery date for you.", time: "Yesterday" },
                { sender: "support", text: "Based on your location, your order should arrive within 3-5 business days.", time: "Yesterday" },
                { sender: "user", text: "When will my order arrive?", time: "Yesterday" }
            ],
            unread: 0
        }
    };
    
    // Current active chat
    let currentChat = "user1";
    
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
    
    // Function to load chat for a specific user
    function loadChat(userId) {
        // Update current chat
        currentChat = userId;
        
        // Clear chat messages
        $chatMessages.empty();
        
        // Add timestamp
        $chatMessages.append('<div class="message-timestamp">Today</div>');
        
        // Get user data
        const userData = chatData[userId];
        
        // Update header with user info
        $('.current-user-info .user-avatar img').attr('src', userData.avatar);
        $('.current-user-info .user-status').removeClass('online offline').addClass(userData.status);
        $('.current-user-info .user-name').text(userData.name);
        $('.current-user-info .user-email').text(userData.email);
        
        // Add messages
        userData.messages.forEach(message => {
            const messageType = message.sender === 'user' ? 'user-message' : 'support-message';
            
            const messageHTML = `
                <div class="message ${messageType}">
                    <div class="message-content">
                        <p>${message.text}</p>
                    </div>
                    <div class="message-time">${message.time}</div>
                </div>
            `;
            
            $chatMessages.append(messageHTML);
        });
        
        // Scroll to bottom of chat
        $chatMessages.scrollTop($chatMessages[0].scrollHeight);
        
        // Reset unread count
        userData.unread = 0;
        $(`.user-entry[data-user-id="${userId}"] .unread-count`).text('');
        
        // Update active user in sidebar
        $('.user-entry').removeClass('active');
        $(`.user-entry[data-user-id="${userId}"]`).addClass('active');
    }
    
    // Function to add a new message to the current chat
    function addMessage(message, isSupport = true) {
        const messageType = isSupport ? 'support-message' : 'user-message';
        const currentTime = getCurrentTime();
        
        // Add message to UI
        const messageHTML = `
            <div class="message ${messageType}">
                <div class="message-content">
                    <p>${message}</p>
                </div>
                <div class="message-time">${currentTime}</div>
            </div>
        `;
        
        $chatMessages.append(messageHTML);
        
        // Add message to data store
        chatData[currentChat].messages.push({
            sender: isSupport ? 'support' : 'user',
            text: message,
            time: currentTime
        });
        
        // Update last message in sidebar
        $(`.user-entry[data-user-id="${currentChat}"] .user-last-message`).text(message);
        $(`.user-entry[data-user-id="${currentChat}"] .message-time`).text(currentTime);
        
        // Scroll to bottom of chat
        $chatMessages.scrollTop($chatMessages[0].scrollHeight);
    }
    
    // Function to handle sending a message
    function sendMessage() {
        const message = $messageInput.val().trim();
        
        if (message !== '') {
            // Add support message to chat
            addMessage(message, true);
            
            // Clear input field
            $messageInput.val('');
            toggleSendButton();
            
            // In a real application, this would send the message to the server
            // and potentially trigger notifications for the user
        }
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
    
    // Handle user selection in sidebar
    $userList.on('click', '.user-entry', function() {
        const userId = $(this).data('user-id');
        loadChat(userId);
    });
    
    // Search functionality
    $searchInput.on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        // Filter user list
        $('.user-entry').each(function() {
            const userId = $(this).data('user-id');
            const userData = chatData[userId];
            const userName = userData.name.toLowerCase();
            const userEmail = userData.email.toLowerCase();
            const lastMessage = userData.messages[userData.messages.length - 1].text.toLowerCase();
            
            if (userName.includes(searchTerm) || 
                userEmail.includes(searchTerm) || 
                lastMessage.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Simulate receiving a new message
    function simulateNewMessage() {
        // Randomly select a user that isn't the current one
        const availableUsers = Object.keys(chatData).filter(id => id !== currentChat);
        const randomUser = availableUsers[Math.floor(Math.random() * availableUsers.length)];
        
        // Create a random message
        const messages = [
            "Hi, I have a question about my order.",
            "Is there any update on my support ticket?",
            "I need help with my account settings.",
            "Can you help me with a refund?",
            "I'm having trouble logging in."
        ];
        const randomMessage = messages[Math.floor(Math.random() * messages.length)];
        
        // Add message to data
        const currentTime = getCurrentTime();
        chatData[randomUser].messages.push({
            sender: 'user',
            text: randomMessage,
            time: currentTime
        });
        
        // Update UI
        chatData[randomUser].unread += 1;
        $(`.user-entry[data-user-id="${randomUser}"] .user-last-message`).text(randomMessage);
        $(`.user-entry[data-user-id="${randomUser}"] .message-time`).text(currentTime);
        $(`.user-entry[data-user-id="${randomUser}"] .unread-count`).text(chatData[randomUser].unread);
        
        // Move user to top of list (in a real app)
        // This would reorder the DOM elements
    }
    
    // Demo: Simulate new message every 30 seconds
    // In a real app, this would be replaced with websocket or polling
    // setInterval(simulateNewMessage, 30000);
    
    // Initialize with first user's chat
    loadChat('user1');
    
    // Handle close chat button
    $('.header-actions .fa-times').parent().on('click', function() {
        // In a real app, this might archive the conversation or mark it as closed
        alert('This would close the current chat in a real application.');
    });
    
    // Handle user info button
    $('.header-actions .fa-info-circle').parent().on('click', function() {
        // In a real app, this might show a modal with detailed user information
        alert(`User Information:\nName: ${chatData[currentChat].name}\nEmail: ${chatData[currentChat].email}\nStatus: ${chatData[currentChat].status}`);
    });
});
