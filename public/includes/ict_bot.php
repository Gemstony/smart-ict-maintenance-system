<?php
// public/includes/ict_bot.php - ICT Assistance Floating Bot Widget
// This file contains the floating chat bot that appears on every page
?>

<!-- ============================================ -->
<!-- ICT ASSISTANCE - FLOATING BOT WIDGET         -->
<!-- ============================================ -->

<style>
/* ===== FLOATING BRAIN/CHAT BUTTON ===== */
.ict-float-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 99999;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1a73e8, #0d47a1);
    color: white;
    border: none;
    box-shadow: 0 4px 25px rgba(26, 115, 232, 0.45);
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}
.ict-float-btn:hover {
    transform: scale(1.12);
    box-shadow: 0 6px 35px rgba(26, 115, 232, 0.55);
}
.ict-float-btn .pulse-dot {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 13px;
    height: 13px;
    background: #4CAF50;
    border-radius: 50%;
    border: 2px solid white;
    animation: pulse-dot 2s infinite;
}
@keyframes pulse-dot {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.3); }
}

/* ===== CHAT WINDOW ===== */
.ict-chat-window {
    position: fixed;
    bottom: 100px;
    right: 30px;
    z-index: 99998;
    width: 420px;
    max-width: 92vw;
    height: 560px;
    max-height: 75vh;
    background: white;
    border-radius: 20px;
    box-shadow: 0 15px 50px rgba(0,0,0,0.25);
    display: none;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(0,0,0,0.05);
    animation: slideUp 0.35s ease;
}
.ict-chat-window.open {
    display: flex;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

/* ===== CHAT HEADER ===== */
.ict-chat-header {
    background: linear-gradient(135deg, #1a73e8, #0d47a1);
    color: white;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.ict-chat-header .header-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.ict-chat-header .header-info .avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.ict-chat-header .header-info .title {
    font-weight: 600;
    font-size: 15px;
}
.ict-chat-header .header-info .subtitle {
    font-size: 11px;
    opacity: 0.8;
}
.ict-chat-header .close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
    padding: 5px;
    border-radius: 50%;
    transition: background 0.3s;
}
.ict-chat-header .close-btn:hover {
    background: rgba(255,255,255,0.15);
}

/* ===== CHAT BODY ===== */
.ict-chat-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px 18px;
    background: #f8f9fa;
    scroll-behavior: smooth;
}
.ict-chat-body::-webkit-scrollbar {
    width: 5px;
}
.ict-chat-body::-webkit-scrollbar-track {
    background: #f1f1f1;
}
.ict-chat-body::-webkit-scrollbar-thumb {
    background: #c1c7cd;
    border-radius: 10px;
}

/* ===== MESSAGES ===== */
.msg {
    display: flex;
    gap: 10px;
    margin-bottom: 14px;
    animation: msgFade 0.3s ease;
}
.msg.bot { flex-direction: row; }
.msg.user { flex-direction: row-reverse; }
.msg .avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}
.msg.bot .avatar {
    background: #1a73e8;
    color: white;
}
.msg.user .avatar {
    background: #e9ecef;
    color: #495057;
}
.msg .bubble {
    max-width: 80%;
    padding: 10px 16px;
    border-radius: 14px;
    font-size: 14px;
    line-height: 1.6;
    word-wrap: break-word;
    white-space: pre-wrap;
}
.msg.bot .bubble {
    background: white;
    color: #212529;
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.msg.user .bubble {
    background: #1a73e8;
    color: white;
    border-bottom-right-radius: 4px;
}
.msg .bubble ul, .msg .bubble ol {
    margin: 6px 0 6px 18px;
}
.msg .bubble p {
    margin-bottom: 6px;
}
.msg .bubble p:last-child { margin-bottom: 0; }
.msg .bubble code {
    background: #f1f3f4;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 13px;
}
.msg .bubble pre {
    background: #f1f3f4;
    padding: 10px;
    border-radius: 6px;
    overflow-x: auto;
    margin: 6px 0;
}
.msg .bubble pre code {
    background: transparent;
    padding: 0;
}
@keyframes msgFade {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ===== TYPING INDICATOR ===== */
.typing-indicator {
    display: none;
    align-items: center;
    gap: 10px;
    padding: 6px 0;
}
.typing-indicator.active { display: flex; }
.typing-dots {
    display: flex;
    gap: 5px;
    background: white;
    padding: 10px 16px;
    border-radius: 14px;
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.typing-dots span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #1a73e8;
    animation: typing 1.4s infinite both;
}
.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
    30% { transform: translateY(-6px); opacity: 1; }
}

/* ===== CHAT FOOTER ===== */
.ict-chat-footer {
    padding: 12px 16px;
    background: white;
    border-top: 1px solid #e9ecef;
    flex-shrink: 0;
}
.ict-chat-footer form {
    display: flex;
    gap: 10px;
}
.ict-chat-footer input[type="text"] {
    flex: 1;
    padding: 10px 14px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.3s;
}
.ict-chat-footer input[type="text"]:focus {
    border-color: #1a73e8;
}
.ict-chat-footer input[type="text"]::placeholder {
    color: #adb5bd;
}
.ict-chat-footer button {
    padding: 10px 18px;
    background: #1a73e8;
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    cursor: pointer;
    transition: background 0.3s;
}
.ict-chat-footer button:hover {
    background: #1557b0;
}
.ict-chat-footer button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.ict-chat-footer .lang-toggle {
    background: none;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 4px 12px;
    font-size: 12px;
    cursor: pointer;
    color: #495057;
    transition: all 0.3s;
}
.ict-chat-footer .lang-toggle:hover {
    background: #f1f3f4;
    border-color: #1a73e8;
}
.ict-chat-footer .lang-toggle.active {
    background: #1a73e8;
    color: white;
    border-color: #1a73e8;
}
.ict-chat-footer .footer-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 6px;
}
.ict-chat-footer .footer-actions small {
    color: #adb5bd;
    font-size: 11px;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 480px) {
    .ict-chat-window {
        bottom: 85px;
        right: 10px;
        left: 10px;
        max-width: none;
        height: 65vh;
        border-radius: 16px;
    }
    .ict-float-btn {
        bottom: 20px;
        right: 20px;
        width: 55px;
        height: 55px;
        font-size: 24px;
    }
    .ict-chat-header { padding: 12px 16px; }
    .ict-chat-body { padding: 12px 14px; }
    .msg .bubble { font-size: 13px; max-width: 92%; }
    .ict-chat-footer input[type="text"] { font-size: 13px; padding: 8px 12px; }
    .ict-chat-footer button { padding: 8px 14px; font-size: 13px; }
}
</style>

<!-- ===== FLOATING BUTTON ===== -->
<button class="ict-float-btn" id="ictChatToggle" aria-label="Open ICT Assistance">
    <i class="fas fa-brain"></i>
    <span class="pulse-dot"></span>
</button>

<!-- ===== CHAT WINDOW ===== -->
<div class="ict-chat-window" id="ictChatWindow">
    <div class="ict-chat-header">
        <div class="header-info">
            <div class="avatar"><i class="fas fa-robot"></i></div>
            <div>
                <div class="title">ICT Assistance</div>
                <div class="subtitle">🤖 Online • AI Assistant</div>
            </div>
        </div>
        <button class="close-btn" id="ictChatClose" aria-label="Close chat">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="ict-chat-body" id="ictChatBody">
        <!-- Welcome Message -->
        <div class="msg bot">
            <div class="avatar"><i class="fas fa-robot"></i></div>
            <div class="bubble">
                <strong>👋 Hello! I'm ICT Assistance</strong>
             
                <em>Ask me anything about the ICT Asset Management System!</em>
            </div>
        </div>

        <!-- Typing Indicator -->
        <div class="typing-indicator" id="ictTypingIndicator">
            <div class="avatar" style="background:#1a73e8;color:white;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;"><i class="fas fa-robot"></i></div>
            <div class="typing-dots">
                <span></span><span></span><span></span>
            </div>
        </div>
    </div>

    <div class="ict-chat-footer">
        <form id="ictChatForm">
            <input type="text" id="ictUserInput" placeholder="Ask me anything..." autofocus>
            <button type="submit" id="ictSendBtn"><i class="fas fa-paper-plane"></i></button>
        </form>
        <div class="footer-actions">
            <small>Type your question...</small>
            <button class="lang-toggle" id="ictLangToggle" data-lang="en">🇬🇧 EN</button>
        </div>
    </div>
</div>

<!-- ===== SCRIPTS ===== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== DOM REFS =====
    const chatToggle = document.getElementById('ictChatToggle');
    const chatWindow = document.getElementById('ictChatWindow');
    const chatClose = document.getElementById('ictChatClose');
    const chatBody = document.getElementById('ictChatBody');
    const chatForm = document.getElementById('ictChatForm');
    const userInput = document.getElementById('ictUserInput');
    const sendBtn = document.getElementById('ictSendBtn');
    const typingIndicator = document.getElementById('ictTypingIndicator');
    const langToggle = document.getElementById('ictLangToggle');

    let currentLang = 'en';
    let isOpen = false;

    // ===== TOGGLE CHAT =====
    function toggleChat() {
        isOpen = !isOpen;
        chatWindow.classList.toggle('open', isOpen);
        if (isOpen) {
            userInput.focus();
        }
    }

    chatToggle.addEventListener('click', toggleChat);
    chatClose.addEventListener('click', toggleChat);

    // ===== LANGUAGE TOGGLE =====
    langToggle.addEventListener('click', function() {
        currentLang = currentLang === 'en' ? 'sw' : 'en';
        this.textContent = currentLang === 'en' ? '🇬🇧 EN' : '🇹🇿 SW';
        this.classList.toggle('active');
        userInput.placeholder = currentLang === 'en' ? 'Ask me anything...' : 'Uliza swali lolote...';
    });

    // ===== SEND MESSAGE =====
    chatForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const message = userInput.value.trim();
        if (!message) return;
        
        // Add user message
        addMessage(message, 'user');
        userInput.value = '';
        userInput.focus();
        
        // Show typing
        typingIndicator.classList.add('active');
        sendBtn.disabled = true;
        scrollToBottom();
        
        try {
            const formData = new URLSearchParams();
            formData.append('message', message);
            formData.append('language', currentLang);
            formData.append('session_id', '<?php echo session_id(); ?>');
            
            const response = await fetch('../../api/ict_bot_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            });
            
            const data = await response.json();
            
            typingIndicator.classList.remove('active');
            sendBtn.disabled = false;
            
            if (data.error) {
                addMessage('❌ ' + data.error, 'bot');
            } else {
                addMessage(data.response, 'bot');
            }
            
        } catch (error) {
            typingIndicator.classList.remove('active');
            sendBtn.disabled = false;
            addMessage('❌ Network error. Please try again.', 'bot');
        }
        
        scrollToBottom();
    });

    // ===== ADD MESSAGE =====
    function addMessage(text, sender) {
        const div = document.createElement('div');
        div.className = `msg ${sender}`;
        
        const avatar = document.createElement('div');
        avatar.className = 'avatar';
        avatar.innerHTML = sender === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-robot"></i>';
        
        const bubble = document.createElement('div');
        bubble.className = 'bubble';
        
        let processed = text;
        processed = processed.replace(/\n/g, '<br>');
        processed = processed.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        processed = processed.replace(/\*(.*?)\*/g, '<em>$1</em>');
        processed = processed.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        bubble.innerHTML = processed;
        
        div.appendChild(avatar);
        div.appendChild(bubble);
        
        chatBody.insertBefore(div, typingIndicator);
        scrollToBottom();
    }

    // ===== SCROLL =====
    function scrollToBottom() {
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    // ===== KEYBOARD SHORTCUTS =====
    userInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
        if (e.key === 'Escape') {
            if (isOpen) toggleChat();
        }
    });

    // ===== GLOBAL KEYBOARD SHORTCUT =====
    document.addEventListener('keydown', function(e) {
        // Ctrl+Alt+I to toggle chat
        if (e.ctrlKey && e.altKey && e.key === 'i') {
            e.preventDefault();
            toggleChat();
        }
    });

    // ===== CLOSE ON CLICK OUTSIDE =====
    document.addEventListener('click', function(e) {
        if (isOpen) {
            const isClickInside = chatWindow.contains(e.target) || chatToggle.contains(e.target);
            if (!isClickInside) {
                toggleChat();
            }
        }
    });

    console.log('✅ ICT Assistance Bot loaded!');
    console.log('💡 Press Ctrl+Alt+I to toggle chat');
});
</script>