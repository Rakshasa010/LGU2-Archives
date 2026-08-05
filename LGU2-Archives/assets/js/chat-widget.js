const ChatWidget = {
    init() {
        this.createWidget();
        this.attachEvents();
    },

    createWidget() {
        const widgetHTML = `
            <div id="chat-widget-container" class="fixed bottom-6 right-6 z-50">
                <!-- Toggle Button -->
                <button id="chat-toggle-btn" class="w-14 h-14 rounded-full bg-red-600 hover:bg-red-700 text-white shadow-xl flex items-center justify-center transition-all duration-300 hover:scale-110 drop-shadow-lg shadow-red-900/30">
                    <i class="bi bi-chat-dots-fill text-2xl"></i>
                </button>
                
                <!-- Chat Window -->
                <div id="chat-window" class="hidden absolute bottom-16 right-0 w-80 md:w-96 bg-white dark:bg-slate-800 rounded-2xl shadow-[0_25px_70px_-15px_rgba(0,0,0,0.45)] border border-gray-200 dark:border-slate-700 overflow-hidden flex flex-col" style="height: 500px;">
                    <!-- Chat Header -->
                    <div class="bg-red-600 text-white p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                                <i class="bi bi-robot text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold">Archive Assistant</h3>
                                <p class="text-xs opacity-80">Online</p>
                            </div>
                        </div>
                        <button id="chat-close-btn" class="text-white hover:text-gray-200">
                            <i class="bi bi-x-lg text-xl"></i>
                        </button>
                    </div>
                    
                    <!-- Chat Messages -->
                    <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50 dark:bg-slate-900">
                        <div class="flex gap-3">
                            <div class="w-8 h-8 rounded-full bg-red-600 flex items-center justify-center text-white text-sm">
                                <i class="bi bi-robot"></i>
                            </div>
                            <div class="bg-white dark:bg-slate-800 rounded-2xl rounded-tl-sm p-3 shadow-sm border border-gray-200 dark:border-slate-700 max-w-[80%]">
                                <p class="text-sm text-gray-800 dark:text-gray-200">
                                    Hi! I'm your Archive Assistant. Ask me about document versions or to find files!
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chat Input -->
                    <div class="p-4 bg-white dark:bg-slate-800 border-t border-gray-200 dark:border-slate-700">
                        <div class="flex gap-2">
                            <input id="chat-input" type="text" placeholder="Ask about documents..." 
                                class="flex-1 px-4 py-2 rounded-full border border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-900 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-red-500">
                            <button id="chat-send-btn" class="w-10 h-10 rounded-full bg-red-600 hover:bg-red-700 text-white flex items-center justify-center transition-colors">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', widgetHTML);
    },

    attachEvents() {
        const toggleBtn = document.getElementById('chat-toggle-btn');
        const closeBtn = document.getElementById('chat-close-btn');
        const chatWindow = document.getElementById('chat-window');
        const sendBtn = document.getElementById('chat-send-btn');
        const chatInput = document.getElementById('chat-input');
        
        toggleBtn.addEventListener('click', () => {
            chatWindow.classList.toggle('hidden');
            toggleBtn.classList.toggle('hidden');
        });
        
        closeBtn.addEventListener('click', () => {
            chatWindow.classList.add('hidden');
            toggleBtn.classList.remove('hidden');
        });
        
        sendBtn.addEventListener('click', () => this.sendMessage());
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.sendMessage();
        });
    },

    addMessage(text, isUser = false) {
        const messagesContainer = document.getElementById('chat-messages');
        const messageHTML = `
            <div class="flex gap-3 ${isUser ? 'flex-row-reverse' : ''}">
                <div class="w-8 h-8 rounded-full ${isUser ? 'bg-blue-600' : 'bg-red-600'} flex items-center justify-center text-white text-sm flex-shrink-0">
                    <i class="bi ${isUser ? 'bi-person' : 'bi-robot'}"></i>
                </div>
                <div class="bg-${isUser ? 'blue-600 text-white' : 'white dark:bg-slate-800'} rounded-2xl ${isUser ? 'rounded-tr-sm' : 'rounded-tl-sm'} p-3 shadow-sm ${isUser ? '' : 'border border-gray-200 dark:border-slate-700'} max-w-[80%]">
                    <p class="text-sm ${isUser ? '' : 'text-gray-800 dark:text-gray-200'}">
                        ${text}
                    </p>
                </div>
            </div>
        `;
        messagesContainer.insertAdjacentHTML('beforeend', messageHTML);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    },

    async sendMessage() {
        const chatInput = document.getElementById('chat-input');
        const message = chatInput.value.trim();
        
        if (!message) return;
        
        this.addMessage(message, true);
        chatInput.value = '';
        
        // Show typing indicator
        const messagesContainer = document.getElementById('chat-messages');
        const typingHTML = `
            <div id="typing-indicator" class="flex gap-3">
                <div class="w-8 h-8 rounded-full bg-red-600 flex items-center justify-center text-white text-sm flex-shrink-0">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl rounded-tl-sm p-3 shadow-sm border border-gray-200 dark:border-slate-700">
                    <div class="flex gap-1">
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                    </div>
                </div>
            </div>
        `;
        messagesContainer.insertAdjacentHTML('beforeend', typingHTML);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
        try {
            console.log('Sending request to api/chat-api.php with message:', message);
            const response = await fetch('api/chat-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message })
            });
            
            console.log('Response status:', response.status, response.statusText);
            const responseText = await response.text(); // First get as text to debug
            console.log('Raw response text:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Failed to parse JSON:', e);
                data = { success: false, error: 'Invalid JSON response: ' + responseText };
            }
            
            console.log('API Response (parsed):', data);
            
            // Remove typing indicator
            document.getElementById('typing-indicator').remove();
            
            if (data.success) {
                this.addMessage(data.response, false);
            } else {
                this.addMessage('Sorry, I encountered an error: ' + (data.error || 'Unknown error'), false);
            }
        } catch (error) {
            // Remove typing indicator
            document.getElementById('typing-indicator').remove();
            console.error('Fetch Error (full):', error);
            console.error('Error stack:', error.stack);
            this.addMessage('Sorry, I encountered an error: ' + error.message, false);
        }
    }
};

// Initialize the chat widget when the DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ChatWidget.init());
} else {
    ChatWidget.init();
}
