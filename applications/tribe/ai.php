<?php
/**
 * Ollama AI Integration for Tribe
 * This file provides AI capabilities using Ollama
 */

// Check if composer autoload exists
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die('Error: Composer dependencies not installed. Please run: composer require ardagnsrn/ollama-php:^0.4');
}

require __DIR__ . '/_init.php';

// Check if Ollama package is available
if (!class_exists('ArdaGnsrn\Ollama\Ollama')) {
    die('Error: Ollama PHP package not found. Please run: composer require ardagnsrn/ollama-php:^0.4');
}

use ArdaGnsrn\Ollama\Ollama;

// Initialize Ollama client
// Detect environment and set appropriate host
$ollamaHost = 'localhost';
if (getenv('OLLAMA_HOST')) {
    $ollamaHost = getenv('OLLAMA_HOST');
} elseif (file_exists('/.dockerenv')) {
    // Running in Docker, use host.docker.internal
    $ollamaHost = 'host.docker.internal:11434';
}

$ollamaUrl = "http://{$ollamaHost}";

// Handle different request types
$action = $_GET['action'] ?? $_POST['action'] ?? 'demo';
$response = [];

try {
    // Try to initialize Ollama client
    $ollama = \ArdaGnsrn\Ollama\Ollama::client($ollamaHost);
    
    switch ($action) {
        case 'models':
            // List available models
            $models = $ollama->models()->list();
            $response = [
                'success' => true,
                'models' => $models
            ];
            break;

        case 'chat':
            // Handle chat completion
            $prompt = $_POST['prompt'] ?? $_GET['prompt'] ?? '';
            $model = $_POST['model'] ?? $_GET['model'] ?? 'llama2';
            $stream = $_POST['stream'] ?? $_GET['stream'] ?? false;
            
            if (empty($prompt)) {
                throw new Exception('Prompt is required');
            }

            // Generate completion
            $completion = $ollama->completions()->create([
                'model' => $model,
                'prompt' => $prompt,
                'stream' => $stream === 'true' || $stream === true,
                'options' => [
                    'temperature' => floatval($_POST['temperature'] ?? 0.7),
                    'top_p' => floatval($_POST['top_p'] ?? 0.9),
                ]
            ]);

            $response = [
                'success' => true,
                'response' => $completion['response'] ?? '',
                'model' => $model,
                'total_duration' => $completion['total_duration'] ?? null,
                'eval_count' => $completion['eval_count'] ?? null
            ];
            break;

        case 'chat_conversation':
            // Handle conversation with context
            $messages = json_decode($_POST['messages'] ?? '[]', true);
            $model = $_POST['model'] ?? 'llama2';
            
            if (empty($messages)) {
                throw new Exception('Messages are required for conversation');
            }

            // Create chat completion with conversation history
            $completion = $ollama->chat()->create([
                'model' => $model,
                'messages' => $messages,
                'stream' => false,
                'options' => [
                    'temperature' => floatval($_POST['temperature'] ?? 0.7),
                    'top_p' => floatval($_POST['top_p'] ?? 0.9),
                ]
            ]);

            $response = [
                'success' => true,
                'message' => $completion['message'] ?? [],
                'model' => $model,
                'total_duration' => $completion['total_duration'] ?? null
            ];
            break;

        case 'embeddings':
            // Generate embeddings for text
            $text = $_POST['text'] ?? $_GET['text'] ?? '';
            $model = $_POST['model'] ?? $_GET['model'] ?? 'llama2';
            
            if (empty($text)) {
                throw new Exception('Text is required for embeddings');
            }

            $embeddings = $ollama->embeddings()->create([
                'model' => $model,
                'prompt' => $text
            ]);

            $response = [
                'success' => true,
                'embeddings' => $embeddings['embedding'] ?? []
            ];
            break;

        case 'pull':
            // Pull a new model
            $model = $_POST['model'] ?? $_GET['model'] ?? '';
            
            if (empty($model)) {
                throw new Exception('Model name is required');
            }

            $result = $ollama->models()->pull($model);
            
            $response = [
                'success' => true,
                'message' => "Model {$model} pulled successfully",
                'result' => $result
            ];
            break;

        case 'demo':
        default:
            // Demo interface
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Tribe AI - Ollama Integration</title>
                <style>
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        min-height: 100vh;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        padding: 20px;
                    }
                    .container {
                        background: white;
                        border-radius: 20px;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                        max-width: 800px;
                        width: 100%;
                        padding: 40px;
                    }
                    h1 {
                        color: #333;
                        margin-bottom: 10px;
                        font-size: 2em;
                    }
                    .subtitle {
                        color: #666;
                        margin-bottom: 30px;
                    }
                    .info-box {
                        background: #f0f7ff;
                        border-left: 4px solid #2196F3;
                        padding: 15px;
                        margin-bottom: 20px;
                        border-radius: 5px;
                    }
                    .info-box h3 {
                        margin-top: 0;
                        color: #1976D2;
                    }
                    .info-box code {
                        background: #e3f2fd;
                        padding: 2px 6px;
                        border-radius: 3px;
                        font-family: 'Courier New', monospace;
                    }
                    .chat-container {
                        border: 2px solid #f0f0f0;
                        border-radius: 10px;
                        height: 400px;
                        overflow-y: auto;
                        padding: 20px;
                        margin-bottom: 20px;
                        background: #fafafa;
                    }
                    .message {
                        margin-bottom: 15px;
                        padding: 10px 15px;
                        border-radius: 10px;
                        max-width: 70%;
                        word-wrap: break-word;
                    }
                    .user-message {
                        background: #667eea;
                        color: white;
                        margin-left: auto;
                        text-align: right;
                    }
                    .ai-message {
                        background: #f0f0f0;
                        color: #333;
                        white-space: pre-wrap;
                    }
                    .input-group {
                        display: flex;
                        gap: 10px;
                        margin-bottom: 20px;
                    }
                    input[type="text"] {
                        flex: 1;
                        padding: 12px 20px;
                        border: 2px solid #e0e0e0;
                        border-radius: 10px;
                        font-size: 16px;
                        transition: border-color 0.3s;
                    }
                    input[type="text"]:focus {
                        outline: none;
                        border-color: #667eea;
                    }
                    button {
                        padding: 12px 30px;
                        background: #667eea;
                        color: white;
                        border: none;
                        border-radius: 10px;
                        font-size: 16px;
                        cursor: pointer;
                        transition: background 0.3s;
                    }
                    button:hover {
                        background: #764ba2;
                    }
                    button:disabled {
                        background: #ccc;
                        cursor: not-allowed;
                    }
                    .controls {
                        display: flex;
                        gap: 10px;
                        margin-bottom: 20px;
                        flex-wrap: wrap;
                    }
                    select {
                        padding: 8px 15px;
                        border: 2px solid #e0e0e0;
                        border-radius: 8px;
                        font-size: 14px;
                        background: white;
                    }
                    .loading {
                        display: none;
                        text-align: center;
                        color: #667eea;
                        padding: 10px;
                    }
                    .loading.active {
                        display: block;
                    }
                    .error {
                        background: #fee;
                        color: #c00;
                        padding: 10px;
                        border-radius: 8px;
                        margin-bottom: 10px;
                        display: none;
                    }
                    .error.active {
                        display: block;
                    }
                    .status {
                        background: #e8f5e9;
                        color: #2e7d32;
                        padding: 10px;
                        border-radius: 8px;
                        margin-bottom: 10px;
                        text-align: center;
                    }
                    .status.offline {
                        background: #ffebee;
                        color: #c62828;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>🤖 Tribe AI</h1>
                    <p class="subtitle">Powered by Ollama</p>
                    
                    <div class="info-box">
                        <h3>ℹ️ Connection Information</h3>
                        <p>Connecting to: <code><?php echo htmlspecialchars($ollamaUrl); ?></code></p>
                        <p>Host: <code><?php echo htmlspecialchars($ollamaHost); ?></code></p>
                        <p>Environment: <code><?php echo file_exists('/.dockerenv') ? 'Docker Container' : 'Host Machine'; ?></code></p>
                    </div>
                    
                    <div class="status" id="status">Checking Ollama connection...</div>
                    <div class="error" id="error"></div>
                    
                    <div class="controls">
                        <select id="model">
                            <option value="llama2">Llama 2</option>
                            <option value="llama3.2">Llama 3.2</option>
                            <option value="mistral">Mistral</option>
                            <option value="codellama">Code Llama</option>
                            <option value="phi">Phi</option>
                            <option value="gemma">Gemma</option>
                            <option value="qwen2.5-coder">Qwen 2.5 Coder</option>
                        </select>
                        <button onclick="loadModels()">Refresh Models</button>
                        <button onclick="clearChat()">Clear Chat</button>
                        <button onclick="pullModel()">Pull New Model</button>
                    </div>
                    
                    <div class="chat-container" id="chat">
                        <div class="message ai-message">Hello! I'm your AI assistant powered by Ollama. How can I help you today?</div>
                    </div>
                    
                    <div class="loading" id="loading">🔄 AI is thinking...</div>
                    
                    <div class="input-group">
                        <input type="text" id="prompt" placeholder="Type your message here..." onkeypress="if(event.key === 'Enter') sendMessage()">
                        <button onclick="sendMessage()" id="sendBtn">Send</button>
                    </div>
                </div>

                <script>
                    let messages = [
                        { role: 'assistant', content: "Hello! I'm your AI assistant powered by Ollama. How can I help you today?" }
                    ];
                    
                    async function checkConnection() {
                        const statusDiv = document.getElementById('status');
                        try {
                            const response = await fetch('ai.php?action=models');
                            const data = await response.json();
                            
                            if (data.success) {
                                statusDiv.textContent = '✅ Connected to Ollama successfully';
                                statusDiv.classList.remove('offline');
                                return true;
                            } else {
                                throw new Error(data.error || 'Connection failed');
                            }
                        } catch (error) {
                            statusDiv.textContent = '❌ Cannot connect to Ollama - Please ensure Ollama is running';
                            statusDiv.classList.add('offline');
                            console.error('Connection error:', error);
                            return false;
                        }
                    }
                    
                    async function sendMessage() {
                        const prompt = document.getElementById('prompt').value.trim();
                        if (!prompt) return;
                        
                        const model = document.getElementById('model').value;
                        const chatDiv = document.getElementById('chat');
                        const loading = document.getElementById('loading');
                        const sendBtn = document.getElementById('sendBtn');
                        const errorDiv = document.getElementById('error');
                        
                        // Clear error
                        errorDiv.classList.remove('active');
                        
                        // Add user message to chat
                        chatDiv.innerHTML += `<div class="message user-message">${escapeHtml(prompt)}</div>`;
                        document.getElementById('prompt').value = '';
                        
                        // Show loading
                        loading.classList.add('active');
                        sendBtn.disabled = true;
                        
                        // Add to messages history
                        messages.push({ role: 'user', content: prompt });
                        
                        try {
                            const response = await fetch('ai.php?action=chat_conversation', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `messages=${encodeURIComponent(JSON.stringify(messages))}&model=${model}`
                            });
                            
                            const data = await response.json();
                            
                            if (data.success && data.message) {
                                // Add AI response to chat
                                chatDiv.innerHTML += `<div class="message ai-message">${escapeHtml(data.message.content)}</div>`;
                                
                                // Add to messages history
                                messages.push(data.message);
                                
                                // Scroll to bottom
                                chatDiv.scrollTop = chatDiv.scrollHeight;
                            } else {
                                throw new Error(data.error || 'Failed to get response. Make sure the model is installed.');
                            }
                        } catch (error) {
                            errorDiv.textContent = 'Error: ' + error.message;
                            errorDiv.classList.add('active');
                            console.error('Chat error:', error);
                        } finally {
                            loading.classList.remove('active');
                            sendBtn.disabled = false;
                            document.getElementById('prompt').focus();
                        }
                    }
                    
                    async function loadModels() {
                        try {
                            const response = await fetch('ai.php?action=models');
                            const data = await response.json();
                            
                            if (data.success && data.models && data.models.models) {
                                const select = document.getElementById('model');
                                const currentValue = select.value;
                                select.innerHTML = '';
                                
                                if (data.models.models.length === 0) {
                                    select.innerHTML = '<option value="">No models installed</option>';
                                    alert('No models found. Please pull a model first using the "Pull New Model" button.');
                                    return;
                                }
                                
                                data.models.models.forEach(model => {
                                    const option = document.createElement('option');
                                    const modelName = model.name.split(':')[0];
                                    option.value = modelName;
                                    option.textContent = model.name + ' (' + formatBytes(model.size) + ')';
                                    select.appendChild(option);
                                });
                                
                                // Restore previous selection if available
                                if (currentValue && Array.from(select.options).some(opt => opt.value === currentValue)) {
                                    select.value = currentValue;
                                }
                            }
                        } catch (error) {
                            console.error('Failed to load models:', error);
                        }
                    }
                    
                    async function pullModel() {
                        const modelName = prompt('Enter model name to pull (e.g., llama2, llama3.2, mistral, codellama, gemma, phi, qwen2.5-coder):');
                        if (!modelName) return;
                        
                        const errorDiv = document.getElementById('error');
                        const loading = document.getElementById('loading');
                        
                        loading.textContent = `📥 Pulling model ${modelName}... This may take several minutes...`;
                        loading.classList.add('active');
                        errorDiv.classList.remove('active');
                        
                        try {
                            const response = await fetch('ai.php?action=pull', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `model=${modelName}`
                            });
                            
                            const data = await response.json();
                            
                            if (data.success) {
                                alert(`Model ${modelName} pulled successfully!`);
                                loadModels(); // Refresh the models list
                            } else {
                                throw new Error(data.error || 'Failed to pull model');
                            }
                        } catch (error) {
                            errorDiv.textContent = 'Error pulling model: ' + error.message;
                            errorDiv.classList.add('active');
                        } finally {
                            loading.textContent = '🔄 AI is thinking...';
                            loading.classList.remove('active');
                        }
                    }
                    
                    function clearChat() {
                        document.getElementById('chat').innerHTML = '<div class="message ai-message">Hello! I\'m your AI assistant powered by Ollama. How can I help you today?</div>';
                        messages = [
                            { role: 'assistant', content: "Hello! I'm your AI assistant powered by Ollama. How can I help you today?" }
                        ];
                    }
                    
                    function escapeHtml(text) {
                        const div = document.createElement('div');
                        div.textContent = text;
                        return div.innerHTML;
                    }
                    
                    function formatBytes(bytes) {
                        if (bytes === 0) return '0 Bytes';
                        const k = 1024;
                        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                        const i = Math.floor(Math.log(bytes) / Math.log(k));
                        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
                    }
                    
                    // Initialize on page load
                    window.onload = async function() {
                        const connected = await checkConnection();
                        if (connected) {
                            await loadModels();
                        }
                        document.getElementById('prompt').focus();
                    };
                    
                    // Check connection periodically
                    setInterval(checkConnection, 30000);
                </script>
            </body>
            </html>
            <?php
            exit;
    }

} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);