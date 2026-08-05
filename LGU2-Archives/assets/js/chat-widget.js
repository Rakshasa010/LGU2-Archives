/* Archive Assistant — Gemini-powered chat widget for the LAS system.
 * Streams responses from api/ai-assistant.php over SSE, renders Markdown,
 * keeps multi-turn history, and shows a loading indicator while waiting. */
const ArchiveAssistant = {
    history: [],
    streaming: false,

    init() {
        if (document.getElementById('chat-widget-container')) return;
        this.createWidget();
        this.attachEvents();
    },

    createWidget() {
        const html = `
            <div id="chat-widget-container" class="fixed bottom-6 right-6 z-50">
                <button id="chat-toggle-btn" class="w-14 h-14 rounded-full bg-red-600 hover:bg-red-700 text-white shadow-xl flex items-center justify-center transition-all duration-300 hover:scale-110" title="Open Archive Assistant">
                    <i class="bi bi-robot text-2xl"></i>
                </button>

                <div id="chat-window" class="hidden absolute bottom-16 right-0 w-80 md:w-96 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 overflow-hidden flex flex-col" style="height: 520px;">
                    <div class="bg-red-600 text-white p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                                <i class="bi bi-robot text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold">Archive Assistant</h3>
                                <p class="text-xs opacity-80">Gemini 2.5 Flash</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button id="chat-clear-btn" class="hover:text-gray-200" title="Start new chat">
                                <i class="bi bi-arrow-counterclockwise text-lg"></i>
                            </button>
                            <button id="chat-close-btn" class="hover:text-gray-200">
                                <i class="bi bi-x-lg text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50 dark:bg-slate-900">
                        <div class="flex gap-3">
                            <div class="w-8 h-8 rounded-full bg-red-600 flex items-center justify-center text-white text-sm flex-shrink-0">
                                <i class="bi bi-robot"></i>
                            </div>
                            <div class="bg-white dark:bg-slate-800 rounded-2xl rounded-tl-sm p-3 shadow-sm border border-gray-200 dark:border-slate-700 max-w-[80%]">
                                <p class="text-sm text-gray-800 dark:text-gray-200">
                                    Hi! I'm your Archive Assistant. Ask me about document versions or to find files!
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-white dark:bg-slate-800 border-t border-gray-200 dark:border-slate-700">
                        <div class="flex gap-2">
                            <input id="chat-input" type="text" placeholder="Ask about documents..." autocomplete="off"
                                class="flex-1 px-4 py-2 rounded-full border border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-900 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-red-500">
                            <button id="chat-send-btn" class="w-10 h-10 rounded-full bg-red-600 hover:bg-red-700 text-white flex items-center justify-center transition-colors" title="Send">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-2 text-center">Responses are AI-generated — verify against official records.</p>
                    </div>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    },

    attachEvents() {
        this.toggleBtn = document.getElementById('chat-toggle-btn');
        this.closeBtn = document.getElementById('chat-close-btn');
        this.clearBtn = document.getElementById('chat-clear-btn');
        this.window = document.getElementById('chat-window');
        this.sendBtn = document.getElementById('chat-send-btn');
        this.input = document.getElementById('chat-input');
        this.messagesEl = document.getElementById('chat-messages');

        this.toggleBtn.addEventListener('click', () => {
            this.window.classList.toggle('hidden');
            this.toggleBtn.classList.toggle('hidden');
            if (!this.window.classList.contains('hidden')) this.input.focus();
        });
        this.closeBtn.addEventListener('click', () => {
            this.window.classList.add('hidden');
            this.toggleBtn.classList.remove('hidden');
        });
        this.clearBtn.addEventListener('click', () => {
            if (this.streaming) return;
            this.history = [];
            const existing = this.messagesEl.querySelectorAll('[data-ai-msg]');
            existing.forEach(el => el.remove());
            this.addMessage('Chat cleared. Ask me something new!', false, false);
        });
        this.sendBtn.addEventListener('click', () => this.sendMessage());
        this.input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.sendMessage();
        });
    },

    addMessage(text, isUser = false, renderMarkdown = true) {
        const bubble = document.createElement('div');
        bubble.className = 'flex gap-3 ' + (isUser ? 'flex-row-reverse' : '');
        bubble.setAttribute('data-ai-msg', '1');
        bubble.innerHTML = `
            <div class="w-8 h-8 rounded-full ${isUser ? 'bg-blue-600' : 'bg-red-600'} flex items-center justify-center text-white text-sm flex-shrink-0">
                <i class="bi ${isUser ? 'bi-person' : 'bi-robot'}"></i>
            </div>
            <div class="rounded-2xl ${isUser ? 'rounded-tr-sm bg-blue-600 text-white' : 'rounded-tl-sm bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700'} p-3 shadow-sm max-w-[80%] ${renderMarkdown ? 'ai-md' : ''}"></div>`;
        const contentEl = bubble.querySelector('div:last-child');
        if (isUser || !renderMarkdown) {
            contentEl.textContent = text;
        } else {
            this.renderMarkdown(contentEl, text);
        }
        this.messagesEl.appendChild(bubble);
        this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
        return contentEl;
    },

    renderMarkdown(el, text) {
        if (window.marked && window.DOMPurify) {
            el.innerHTML = window.DOMPurify.sanitize(window.marked.parse(text || ''));
        } else {
            el.classList.add('whitespace-pre-wrap');
            el.textContent = text;
        }
    },

    showTyping() {
        const div = document.createElement('div');
        div.className = 'flex gap-3';
        div.setAttribute('data-ai-typing', '1');
        div.innerHTML = `
            <div class="w-8 h-8 rounded-full bg-red-600 flex items-center justify-center text-white text-sm flex-shrink-0">
                <i class="bi bi-robot"></i>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl rounded-tl-sm p-3 shadow-sm border border-gray-200 dark:border-slate-700">
                <div class="flex gap-1">
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                </div>
            </div>`;
        this.messagesEl.appendChild(div);
        this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
    },

    removeTyping() {
        const t = this.messagesEl.querySelector('[data-ai-typing]');
        if (t) t.remove();
    },

    async ensureLibs() {
        if (window.marked && window.DOMPurify) return;
        const load = (src) => new Promise((resolve) => {
            const s = document.createElement('script');
            s.src = src;
            s.onload = resolve;
            s.onerror = resolve; // degrade gracefully to plain text
            document.head.appendChild(s);
        });
        if (!window.DOMPurify) await load('https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js');
        if (!window.marked) await load('https://cdn.jsdelivr.net/npm/marked@13.0.3/marked.min.js');
    },

    async sendMessage() {
        const message = this.input.value.trim();
        if (!message || this.streaming) return;

        this.input.value = '';
        this.addMessage(message, true, false);
        this.history.push({ role: 'user', parts: [{ text: message }] });
        this.showTyping();
        this.streaming = true;

        try {
            await this.ensureLibs();

            const res = await fetch('api/ai-assistant.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message, history: this.history.slice(-20), stream: true })
            });

            const contentType = res.headers.get('content-type') || '';

            // JSON (error or non-streaming fallback)
            if (contentType.includes('application/json')) {
                let data;
                try { data = await res.json(); } catch { data = null; }
                this.removeTyping();
                if (data && data.success) {
                    this.renderMarkdown(this.addMessage(data.response || '', false), data.response || '');
                    this.history.push({ role: 'model', parts: [{ text: data.response || '' }] });
                } else {
                    this.showError(data && data.error ? data.error : ('Request failed (HTTP ' + res.status + ')'));
                }
                this.streaming = false;
                return;
            }

            if (!res.ok || !res.body) {
                this.removeTyping();
                this.showError('Request failed (HTTP ' + res.status + ')');
                this.streaming = false;
                return;
            }

            // SSE stream
            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let assistantEl = null;
            let full = '';
            let errored = false;

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });

                let idx;
                while ((idx = buffer.indexOf('\n\n')) !== -1) {
                    const block = buffer.slice(0, idx);
                    buffer = buffer.slice(idx + 2);
                    for (const line of block.split('\n')) {
                        if (!line.startsWith('data:')) continue;
                        const json = line.slice(5).trim();
                        if (!json) continue;
                        let evt;
                        try { evt = JSON.parse(json); } catch { continue; }

                        if (evt.error) {
                            this.removeTyping();
                            if (assistantEl) assistantEl.remove();
                            this.showError(evt.error);
                            errored = true;
                            continue;
                        }
                        if (evt.delta) {
                            if (!assistantEl) {
                                this.removeTyping();
                                assistantEl = this.addMessage('', false);
                            }
                            full += evt.delta;
                            this.renderMarkdown(assistantEl, full);
                            this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
                        }
                        if (evt.done) break;
                    }
                }
            }

            this.removeTyping();
            if (!errored) {
                if (assistantEl && full) {
                    this.renderMarkdown(assistantEl, full);
                    this.history.push({ role: 'model', parts: [{ text: full }] });
                } else if (!full) {
                    this.showError('The assistant returned an empty response.');
                }
            }
        } catch (error) {
            this.removeTyping();
            this.showError('Network error: ' + (error && error.message ? error.message : 'Unable to reach the assistant.'));
        } finally {
            this.streaming = false;
            this.input.focus();
        }
    },

    showError(message) {
        this.addMessage('Sorry, I encountered an error: ' + message, false, false);
    }
};

(function () {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ArchiveAssistant.init());
    } else {
        ArchiveAssistant.init();
    }
})();
