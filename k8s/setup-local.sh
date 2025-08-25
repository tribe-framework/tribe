#!/bin/bash

# Setup script for local Kubernetes development
set -e

echo "🏗️ Setting up local Kubernetes configuration..."

# Get the current project directory
PROJECT_DIR=$(pwd)

echo "📁 Project directory: $PROJECT_DIR"

# Create local manifests with absolute paths
echo "📝 Generating local manifests with absolute paths..."

# Create the local directory if it doesn't exist
mkdir -p k8s/local-generated

# Generate the PHP Tribe deployment with correct paths
sed "s|path: ./applications/tribe|path: ${PROJECT_DIR}/applications/tribe|g" k8s/local/php-tribe.yaml | \
sed "s|path: ./uploads|path: ${PROJECT_DIR}/uploads|g" | \
sed "s|path: ./logs|path: ${PROJECT_DIR}/logs|g" > k8s/local-generated/php-tribe.yaml

echo "✅ Generated k8s/local-generated/php-tribe.yaml"

# Generate the PHP Junction deployment with correct paths
sed "s|path: ./uploads|path: ${PROJECT_DIR}/uploads|g" k8s/local/php-junction.yaml | \
sed "s|path: ./logs|path: ${PROJECT_DIR}/logs|g" > k8s/local-generated/php-junction.yaml

echo "✅ Generated k8s/local-generated/php-junction.yaml"

# Generate the Nginx Tribe deployment with correct paths
sed "s|path: ./applications/tribe|path: ${PROJECT_DIR}/applications/tribe|g" k8s/local/nginx-tribe.yaml | \
sed "s|path: ./uploads|path: ${PROJECT_DIR}/uploads|g" | \
sed "s|path: ./logs|path: ${PROJECT_DIR}/logs|g" > k8s/local-generated/nginx-tribe.yaml

echo "✅ Generated k8s/local-generated/nginx-tribe.yaml"

# Generate the Nginx Junction deployment with correct paths
sed "s|path: ./uploads|path: ${PROJECT_DIR}/uploads|g" k8s/local/nginx-junction.yaml | \
sed "s|path: ./logs|path: ${PROJECT_DIR}/logs|g" > k8s/local-generated/nginx-junction.yaml

echo "✅ Generated k8s/local-generated/nginx-junction.yaml"

# Copy MySQL deployment (no path changes needed)
cp k8s/local/mysql.yaml k8s/local-generated/mysql.yaml
echo "✅ Generated k8s/local-generated/mysql.yaml"

# Update build script to use current directory context
echo "📝 Updating build script with correct image names..."

# Ask for Docker Hub username if not set
if [ -z "$DOCKER_HUB_USERNAME" ]; then
    echo ""
    read -p "Enter your Docker Hub username: " DOCKER_HUB_USERNAME
    echo ""
    echo "💡 Tip: You can set DOCKER_HUB_USERNAME environment variable to skip this step"
fi

# Update the build script with the username
sed "s/yourusername/$DOCKER_HUB_USERNAME/g" k8s/build-images.sh > k8s/build-images-local.sh
chmod +x k8s/build-images-local.sh

echo "✅ Generated k8s/build-images-local.sh"

# Also update the image names in the generated manifests
for file in k8s/local-generated/*.yaml; do
    if [ -f "$file" ]; then
        sed -i "s/yourusername/$DOCKER_HUB_USERNAME/g" "$file"
    fi
done

echo ""
echo "🎉 Local setup complete!"
echo ""
echo "📋 Next steps:"
echo "1. Build images: ./k8s/build-images-local.sh"
echo "2. Apply MySQL: kubectl apply -f k8s/local-generated/mysql.yaml"
echo "3. Apply remaining services: kubectl apply -f k8s/local-generated/"
echo "4. Check status: kubectl get pods -n tribe-junction"
echo ""
echo "🌐 Access URLs (after deployment):"
echo "  - Tribe: http://localhost:1212"
echo "  - Junction: http://localhost:4488 → http://localhost:32488"
echo ""
echo "💡 Your local files in ./applications/tribe will be directly mounted in the pods"