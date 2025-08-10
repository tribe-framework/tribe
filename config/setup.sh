#!/bin/bash

# Setup script to download phpMyAdmin and Junction
set -e

echo "🚀 Setting up development environment..."

# Function to generate a 32-character random string for blowfish secret
generate_blowfish_secret() {
    # Check if we're on macOS or Linux and use appropriate method
    if command -v openssl >/dev/null 2>&1; then
        # Use openssl for cross-platform compatibility
        openssl rand -base64 32 | tr -d "=+/" | cut -c1-32
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS specific - use LC_ALL=C to avoid illegal byte sequence
        LC_ALL=C cat /dev/urandom | LC_ALL=C tr -dc 'a-zA-Z0-9' | head -c 32
    else
        # Linux
        cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1
    fi
}

# Function to update phpMyAdmin config with blowfish secret
update_phpmyadmin_config() {
    local secret="$1"
    local config_file="config/tribe/phpmyadmin.config.inc.php"
    
    if [ -f "$config_file" ]; then
        echo "🔧 Updating phpMyAdmin config with blowfish secret..."
        # Use different sed syntax for macOS vs Linux
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS
            sed -i '' "s/\$cfg\['blowfish_secret'\] = '';/\$cfg['blowfish_secret'] = '$secret';/" "$config_file"
        else
            # Linux
            sed -i "s/\$cfg\['blowfish_secret'\] = '';/\$cfg['blowfish_secret'] = '$secret';/" "$config_file"
        fi
        echo "✅ phpMyAdmin config updated!"
    else
        echo "⚠️  phpMyAdmin config file not found. Will copy template after download."
    fi
}

# Function to check and update .env file with blowfish secret and API key
check_and_update_env_secrets() {
    local env_file=".env"
    
    if [ -f "$env_file" ]; then
        # Check if BLOWFISH_SECRET exists and is empty
        if grep -q "^BLOWFISH_SECRET=" "$env_file"; then
            local current_secret=$(grep "^BLOWFISH_SECRET=" "$env_file" | cut -d'=' -f2 | tr -d '"' | tr -d "'")
            
            if [ -z "$current_secret" ]; then
                echo "🔐 BLOWFISH_SECRET is empty, generating new secret..."
                local new_secret=$(generate_blowfish_secret)
                
                # Update the .env file with macOS/Linux compatibility
                if [[ "$OSTYPE" == "darwin"* ]]; then
                    # macOS
                    sed -i '' "s/^BLOWFISH_SECRET=.*/BLOWFISH_SECRET=\"$new_secret\"/" "$env_file"
                else
                    # Linux
                    sed -i "s/^BLOWFISH_SECRET=.*/BLOWFISH_SECRET=\"$new_secret\"/" "$env_file"
                fi
                echo "✅ BLOWFISH_SECRET updated in .env file!"
                
                # Update phpMyAdmin config
                update_phpmyadmin_config "$new_secret"
                
                return 0
            else
                echo "✅ BLOWFISH_SECRET already set in .env file"
                
                # Still update phpMyAdmin config with existing secret
                update_phpmyadmin_config "$current_secret"
                return 1
            fi
        else
            echo "🔐 BLOWFISH_SECRET not found in .env, adding it..."
            local new_secret=$(generate_blowfish_secret)
            echo "BLOWFISH_SECRET=\"$new_secret\"" >> "$env_file"
            echo "✅ BLOWFISH_SECRET added/updated in .env file!"
            
            # Update phpMyAdmin config
            update_phpmyadmin_config "$new_secret"
            return 0
        fi
    fi
    return 1
}

# Function to prompt user for input with default value
prompt_with_default() {
    local prompt="$1"
    local default="$2"
    local var_name="$3"
    
    echo -n "$prompt [$default]: "
    read user_input
    
    if [ -z "$user_input" ]; then
        eval "$var_name=\"$default\""
    else
        eval "$var_name=\"$user_input\""
    fi
}

# Check if .env file exists and warn user
if [ -f ".env" ]; then
    echo "⚠️  .env file already exists!"
    echo "This script will NOT override your existing .env file."
    echo "If you want to reconfigure, please rename or delete the existing .env file first."
    echo ""
    read -p "Continue with setup anyway? (y/N): " continue_setup
    if [[ ! "$continue_setup" =~ ^[Yy]$ ]]; then
        echo "Setup cancelled."
        exit 0
    fi
    SKIP_ENV_SETUP=true
else
    SKIP_ENV_SETUP=false
fi

# Create directories
mkdir -p applications
mkdir -p uploads

# Download phpMyAdmin
echo "📦 Downloading phpMyAdmin..."
if [ -d "applications/phpmyadmin" ]; then
    echo "🗑️ Removing existing phpmyadmin directory..."
    rm -rf applications/phpmyadmin
fi
    
curl -L https://files.phpmyadmin.net/phpMyAdmin/5.2.2/phpMyAdmin-5.2.2-all-languages.tar.gz -o pma.tar.gz
mkdir -p applications/phpmyadmin
tar -xzf pma.tar.gz -C applications/phpmyadmin --strip-components=1
rm pma.tar.gz
echo "✅ phpMyAdmin downloaded successfully!"

# Download Junction
echo "📦 Downloading Junction..."
if [ -d "applications/junction" ]; then
    echo "🗑️ Removing existing junction directory..."
    rm -rf applications/junction
fi

curl -L -o junction-dev.zip "https://github.com/tribe-framework/junction/archive/refs/heads/dev.zip"
mkdir -p applications/junction
unzip -q junction-dev.zip
mv junction-dev/dist applications/junction/dist
rm -rf junction-dev
rm junction-dev.zip
chmod -R 755 applications/junction
echo "✅ Junction downloaded successfully!"

# Setup environment configuration
if [ "$SKIP_ENV_SETUP" = false ]; then
    echo ""
    echo "🔧 Setting up environment configuration..."
    echo "Please provide the following configuration values (press Enter for defaults):"
    echo ""
    
    # Prompt for user inputs
    prompt_with_default "Web URL (without protocol)" "localhost:1212" "WEB_BARE_URL"
    prompt_with_default "Database password" "userpassword" "DB_PASS"
    prompt_with_default "Junction password" "password" "JUNCTION_PASSWORD"
    
    # Generate WEB_URL based on WEB_BARE_URL
    WEB_URL="http://$WEB_BARE_URL"
    TRIBE_API_URL="$WEB_URL"
    
    # Generate BLOWFISH_SECRET
    BLOWFISH_SECRET=$(generate_blowfish_secret)
    
    echo ""
    echo "📝 Creating .env file..."
    
    # Create .env file from template with user values
    cat > .env << EOF
ENV="dev"
ALLOW_CROSS_ORIGIN=true
SSL=false

WEB_BARE_URL="$WEB_BARE_URL"
WEB_URL="$WEB_URL"

DB_NAME="tribe_db"
DB_USER="tribe_user"
DB_PASS="$DB_PASS"
DB_HOST="mysql"
DB_PORT=3306

TRIBE_API_URL="$TRIBE_API_URL"
TRIBE_API_KEY=""

JUNCTION_PASSWORD="$JUNCTION_PASSWORD"
JUNCTION_SLUG="junction"

PLAUSIBLE_AUTH=""
PLAUSIBLE_DOMAIN=""

HIDE_POSTCODE_ATTRIBUTION="false"

DEFAULT_TIMEZONE="Asia/Kolkata"

BLOWFISH_SECRET="$BLOWFISH_SECRET"
EOF
    
    # Update phpMyAdmin config with the generated secret
    update_phpmyadmin_config "$BLOWFISH_SECRET"
    
    echo "✅ .env file created successfully!"
    echo ""
    echo "📋 Configuration Summary:"
    echo "  Web URL: $WEB_URL"
    echo "  Database Password: $DB_PASS"
    echo "  Junction Password: $JUNCTION_PASSWORD"
    echo "  BLOWFISH_SECRET: Generated and configured"
    echo ""
else
    echo ""
    echo "ℹ️ Skipping .env setup (file already exists)"
    echo "🔍 Checking BLOWFISH_SECRET configuration..."
    
    # Check and update secrets for existing .env
    check_and_update_env_secrets
fi

echo "🎉 Setup complete! You can now run 'docker-compose up -d'"