#!/bin/bash

# Build script for all custom Docker images
set -e

echo "🏗️ Building custom Docker images for Kubernetes..."

# Get Docker Hub username (you can set this as environment variable or replace with your username)
DOCKER_HUB_USERNAME=${DOCKER_HUB_USERNAME:-"yourusername"}

# Define image names and versions
SETUP_IMAGE="$DOCKER_HUB_USERNAME/tribe-junction-setup:latest"
TRIBE_IMAGE="$DOCKER_HUB_USERNAME/tribe-junction-php-tribe:latest"
JUNCTION_IMAGE="$DOCKER_HUB_USERNAME/tribe-junction-php-junction:latest"

# Check if we should push to Docker Hub
PUSH_TO_HUB=${1:-false}

echo ""
echo "📦 Building setup image..."
docker build -t $SETUP_IMAGE ./k8s/images/setup/

echo ""
echo "📦 Building Tribe PHP image..."
# Copy php.ini from your config/tribe/ directory
cp ./config/tribe/php.ini ./k8s/images/tribe/
docker build -t $TRIBE_IMAGE ./k8s/images/tribe/
rm ./k8s/images/tribe/php.ini

echo ""
echo "📦 Building Junction PHP image..."
# Copy php.ini from your config/junction/ directory  
cp ./config/junction/php.ini ./k8s/images/junction/
docker build -t $JUNCTION_IMAGE ./k8s/images/junction/
rm ./k8s/images/junction/php.ini

echo ""
echo "✅ All images built successfully!"

# Push to Docker Hub if requested
if [ "$PUSH_TO_HUB" = "push" ]; then
    echo ""
    echo "🚀 Pushing images to Docker Hub..."
    echo "Make sure you're logged in with 'docker login'"
    
    docker push $SETUP_IMAGE
    docker push $TRIBE_IMAGE
    docker push $JUNCTION_IMAGE
    
    echo "✅ All images pushed to Docker Hub!"
fi

echo ""
echo "📋 Built images:"
echo "  - $SETUP_IMAGE"
echo "  - $TRIBE_IMAGE" 
echo "  - $JUNCTION_IMAGE"
echo ""
echo "💡 Usage:"
echo "  Local build only: ./k8s/build-images.sh"
echo "  Build and push:   ./k8s/build-images.sh push"
echo ""
echo "🚀 You can now deploy to Kubernetes!"