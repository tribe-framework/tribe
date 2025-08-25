#!/bin/bash

# Setup script to download phpMyAdmin and Junction
set -e

echo "🚀 Setting up development environment..."

# Create directories
mkdir -p /shared/applications
mkdir -p /shared/uploads

# Download phpMyAdmin
echo "📦 Downloading phpMyAdmin..."
if [ -d "/shared/applications/phpmyadmin" ]; then
    echo "🗑️ Removing existing phpmyadmin directory..."
    rm -rf /shared/applications/phpmyadmin
fi
    
curl -L https://files.phpmyadmin.net/phpMyAdmin/5.2.2/phpMyAdmin-5.2.2-all-languages.tar.gz -o pma.tar.gz
mkdir -p /shared/applications/phpmyadmin
tar -xzf pma.tar.gz -C /shared/applications/phpmyadmin --strip-components=1
rm pma.tar.gz
echo "✅ phpMyAdmin downloaded successfully!"

# Download Junction
echo "📦 Downloading Junction..."
if [ -d "/shared/applications/junction" ]; then
    echo "🗑️ Removing existing junction directory..."
    rm -rf /shared/applications/junction
fi

curl -L -o junction-dev.zip "https://github.com/tribe-framework/junction/archive/refs/heads/dev.zip"
mkdir -p /shared/applications/junction
unzip -q junction-dev.zip
mv junction-dev/dist /shared/applications/junction/dist
rm -rf junction-dev
rm junction-dev.zip
chmod -R 755 /shared/applications/junction
echo "✅ Junction downloaded successfully!"

echo "🎉 Setup complete!"