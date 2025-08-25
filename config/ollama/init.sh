#!/bin/sh

# Ollama Model Initialization Script
echo "🚀 Starting Ollama model initialization..."

# Set the Ollama host
export OLLAMA_HOST=http://ollama:11434

# Function to wait for Ollama service
wait_for_ollama() {
    echo "⏳ Waiting for Ollama service to be ready..."
    local max_attempts=60
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if ollama list > /dev/null 2>&1; then
            echo "✅ Ollama service is ready!"
            return 0
        fi
        echo "  Attempt $attempt/$max_attempts - waiting for Ollama..."
        sleep 5
        attempt=$((attempt + 1))
    done
    
    echo "❌ Timeout waiting for Ollama service"
    return 1
}

# Function to pull a model with retries
pull_model() {
    local model=$1
    local max_retries=3
    local retry=1
    
    echo "📥 Pulling model: $model"
    
    while [ $retry -le $max_retries ]; do
        if ollama pull "$model"; then
            echo "✅ Successfully pulled $model"
            return 0
        else
            echo "❌ Failed to pull $model (attempt $retry/$max_retries)"
            if [ $retry -eq $max_retries ]; then
                echo "❌ Failed to pull $model after $max_retries attempts"
                return 1
            fi
            retry=$((retry + 1))
            echo "⏳ Retrying in 10 seconds..."
            sleep 10
        fi
    done
}

# Wait for Ollama to be ready
if ! wait_for_ollama; then
    exit 1
fi

# List of models to pull
models="qwen3-coder"

# Pull each model
failed_models=""
for model in $models; do
    if ! pull_model "$model"; then
        if [ -z "$failed_models" ]; then
            failed_models="$model"
        else
            failed_models="$failed_models $model"
        fi
    fi
done

# Final status
echo ""
echo "📊 Final Status:"
echo "================"

# List all available models
echo "✅ Available models:"
ollama list

if [ -n "$failed_models" ]; then
    echo ""
    echo "❌ Failed to pull these models:"
    for model in $failed_models; do
        echo "  - $model"
    done
    echo ""
    echo "🔧 You can manually pull them later with:"
    for model in $failed_models; do
        echo "  docker exec ollama_service ollama pull $model"
    done
fi

echo ""
echo "🎉 Ollama model initialization complete!"